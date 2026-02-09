<?php

session_start();
require "./database/config.php";
require './utility/formatDateTime.php';

if (!isset($_SESSION["admin_id"]) || !isset($_GET['id'])) {
    header("location: index.php");
    exit();
}

$createdBy = base64_decode($_SESSION['admin_id']);

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    try {

        $customerId = intval(base64_decode($_GET['id']));


        // all invoices 
        $stmtFetchAll = $db->prepare("
        SELECT 
            invoice.*,
            invoice.status AS paymentStatus,
            customer.*,
            tax.*,
            (
                COALESCE(SUM(l.debit_amount), 0) -
                COALESCE(SUM(l.credit_amount), 0)
            ) AS outstanding_amount
        FROM invoice
        INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
            ON tax.tax_id = invoice.tax
        LEFT JOIN ledger_transactions l
            ON l.invoice_id = invoice.invoice_id
        WHERE invoice.is_active = 1
        AND customer.customer_id = ?
        GROUP BY invoice.invoice_id
    ");

        $stmtFetchAll->bind_param('i', $customerId);

        if ($stmtFetchAll->execute()) {
            $allInvoices = $stmtFetchAll->get_result();
        }


        // paid 
        $stmtFetchPaid = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.status = 'PAID' AND invoice.is_active = 1 AND customer.customer_id = ? ");

        $stmtFetchPaid->bind_param('i', $customerId);

        if ($stmtFetchPaid->execute()) {
            $paidInvoices = $stmtFetchPaid->get_result();
        }

        // pending
        $stmtFetchPending = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.status = 'PENDING' AND invoice.is_active = 1 AND customer.customer_id = ? ");

        $stmtFetchPending->bind_param('i', $customerId);

        if ($stmtFetchPending->execute()) {
            $pendingInvoices = $stmtFetchPending->get_result();
        }

        // cancelled
        $stmtFetchCancelled = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.status = 'CANCELLED' AND invoice.is_active = 1 AND customer.customer_id = ?  ");

        $stmtFetchCancelled->bind_param('i', $customerId);

        if ($stmtFetchCancelled->execute()) {
            $cancelledInvoices = $stmtFetchCancelled->get_result();
        }

        // refunded
        $stmtFetchRefunded = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.status = 'REFUNDED' AND invoice.is_active = 1 AND customer.customer_id = ?");

        $stmtFetchRefunded->bind_param('i', $customerId);

        if ($stmtFetchRefunded->execute()) {
            $refundedInvoices = $stmtFetchRefunded->get_result();
        }

        $stmtFetchLedgerTransaction = $db->prepare("SELECT
                invoice.invoice_id,
                invoice.invoice_number,
                ledger_transactions.*,
                invoice.status AS invoiceStatus,
                customer.customer_id,
                customer.customer_name,
                admin.admin_username,
                tax.tax_rate
            FROM ledger_transactions
            INNER JOIN customer
                ON customer.customer_id = ledger_transactions.customer_id
            LEFT JOIN invoice
                ON invoice.invoice_id = ledger_transactions.invoice_id
            LEFT JOIN admin 
                ON admin.admin_id = invoice.created_by
            LEFT JOIN tax 
                ON tax.tax_id = invoice.tax
            WHERE ledger_transactions.customer_id = ?    
            ORDER BY ledger_transactions.ledger_id ASC;
        ");

        $stmtFetchLedgerTransaction->bind_param('i', $customerId);
        if ($stmtFetchLedgerTransaction->execute()) {
            $ledgerTransactions = $stmtFetchLedgerTransaction->get_result();
        } else {
            $_SESSION['error'] = 'Error for fetching customers';
        }



    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['servicesIds'])) {
    $servicesIds = $_POST['servicesIds'];

    $stmtFetchService = $db->prepare('SELECT * FROM services WHERE service_id = ?');

    $results = [];
    foreach ($servicesIds as $id) {
        // Ensure the ID is an integer to prevent SQL injection
        $id = (int) $id;
        $stmtFetchService->execute([$id]);
        $service = $stmtFetchService->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($service) {
            $results[] = $service;
        }
    }
    echo json_encode([
        'status' => 200,
        'data' => $results
    ]);
    exit;
}

try {

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);

    // for card details
    $customerId = intval(base64_decode($_GET['id']));


    $stmt = $db->prepare("SELECT * FROM customer WHERE customer_id = ?");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $customerInfo = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtBalance = $db->prepare("
    SELECT balance
    FROM customer_wallet
    WHERE customer_id = ?
    ");

    $stmtBalance->bind_param("i", $customerId);
    $stmtBalance->execute();

    $result = $stmtBalance->get_result();
    $row = $result->fetch_assoc();

    $balance = (float) ($row['balance'] ?? 0.00);

    $stmtBalance->close();





} catch (\Throwable $th) {
    //throw $th;
    $_SESSION["error"] = $th->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_type'])) {

    try {

        $db->begin_transaction();

        // Required fields
        $requiredFields = ['transaction_type', 'payment_method', 'amount'];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }



        // Sanitize inputs
        $transactionType = strtoupper(trim($_POST['transaction_type']));
        $paymentMethod = strtoupper(trim($_POST['payment_method']));
        $amount = (float) $_POST['amount'];
        $customerId = intval(base64_decode($_GET['id']));
        $remarks = $_POST['remark'] ?? null;

        // Optional flags
        $doSettlement = isset($_POST['settle_invoice']) && $_POST['settle_invoice'] == 1;

        // Validation
        if ($transactionType !== 'PAYMENT') {
            throw new Exception('Only PAYMENT is allowed in this endpoint');
        }

        $validPaymentMethods = ['CREDIT_CARD', 'DEBIT_CARD', 'CASH', 'NET_BANKING', 'PAYPAL', 'OTHER'];

        if (!in_array($paymentMethod, $validPaymentMethods)) {
            throw new Exception('Invalid payment method');
        }

        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        // Check customer wallet balance
        $stmtBalance = $db->prepare("
            SELECT balance
            FROM customer_wallet
            WHERE customer_id = ?
        ");

        if (!$stmtBalance) {
            throw new Exception($db->error);
        }

        $stmtBalance->bind_param("i", $customerId);
        $stmtBalance->execute();
        $result = $stmtBalance->get_result();
        $row = $result->fetch_assoc();
        $stmtBalance->close();

        $currentBalance = (float) ($row['balance'] ?? 0.00);

        // Prevent over-payment
        if ($amount > $currentBalance) {
            throw new Exception(
                'Payment amount exceeds outstanding balance. Current balance: ' .
                number_format($currentBalance, 2)
            );
        }


        // Insert ledger transaction (PAYMENT)
        $ledgerSql = "
            INSERT INTO ledger_transactions
            (customer_id, invoice_id, transaction_type, payment_method, debit_amount, credit_amount, remarks, created_by)
            VALUES (?, NULL, 'PAYMENT', ?, 0, ?, ?, ?)
        ";

        $ledgerStmt = $db->prepare($ledgerSql);
        if (!$ledgerStmt) {
            throw new Exception($db->error);
        }

        $ledgerStmt->bind_param(
            "isdsi",
            $customerId,
            $paymentMethod,
            $amount,
            $remarks,
            $createdBy
        );

        if (!$ledgerStmt->execute()) {
            throw new Exception($ledgerStmt->error);
        }

        $ledgerId = $ledgerStmt->insert_id;
        $ledgerStmt->close();

        // Update customer wallet (PRIMARY)
        $walletSql = "
            UPDATE customer_wallet
            SET balance = balance - ?
            WHERE customer_id = ?
        ";

        $walletStmt = $db->prepare($walletSql);
        if (!$walletStmt) {
            throw new Exception($db->error);
        }

        $walletStmt->bind_param("di", $amount, $customerId);
        $walletStmt->execute();
        $walletStmt->close();

        // OPTIONAL: Invoice settlement (FIFO)
        if ($doSettlement) {

            $invoiceSql = "
                SELECT
                    i.invoice_id,
                    i.total_amount -
                    COALESCE(SUM(s.settled_amount), 0) AS due_amount
                FROM invoice i
                LEFT JOIN invoice_settlements s
                    ON s.invoice_id = i.invoice_id
                WHERE i.customer_id = ?
                GROUP BY i.invoice_id
                HAVING due_amount > 0
                ORDER BY i.created_at ASC
            ";

            $stmtInv = $db->prepare($invoiceSql);
            $stmtInv->bind_param("i", $customerId);
            $stmtInv->execute();
            $invoices = $stmtInv->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtInv->close();

            $remaining = $amount;

            foreach ($invoices as $inv) {

                if ($remaining <= 0) {
                    break;
                }

                $apply = min($remaining, $inv['due_amount']);

                // Insert settlement record
                $settleSql = "
                    INSERT INTO invoice_settlements
                    (invoice_id, ledger_id, settled_amount)
                    VALUES (?, ?, ?)
                ";

                $settleStmt = $db->prepare($settleSql);
                $settleStmt->bind_param(
                    "iid",
                    $inv['invoice_id'],
                    $ledgerId,
                    $apply
                );
                $settleStmt->execute();
                $settleStmt->close();

                // Update invoice status
                $newStatus = ($apply == $inv['due_amount'])
                    ? 'PAID'
                    : 'PARTIALLY_PAID';

                $upd = $db->prepare("
                    UPDATE invoice
                    SET status = ?
                    WHERE invoice_id = ?
                ");
                $upd->bind_param("si", $newStatus, $inv['invoice_id']);
                $upd->execute();
                $upd->close();

                $remaining -= $apply;
            }
        }

        // Commit
        $db->commit();

        echo json_encode([
            'status' => 201,
            'message' => 'Payment recorded successfully',
            'ledger_id' => $ledgerId
        ]);
        exit;

    } catch (Exception $e) {

        $db->rollback();

        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}


// send reminder
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdForReminder'])) {

    try {

        $invoiceId = intval($_POST['invoiceIdForReminder']);
        $stmtFetchCustomer = $db->prepare("SELECT invoice.*, customer.*, tax.* FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.is_active = 1 AND invoice.invoice_id = ? AND invoice.status IN ('PENDING') ");

        $stmtFetchCustomer->bind_param('i', $invoiceId);

        if ($stmtFetchCustomer->execute()) {
            $invoices = $stmtFetchCustomer->get_result()->fetch_assoc();

            // Check if the invoice exists
            if (empty($invoices)) {
                echo json_encode([
                    'status' => 404,
                    'message' => "Invoice Status is Not Pending"
                ]);
                exit; // Stop further execution
            }

        } else {
            echo json_encode([
                'status' => 500,
                'message' => "Database Query Execution Failed"
            ]);
            exit;
        }

        $stmtFetchEmailTemplates = $db->prepare("SELECT * FROM email_template WHERE is_active = 1 AND type = 'REMINDER' ");
        $stmtFetchEmailTemplates->execute();
        $emailTemplate = $stmtFetchEmailTemplates->get_result()->fetch_array(MYSQLI_ASSOC);

        // === Email Template Fallbacks ===
        $templateTitle = $emailTemplate['email_template_title'] ?? 'Payment Reminder';
        $emailSubject = $emailTemplate['email_template_subject'] ?? 'Payment Reminder: Overdue Invoices';

        $content1 = !empty($emailTemplate['content_1'])
            ? nl2br(trim($emailTemplate['content_1']))
            : '<p>We hope this message finds you well. The following invoice(s) are overdue. Kindly make the payment at your earliest convenience to avoid any service interruptions.</p>';

        $content2 = !empty($emailTemplate['content_2'])
            ? nl2br(trim($emailTemplate['content_2']))
            : '
        <p>Please settle the outstanding amount at your earliest convenience. For any questions or assistance, contact our support team at <a href="mailto:support@vibrantick.org">support@vibrantick.org</a> or call <a href="tel:+919870443528">+91-9870443528</a>.</p>
        <p>Thank you for your prompt attention to this matter.</p>
        <p>Best regards,<br>Vibrantick InfoTech Solution Team</p>';




        $customerName = htmlspecialchars($invoices['customer_name']);
        $customerEmail = htmlspecialchars($invoices['customer_email']);
        $invoiceNumber = htmlspecialchars($invoices['invoice_number']);
        $dueDate = htmlspecialchars($invoices['due_date']);
        $amount = number_format((float) $invoices['amount'], 2);
        $taxRate = htmlspecialchars($invoices['tax_rate']) ?: '0';
        $discount = number_format((float) $invoices['discount'], 2);
        $totalAmount = number_format((float) $invoices['total_amount'], 2);

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $userName;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
        $mail->Port = $port;
        $mail->setFrom($userName, $fromTitle);
        $mail->isHTML(true);

        // Prepare statement for updating reminder_count
        $stmtUpdate = $db->prepare('UPDATE invoice SET reminder_count = reminder_count + 1 WHERE invoice_id = ?');

        $emailBody = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$templateTitle}</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }
                    .header {
                        background-color: #007bff;
                        padding: 20px;
                        text-align: center;
                        color: #ffffff;
                    }
                    .header img {
                        max-width: 150px;
                        height: auto;
                        background-color: #fff;
                        border-radius: 4px;
                    }
                    .header h1 {
                        margin: 10px 0;
                        font-size: 24px;
                    }
                    .content {
                        padding: 20px;
                    }
                    .content p {
                        line-height: 1.6;
                        color: #333333;
                    }
                    .invoice-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                    }
                    .invoice-table th,
                    .invoice-table td {
                        border: 1px solid #dddddd;
                        padding: 12px;
                        text-align: left;
                    }
                    .invoice-table th {
                        background-color: #007bff;
                        color: #ffffff;
                        font-weight: bold;
                    }
                    .invoice-table tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    .invoice-table tr:hover {
                        background-color: #f1f1f1;
                    }
                    .footer {
                        background-color: #f4f4f4;
                        padding: 15px;
                        text-align: center;
                        font-size: 12px;
                        color: #666666;
                    }
                    .footer a {
                        color: #007bff;
                        text-decoration: none;
                        margin: 0 10px;
                    }
                    .footer img {
                        width: 24px;
                        height: 24px;
                        vertical-align: middle;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #007bff;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        margin-top: 20px;
                    }
                    @media only screen and (max-width: 600px) {
                        .container {
                            width: 100%;
                            margin: 10px;
                        }
                        .header img {
                            max-width: 120px;
                        }
                        .header h1 {
                            font-size: 20px;
                        }
                        .invoice-table th,
                        .invoice-table td {
                            font-size: 14px;
                            padding: 8px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <!-- Header -->
                    <div class="header">
                        <img src="{$logoUrl}" alt="Logo" />
                        <h1>{$templateTitle}</h1>
                    </div>

                    <!-- Content -->
                    <div class="content">
                        <p>Dear {$customerName},</p>
                        {$content1}

                        <!-- Invoice Table -->
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Tax</th>
                                    <th>Discount</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{$invoiceNumber}</td>
                                    <td>{$dueDate}</td>
                                    <td>Rs: {$amount}</td>
                                    <td>{$taxRate}</td>
                                    <td>Rs: {$discount}</td>
                                    <td><strong>Rs: {$totalAmount}</strong></td>
                                </tr>
                            </tbody>
                        </table>

                        {$content2}
                    </div>

                    <!-- Footer -->
                    <div class="footer">
                        <p>&copy; {$currentYear} {$fromTitle}. All rights reserved.</p>
                        <p>{$address1} <a href='mailto:{$supportEmail}'>{$supportEmail}</a></p>
                        <p>
                            <a href='{$linkedin}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/174/174857.png ' alt='LinkedIn'></a>
                            <a href='{$instagram}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/2111/2111463.png ' alt='Instagram'></a>
                            <a href='{$facebook}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/733/733547.png ' alt='Facebook'></a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            HTML;

        $mail->clearAddresses();
        $mail->addAddress($customerEmail, $customerName);
        $mail->Subject = $emailSubject;
        $mail->Body = $emailBody;

        if ($mail->send()) {

            $stmtUpdate->bind_param('i', $invoiceId);
            if (!$stmtUpdate->execute()) {
                echo "Failed to update reminder_count for invoice {$invoiceId}\n";
            }
            echo json_encode([
                'status' => 200,
                'message' => 'The Mail has been send to ' . $customerName,
                'data' => $logoUrl
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 403,
                'message' => 'Unable to Send Mail to ' . $customerName,
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage(),
        ]);
        exit;
    }
}

// send invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdForSend'])) {

    try {

        $invoiceId = intval($_POST['invoiceIdForSend']);
        $stmtFetchCustomer = $db->prepare("SELECT invoice.*, customer.*, tax.* FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.is_active = 1 AND invoice.invoice_id = ? AND invoice.status IN ('PENDING', 'PAID')");

        $stmtFetchCustomer->bind_param('i', $invoiceId);

        if ($stmtFetchCustomer->execute()) {
            $invoices = $stmtFetchCustomer->get_result()->fetch_assoc();

            // Check if the invoice exists
            if (empty($invoices)) {
                echo json_encode([
                    'status' => 404,
                    'message' => "Invoice Status is Not Pending"
                ]);
                exit; // Stop further execution
            }

        } else {
            echo json_encode([
                'status' => 500,
                'message' => "Database Query Execution Failed"
            ]);
            exit;
        }

        //GENERATE PDF CONTENT FROM download-invoice.php
        ob_start(); // Capture any output

        // Simulate GET request to download-invoice.php
        $_GET['id'] = base64_encode($invoiceId); // Match how download-invoice.php expects it

        // Include the file – it will generate PDF in memory
        require './download-invoice.php'; // This calls $pdf->Output() internally

        $pdfContent = ob_get_clean(); // Capture raw PDF output

        if (empty($pdfContent)) {
            echo json_encode(['status' => 500, 'message' => 'Failed to generate PDF']);
            exit;
        }

        $stmtFetchEmailTemplates = $db->prepare("SELECT * FROM email_template WHERE is_active = 1 AND type = 'ISSUED' ");
        $stmtFetchEmailTemplates->execute();
        $emailTemplate = $stmtFetchEmailTemplates->get_result()->fetch_array(MYSQLI_ASSOC);

        // === Email Template Fallbacks ===
        $templateTitle = $emailTemplate['email_template_title'] ?? 'Invoice Issued';
        $emailSubject = $emailTemplate['email_template_subject'] ?? 'Payment Request: Invoice from Vibrantick InfoTech';

        $content1 = !empty($emailTemplate['content_1'])
            ? nl2br(trim($emailTemplate['content_1']))
            : '<p>We are pleased to inform you that your invoice has been successfully generated. Please review the details below and make the payment before the due date to ensure uninterrupted service.</p>';

        $content2 = !empty($emailTemplate['content_2'])
            ? nl2br(trim($emailTemplate['content_2']))
            : '
            <p>If you have already made this payment, thank you! Please disregard this email or contact us if you need a receipt. For any questions or assistance, contact our support team at <a href="mailto:support@vibrantick.org">support@vibrantick.org</a> or call <a href="tel:+919870443528">+91-9870443528</a>.</p>
            <p>Thank you for your prompt attention to this matter.</p>
            <p>Best regards,<br>Vibrantick InfoTech Solution Team</p>';


        $customerName = htmlspecialchars($invoices['customer_name']);
        $customerEmail = htmlspecialchars($invoices['customer_email']);
        $invoiceNumber = htmlspecialchars($invoices['invoice_number']);
        $dueDate = htmlspecialchars($invoices['due_date']);
        $amount = number_format((float) $invoices['amount'], 2);
        $taxRate = htmlspecialchars($invoices['tax_rate']) ?: '0';
        $discount = number_format((float) $invoices['discount'], 2);
        $totalAmount = number_format((float) $invoices['total_amount'], 2);

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $userName;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
        $mail->Port = $port;
        $mail->setFrom($userName, $fromTitle);
        $mail->isHTML(true);

        // Prepare statement for updating reminder_count
        $stmtUpdate = $db->prepare('UPDATE invoice SET reminder_count = reminder_count + 1 WHERE invoice_id = ?');

        $emailBody = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$templateTitle}</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }
                    .header {
                        background-color: #007bff;
                        padding: 20px;
                        text-align: center;
                        color: #ffffff;
                    }
                    .header img {
                        max-width: 150px;
                        height: auto;
                        background-color: #fff;
                        border-radius: 4px;
                    }
                    .header h1 {
                        margin: 10px 0;
                        font-size: 24px;
                    }
                    .content {
                        padding: 20px;
                    }
                    .content p {
                        line-height: 1.6;
                        color: #333333;
                    }
                    .invoice-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                    }
                    .invoice-table th,
                    .invoice-table td {
                        border: 1px solid #dddddd;
                        padding: 12px;
                        text-align: left;
                    }
                    .invoice-table th {
                        background-color: #007bff;
                        color: #ffffff;
                        font-weight: bold;
                    }
                    .invoice-table tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    .invoice-table tr:hover {
                        background-color: #f1f1f1;
                    }
                    .footer {
                        background-color: #f4f4f4;
                        padding: 15px;
                        text-align: center;
                        font-size: 12px;
                        color: #666666;
                    }
                    .footer a {
                        color: #007bff;
                        text-decoration: none;
                        margin: 0 10px;
                    }
                    .footer img {
                        width: 24px;
                        height: 24px;
                        vertical-align: middle;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #007bff;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        margin-top: 20px;
                    }
                    @media only screen and (max-width: 600px) {
                        .container {
                            width: 100%;
                            margin: 10px;
                        }
                        .header img {
                            max-width: 120px;
                        }
                        .header h1 {
                            font-size: 20px;
                        }
                        .invoice-table th,
                        .invoice-table td {
                            font-size: 14px;
                            padding: 8px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <!-- Header -->
                    <div class="header">
                        <img src="{$logoUrl}" alt="Logo" />
                        <h1>{$templateTitle}</h1>
                    </div>

                    <!-- Content -->
                    <div class="content">
                        <p>Dear {$customerName},</p>
                        {$content1}

                        <!-- Invoice Table -->
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Tax</th>
                                    <th>Discount</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{$invoiceNumber}</td>
                                    <td>{$dueDate}</td>
                                    <td>Rs: {$amount}</td>
                                    <td>{$taxRate}</td>
                                    <td>Rs: {$discount}</td>
                                    <td><strong>Rs: {$totalAmount}</strong></td>
                                </tr>
                            </tbody>
                        </table>

                        {$content2}
                    </div>

                    <!-- Footer -->
                    <div class="footer">
                        <p>&copy; {$currentYear} {$fromTitle}. All rights reserved.</p>
                        <p>{$address1} <a href='mailto:{$supportEmail}'>{$supportEmail}</a></p>
                        <p>
                            <a href='{$linkedin}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/174/174857.png ' alt='LinkedIn'></a>
                            <a href='{$instagram}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/2111/2111463.png ' alt='Instagram'></a>
                            <a href='{$facebook}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/733/733547.png ' alt='Facebook'></a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            HTML;

        $mail->clearAddresses();
        $mail->addAddress($customerEmail, $customerName);
        $mail->Subject = $emailSubject;
        $mail->Body = $emailBody;

        // ✅ Attach the generated PDF as file
        $mail->addStringAttachment($pdfContent, "Invoice-$invoiceNumber.pdf", 'base64', 'application/pdf');

        if ($mail->send()) {

            $stmtUpdate->bind_param('i', $invoiceId);
            if (!$stmtUpdate->execute()) {
                echo "Failed to update reminder_count for invoice {$invoiceId}\n";
            }
            echo json_encode([
                'status' => 200,
                'message' => 'The Mail has been send to ' . $customerName,
                'data' => $logoUrl
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 403,
                'message' => 'Unable to Send Mail to ' . $customerName,
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage(),
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}


//Delete

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoiceId'])) {

    $invoiceId = intval($_POST['invoiceId']);

    if ($invoiceId <= 0) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid invoice ID.'
        ]);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE invoice SET is_active = 0 WHERE invoice_id = ?");
        $stmt->bind_param("i", $invoiceId);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected invoice deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmt->error
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }

    exit;
}



ob_end_clean();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="author" content="=">
    <meta name="robots" content="noindex, nofollow">
    <title>Customer Report </title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/plugins/daterangepicker/daterangepicker.css">

    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <!-- html to pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <!-- html to excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<body>
    <!-- <div id="global-loader">
        <div class="whirly-loader"> </div>
    </div> -->

    <div class="main-wrapper">
        <?php if (isset($_SESSION['success'])) { ?>
            <script>
                const notyf = new Notyf({
                    position: {
                        x: 'center',
                        y: 'top'
                    },
                    types: [
                        {
                            type: 'success',
                            background: '#4dc76f', // Change background color
                            textColor: '#FFFFFF',  // Change text color
                            dismissible: false
                        }
                    ]
                });
                notyf.success("<?php echo $_SESSION['success']; ?>");
            </script>
            <?php
            unset($_SESSION['success']);
            ?>
        <?php } ?>

        <?php if (isset($_SESSION['error'])) { ?>
            <script>
                const notyf = new Notyf({
                    position: {
                        x: 'center',
                        y: 'top'
                    },
                    types: [
                        {
                            type: 'error',
                            background: '#ff1916',
                            textColor: '#FFFFFF',
                            dismissible: false
                        }
                    ]
                });
                notyf.error("<?php echo $_SESSION['error']; ?>");
            </script>
            <?php
            unset($_SESSION['error']);
            ?>
        <?php } ?>

        <!-- Header Start -->
        <div class="header">
            <?php require_once("header.php"); ?>
        </div>
        <!-- Header End -->


        <!-- Sidebar Start -->
        <div class="sidebar" id="sidebar">
            <?php require_once("sidebar.php"); ?>
        </div>

        <div class="sidebar collapsed-sidebar" id="collapsed-sidebar">
            <?php require_once("sidebar-collapsed.php"); ?>
        </div>

        <div class="sidebar horizontal-sidebar">
            <?php require_once("sidebar-horizontal.php"); ?>
        </div>
        <!-- Sidebar End -->

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="add-item d-flex">
                        <div class="page-title">
                            <h4>Customer Report</h4>
                            <h6>Manage Your Customer Report</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportActiveTabToPDF()" data-bs-placement="top"
                                title="Pdf"><img src="assets/img/icons/pdf.svg" alt="img" /></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportActiveTabToExcel()" data-bs-placement="top"
                                title="Excel"><img src="assets/img/icons/excel.svg" alt="img" /></a>
                        </li>

                        <li>
                            <a href="" data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i
                                    data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                    <div class="page-btn">
                        <?php if ($isAdmin || hasPermission('Add Invoice', $privileges, $roleData['0']['role_name'])): ?>

                            <a href="javascript:void(0);" data-bs-toggle="modal" class="btn btn-added"
                                data-bs-target="#create-payment">
                                <i data-feather="send" class="me-2"></i>Create Payment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="customer-info employee-grid-widget">
                    <div class="container row">

                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6">
                            <div class="employee-grid-profile">
                                <div class="profile-info">
                                    <div class="profile-pic active-profile">
                                        <img src="<?= !empty($customerInfo['image']) ? $customerInfo['image'] : 'assets/img/users/user-02.jpg' ?>"
                                            alt="" />
                                    </div>
                                    <h5><?= !empty($customerInfo['customer_name']) ? $customerInfo['customer_name'] : '' ?>
                                    </h5>

                                </div>
                                <ul class="department">

                                    <table class="w-100 customer-card">

                                        <tr>
                                            <td class="customer-header">
                                                Email :</td>
                                            <td><?= !empty($customerInfo['customer_email']) ? $customerInfo['customer_email'] : '' ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="customer-header">Balance :</td>
                                            <td><?= !empty($balance) ? $balance : '' ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="customer-header">GST No :</td>
                                            <td><?= !empty($customerInfo['gst_number']) ? $customerInfo['gst_number'] : '' ?>
                                            </td>
                                        </tr>
                                    </table>

                                </ul>

                            </div>
                        </div>
                        <div class="col-xxl-8 col-xl-8 col-lg-8 col-md-6">
                            <div class="employee-grid-profile">
                                <table width="100%" cellpadding="0" cellspacing="0" class="customer-card">
                                    <tr class="row-btm-border">
                                        <th class="customer-title" colspan="3">
                                            Customer Information
                                        </th>
                                    </tr>
                                    <tr class="row-btm-border">
                                        <td class="customer-header">
                                            Shipping Name :
                                        </td>
                                        <td>
                                            <?= !empty($customerInfo['ship_name']) ? $customerInfo['ship_name'] : '' ?>
                                        </td>
                                    </tr>
                                    <tr class="row-btm-border">
                                        <td class="customer-header">
                                            Shipping Phone :
                                        </td>
                                        <td><?= !empty($customerInfo['ship_phone']) ? $customerInfo['ship_phone'] : '' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="customer-header">
                                            Shipping Email :
                                        </td>
                                        <td>
                                            <?= !empty($customerInfo['ship_email']) ? $customerInfo['ship_email'] : '' ?>
                                        </td>


                                    </tr>
                                </table>
                            </div>
                            <div class="employee-grid-profile">
                                <table width="100%" cellpadding="0" cellspacing="0" class="customer-card">
                                    <tr class="row-btm-border">
                                        <td class="customer-header">
                                            Shipping Address :
                                        </td>

                                        <td> <?= !empty($customerInfo['ship_address']) ? $customerInfo['ship_address'] : '' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="customer-header">
                                            Customer Address :
                                        </td>
                                        <td> <?= !empty($customerInfo['customer_address']) ? $customerInfo['customer_address'] : '' ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="table-tab">
                    <ul class="nav nav-pills" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-report-tab" data-bs-toggle="pill"
                                data-bs-target="#all-report" type="button" role="tab" aria-controls="all-report"
                                aria-selected="true">All Invoices</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="paid-report-tab" data-bs-toggle="pill"
                                data-bs-target="#paid-report" type="button" role="tab" aria-controls="paid-report"
                                aria-selected="true">Paid Invoices</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pending-report-tab" data-bs-toggle="pill"
                                data-bs-target="#pending-report" type="button" role="tab" aria-controls="pending-report"
                                aria-selected="false">Pending Invoices</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cancelled-report-tab" data-bs-toggle="pill"
                                data-bs-target="#cancelled-report" type="button" role="tab"
                                aria-controls="cancelled-report" aria-selected="false">Cancelled Invoices
                            </button>
                        </li>
                        <!-- <li class="nav-item" role="presentation">
                            <button class="nav-link" id="refunded-report-tab" data-bs-toggle="pill"
                                data-bs-target="#refunded-report" type="button" role="tab"
                                aria-controls="refunded-report" aria-selected="false">Refunded Invoices</button>
                        </li> -->
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="transaction-report-tab" data-bs-toggle="pill"
                                data-bs-target="#transaction-report" type="button" role="tab"
                                aria-controls="transaction-report" aria-selected="false">Ledger Transaction</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="pills-tabContent">
                        <!-- All Invoices Tab -->
                        <div class="tab-pane fade show active" id="all-report" role="tabpanel"
                            aria-labelledby="all-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="table-responsive">
                                        <table id="allTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Due Date</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Discount</th>
                                                    <th>Tax</th>
                                                    <th>Total Amount</th>
                                                    <th>Outstanding Amount</th>
                                                    <th>Status</th>
                                                    <th class="no-sort text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalAmountSum = 0; // Initialize total
                                                $pendingAmountSum = 0; // Initialize total
                                                foreach ($allInvoices->fetch_all(MYSQLI_ASSOC) as $allInvoice) {
                                                    $totalAmountSum += $allInvoice['total_amount']; // Add to total
                                                
                                                    if ($allInvoice['paymentStatus'] == 'PENDING') {
                                                        $pendingAmountSum += $allInvoice['outstanding_amount'];
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $allInvoice['invoice_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td><?php echo formatDateTime($allInvoice['due_date'], $localizationSettings); ?>
                                                        </td>
                                                        <td>
                                                            <a
                                                                href="javascript:void(0);"><?php echo htmlspecialchars($allInvoice['customer_name']); ?></a>
                                                        </td>
                                                        <td><a href="#" class="view-note view-service"
                                                                data-bs-toggle="modal"
                                                                data-service-id="<?php echo htmlspecialchars($allInvoice['service_id']); ?>"
                                                                data-status="<?php echo htmlspecialchars($allInvoice['paymentStatus']); ?>"
                                                                data-bs-target="#view-notes">View</a></td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($allInvoice['amount']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($allInvoice['discount']); ?>%</td>
                                                        <td><?php echo htmlspecialchars($allInvoice['tax_name'] . "-" . $allInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($allInvoice['total_amount']); ?>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($allInvoice['outstanding_amount']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($allInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($allInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($allInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($allInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <a class="action-set" href="javascript:void(0);"
                                                                data-bs-toggle="dropdown" aria-expanded="true">
                                                                <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                            </a>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a target="_blank"
                                                                        href="<?php echo getenv("BASE_URL") . "view-invoice?id=" . base64_encode($allInvoice['invoice_id']) ?>"
                                                                        class="editStatus dropdown-item" data-admin-id=""><i
                                                                            data-feather="eye" class="info-img"></i>Show
                                                                        Detail</a>
                                                                </li>
                                                                <?php if ($isAdmin || hasPermission('Edit Invoice', $privileges, $roleData['0']['role_name'])): ?>

                                                                    <li>
                                                                        <a href="<?php echo getenv("BASE_URL") . "edit-invoice?id=" . base64_encode($allInvoice['invoice_id']) ?>"
                                                                            class="editButton dropdown-item"><i
                                                                                data-feather="edit" class="info-img"></i>Edit
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li>
                                                                    <a target="_blank"
                                                                        href="<?php echo getenv("BASE_URL") . "download-invoice?id=" . base64_encode($allInvoice['invoice_id']) ?>"
                                                                        class="qrCode dropdown-item"><i
                                                                            data-feather="download"
                                                                            class="info-img"></i>Download
                                                                    </a>
                                                                </li>
                                                                <?php if ($isAdmin || hasPermission('Delete Invoice', $privileges, $roleData['0']['role_name'])): ?>
                                                                    <li>
                                                                        <a href="javascript:void(0);"
                                                                            data-invoice-id="<?php echo $allInvoice['invoice_id'] ?>"
                                                                            class="dropdown-item deleteButton mb-0"><i
                                                                                data-feather="trash-2"
                                                                                class="info-img"></i>Delete </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($isAdmin || hasPermission('Send Reminder', $privileges, $roleData['0']['role_name'])): ?>

                                                                    <li>
                                                                        <a href="javascript:void(0);"
                                                                            data-invoice-id="<?php echo $allInvoice['invoice_id'] ?>"
                                                                            class="dropdown-item sendReminder mb-0"><i
                                                                                data-feather="bell" class="info-img"></i>Send
                                                                            Reminder </a>
                                                                    </li>
                                                                <?php endif; ?>

                                                                <?php if ($isAdmin || hasPermission('Send Invoice', $privileges, $roleData['0']['role_name'])): ?>

                                                                    <li>
                                                                        <a href="javascript:void(0);"
                                                                            data-invoice-id="<?php echo $allInvoice['invoice_id'] ?>"
                                                                            class="dropdown-item sendInvoice mb-0"><i
                                                                                data-feather="send" class="info-img"></i>Send
                                                                            Invoice </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="7"></td>
                                                    <td><strong><span class="text-success">Total:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td><strong><span class="text-danger">Pending:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($balance, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Paid Invoices Tab -->
                        <div class="tab-pane fade show" id="paid-report" role="tabpanel"
                            aria-labelledby="paid-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="table-responsive">
                                        <table id="paidTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Due Date</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Discount</th>
                                                    <th>Tax</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($paidInvoices->fetch_all(MYSQLI_ASSOC) as $paidInvoice) { ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds" value="<?php echo $paidInvoice['invoice_id'] ?>>
                                                                <span class=" checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td><?php echo formatDateTime($paidInvoice['due_date'], $localizationSettings); ?>
                                                        </td>
                                                        <td>
                                                            <a
                                                                href="javascript:void(0);"><?php echo htmlspecialchars($paidInvoice['customer_name']); ?></a>
                                                        </td>
                                                        <td><a href="#" class="view-note view-service"
                                                                data-bs-toggle="modal"
                                                                data-service-id="<?php echo htmlspecialchars($paidInvoice['service_id']); ?>"
                                                                data-status="<?php echo htmlspecialchars($paidInvoice['paymentStatus']); ?>"
                                                                data-bs-target="#view-notes">View</a></td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($paidInvoice['amount']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($paidInvoice['discount']); ?>%</td>
                                                        <td><?php echo htmlspecialchars($paidInvoice['tax_name'] . "-" . $paidInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($paidInvoice['total_amount']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($paidInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Pending Invoices Tab -->
                        <div class="tab-pane fade" id="pending-report" role="tabpanel"
                            aria-labelledby="pending-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="table-responsive">
                                        <table id="pendingTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all2">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Due Date</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Discount</th>
                                                    <th>Tax</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingInvoices->fetch_all(MYSQLI_ASSOC) as $pendingInvoice) { ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds" value="<?php echo $pendingInvoice['invoice_id'] ?>>
                                                                <span class=" checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td><?php echo formatDateTime($pendingInvoice['due_date'], $localizationSettings); ?>
                                                        </td>
                                                        </td>
                                                        <td>
                                                            <a
                                                                href="javascript:void(0);"><?php echo htmlspecialchars($pendingInvoice['customer_name']); ?></a>
                                                        </td>
                                                        <td><a href="#" class="view-note view-service"
                                                                data-bs-toggle="modal"
                                                                data-service-id="<?php echo htmlspecialchars($pendingInvoice['service_id']); ?>"
                                                                data-status="<?php echo htmlspecialchars($pendingInvoice['paymentStatus']); ?>"
                                                                data-bs-target="#view-notes">View</a></td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($pendingInvoice['amount']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($pendingInvoice['discount']); ?>%
                                                        </td>
                                                        <td><?php echo htmlspecialchars($pendingInvoice['tax_name'] . "-" . $pendingInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($pendingInvoice['total_amount']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($pendingInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Cancelled Invoices Tab -->
                        <div class="tab-pane fade" id="cancelled-report" role="tabpanel"
                            aria-labelledby="cancelled-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="table-responsive">
                                        <table id="cancelledTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all3">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Due Date</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Discount</th>
                                                    <th>Tax</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cancelledInvoices->fetch_all(MYSQLI_ASSOC) as $cancelledInvoice) { ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds" value="<?php echo $cancelledInvoice['invoice_id'] ?>>
                                                                <span class=" checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td><?php echo formatDateTime($cancelledInvoice['due_date'], $localizationSettings);
                                                        ?>
                                                        </td>
                                                        <td>
                                                            <a
                                                                href="javascript:void(0);"><?php echo htmlspecialchars($cancelledInvoice['customer_name']); ?></a>
                                                        </td>
                                                        <td><a href="#" class="view-note view-service"
                                                                data-bs-toggle="modal"
                                                                data-service-id="<?php echo htmlspecialchars($cancelledInvoice['service_id']); ?>"
                                                                data-status="<?php echo htmlspecialchars($cancelledInvoice['paymentStatus']); ?>"
                                                                data-bs-target="#view-notes">View</a></td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($cancelledInvoice['amount']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cancelledInvoice['discount']); ?>%
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cancelledInvoice['tax_name'] . "-" . $cancelledInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($cancelledInvoice['total_amount']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($cancelledInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Refunded Invoices Tab -->
                        <!-- <div class="tab-pane fade" id="refunded-report" role="tabpanel"
                            aria-labelledby="refunded-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>


                                    <div class="table-responsive">
                                        <table id="refundedTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all4">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Due Date</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Discount</th>
                                                    <th>Tax</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($refundedInvoices->fetch_all(MYSQLI_ASSOC) as $refundedInvoice) { ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds" value="<?php echo $refundedInvoice['invoice_id'] ?>>
                                                                <span class=" checkmarks"></span>
                                                            </label>
                                                        </td>

                                                        <td><?php echo formatDateTime($refundedInvoice['due_date'], $localizationSettings); ?>
                                                        </td>
                                                        <td>
                                                            <a
                                                                href="javascript:void(0);"><?php echo htmlspecialchars($refundedInvoice['customer_name']); ?></a>
                                                        </td>
                                                        <td><a href="#" class="view-note view-service"
                                                                data-bs-toggle="modal"
                                                                data-service-id="<?php echo htmlspecialchars($refundedInvoice['service_id']); ?>"
                                                                data-status="<?php echo htmlspecialchars($refundedInvoice['paymentStatus']); ?>"
                                                                data-bs-target="#view-notes">View</a></td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($refundedInvoice['amount']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($refundedInvoice['discount']); ?>%
                                                        </td>
                                                        <td><?php echo htmlspecialchars($refundedInvoice['tax_name'] . "-" . $refundedInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($refundedInvoice['total_amount']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($refundedInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                        <!-- Ledger Transaction Tab -->
                        <div class="tab-pane fade" id="transaction-report" role="tabpanel"
                            aria-labelledby="transaction-report-tab">
                            <div class="card table-list-card">
                                <div class="card-body">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>

                                    </div>


                                    <div class="table-responsive">
                                        <table id="transactionTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th class="no-sort">
                                                        <label class="checkboxs">
                                                            <input type="checkbox" id="select-all">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Ledger Id</th>
                                                    <!-- <th>Customer Name</th> -->
                                                    <th>Transaction Date</th>
                                                    <th>Transaction Type</th>
                                                    <th>Payment Method</th>
                                                    <th>Created Date</th>
                                                    <th>Debit Amount</th>
                                                    <th>Credit Amount</th>
                                                    <th>Type</th>

                                                    <th class="no-sort text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalPaidAmount = 0;
                                                $totalPendingAmount = 0;
                                                $count = 1;

                                                foreach ($ledgerTransactions->fetch_all(MYSQLI_ASSOC) as $transaction) {
                                                    $totalPaidAmount += $transaction['credit_amount'];
                                                    $totalPendingAmount += $transaction['debit_amount'];
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $transaction['ledger_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <?php echo $count ?>
                                                        </td>
                                                        <!-- <td>
                                                            <a class="text-primary" target="_blank"
                                                                href="<?= getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($transaction['customer_id']) ?>">
                                                                <?= $transaction['customer_name'] ?>
                                                            </a>

                                                        </td> -->
                                                        <td>
                                                            <?php echo formatDateTime($transaction['transaction_date'], $localizationSettings); ?>
                                                        </td>
                                                        <td>
                                                            <?= $transaction['transaction_type'] ?>
                                                        </td>
                                                        <td>
                                                            <?= $transaction['payment_method'] ?>
                                                        </td>

                                                        <td>
                                                            <?php echo formatDateTime($transaction['created_at'], $localizationSettings); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $transaction['debit_amount']; ?>
                                                        <td>
                                                            <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $transaction['credit_amount']; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($transaction['transaction_type'] == 'PAYMENT') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($transaction['transaction_type'] == 'REFUND') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($transaction['transaction_type'] == 'ADJUSTMENT') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($transaction['transaction_type'] == 'INVOICE') { ?>
                                                                <span class="badge badge-lg bg-primary">Invoice</span>
                                                            <?php } ?>
                                                        </td>



                                                        <td class="text-center">
                                                            <a class="action-set" href="javascript:void(0);"
                                                                data-bs-toggle="dropdown" aria-expanded="true">
                                                                <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                            </a>
                                                            <ul class="dropdown-menu">
                                                                <?php if ($isAdmin || hasPermission('Ledger Details', $privileges, $roleData['0']['role_name'])): ?>
                                                                    <li>
                                                                        <a href="javascript:void(0);" data-bs-toggle="modal"
                                                                            class="btn btn-added show-payment-btn"
                                                                            data-bs-target="#show-payment"
                                                                            data-ledger-id="<?= $transaction['ledger_id'] ?>"
                                                                            data-invoice-id="<?= $transaction['invoice_id'] ?>"
                                                                            data-customer-id="<?= $transaction['customer_id'] ?>"
                                                                            data-payment-method="<?= $transaction['payment_method'] ?>"
                                                                            data-transaction-type="<?= $transaction['transaction_type'] ?>"
                                                                            data-debit-amount="<?= $transaction['debit_amount'] ?>"
                                                                            data-credit-amount="<?= $transaction['credit_amount'] ?>"
                                                                            data-remarks="<?= $transaction['remarks'] ?>"
                                                                            data-created_at="<?= $transaction['created_at'] ?>"><i
                                                                                data-feather="eye" class="info-img"></i>Show
                                                                            Detail</a>
                                                                    </li>
                                                                <?php endif; ?>

                                                            </ul>
                                                        </td>
                                                    </tr>
                                                    <?php $count++;
                                                } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="8"></td>
                                                    <td>
                                                        <strong>
                                                            <span class="text-success">Total Paid:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalPaidAmount, 2); ?>
                                                            </span>
                                                        </strong>
                                                    </td>
                                                    <!-- <td>
                                            <strong>
                                                <span class="text-danger">Pending:
                                                    <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalPendingAmount, 2); ?>
                                                </span>
                                            </strong>
                                        </td> -->
                                                    <td colspan="1"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="view-notes">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Service</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <p>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="create-payment">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Create Payment</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form class="create-payment-form">
                                <div class="mb-3">
                                    <label class="form-label">Transaction Type</label>
                                    <select class="form-select" name="transaction_type" id="transaction_type">
                                        <option>Select</option>
                                        <option value="PAYMENT">Payment</option>
                                        <option value='REFUND'>Refund</option>
                                        <option value='ADJUSTMENT'>Adjustment</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Transaction Type</label>
                                    <select id="payment_method" name="payment_method" class="form-control" required>
                                        <option>Select Method</option>
                                        <option value="CASH">Cash</option>
                                        <option value="CREDIT_CARD">Credit Card</option>
                                        <option value="DEBIT_CARD">Debit Card</option>
                                        <option value="NET_BANKING">Net Banking</option>
                                        <option value="PAYPAL">Paypal</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="number" name="amount" step="0.01" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remark</label>
                                    <textarea class="form-control h-100" name="remark" rows="5">Remark...</textarea>
                                    <p class="mt-1">Maximum 60 Characters</p>
                                </div>

                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-submit">Create Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="show-payment">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Show Payment</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form class="show-payment-form">
                                <input type="hidden" name="editLedgerId" id="editLedgerId">
                                <div class="mb-3">
                                    <label class="form-label">Transaction Type</label>
                                    <select class="form-select" name="edit_transaction_type" id="edit_transaction_type">
                                        <option>Select</option>
                                        <option value="INVOICE">Invoice</option>
                                        <option value="PAYMENT">Payment</option>
                                        <option value='REFUND'>Refund</option>
                                        <option value='ADJUSTMENT'>Adjustment</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Transaction Type</label>
                                    <select id="edit_payment_method" name="edit_payment_method" class="form-control"
                                        required>
                                        <option>Select Method</option>
                                        <option value="CASH">Cash</option>
                                        <option value="CREDIT_CARD">Credit Card</option>
                                        <option value="DEBIT_CARD">Debit Card</option>
                                        <option value="NET_BANKING">Net Banking</option>
                                        <option value="PAYPAL">Paypal</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="number" id="editAmount" name="editAmount" step="0.01"
                                        class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remark</label>
                                    <textarea class="form-control h-100" id="editRemark" name="editRemark"
                                        rows="5">Remark...</textarea>
                                    <p class="mt-1">Maximum 60 Characters</p>
                                </div>

                                <!-- <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-submit">Create Payment</button>
                                </div> -->
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/js/script.js" type="27258ef042af1fde9a60cc39-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="27258ef042af1fde9a60cc39-|49" defer=""></script>
    <script src="assets/js/custom.js"></script>

    <script>
        $(document).ready(function () {

            // Initialize Notyf
            const notyf = new Notyf({
                duration: 3000,
                position: { x: "center", y: "top" },
                types: [
                    {
                        type: "success",
                        background: "#4dc76f",
                        textColor: "#FFFFFF",
                        dismissible: false,
                    },
                    {
                        type: "error",
                        background: "#ff1916",
                        textColor: "#FFFFFF",
                        dismissible: false,
                        duration: 3000,
                    },
                ],
            });

            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let invoiceId = $(this).data('invoice-id');

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'manage-invoice.php',
                            type: 'POST',
                            data: { invoiceId: invoiceId },
                            success: function (response) {
                                let result;

                                try {
                                    result = JSON.parse(response);
                                } catch (e) {
                                    Swal.fire('Error!', 'Invalid server response.', 'error');
                                    return;
                                }

                                if (result.status === 200) {
                                    Swal.fire(
                                        'Deleted!',
                                        'The invoice has been deleted.',
                                        'success'
                                    ).then(() => {
                                        location.reload(); // Reload page after confirmation
                                    });
                                } else {
                                    Swal.fire('Error!', result.message || 'Deletion failed.', 'error');
                                }
                            },
                            error: function () {
                                Swal.fire(
                                    'Error!',
                                    'There was an error contacting the server.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.sendReminder', function (e) {
                e.preventDefault();

                let invoiceId = $(this).data('invoice-id');
                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Send Mail!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'manage-invoice.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { invoiceIdForReminder: invoiceId },
                            success: function (response) {
                                let result = JSON.parse(response);
                                console.log(result);

                                if (result.status == 200) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Send!',
                                        result.message,
                                        'success' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 403) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.message,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 404) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.message,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 500) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.error,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }

                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error sending the mail.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.sendInvoice', function (e) {
                e.preventDefault();

                let invoiceId = $(this).data('invoice-id');


                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Send Mail!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'manage-invoice.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { invoiceIdForSend: invoiceId },
                            success: function (response) {
                                console.log(response);
                                let result = JSON.parse(response);
                                console.log(result);

                                if (result.status == 200) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Send!',
                                        result.message,
                                        'success' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 403) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.message,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 404) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.message,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 500) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.error,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }

                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error sending the mail.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });

            $(document).on("submit", ".create-payment-form", async function (e) {
                e.preventDefault();

                let transactionType = $('select[name="transaction_type"]').val();
                let paymentMethod = $('select[name="payment_method"]').val();
                let amount = $('input[name="amount"]').val().trim();
                let remark = $('textarea[name="remark"]').val().trim();

                const transactionTypeRegex = /^(PAYMENT|REFUND|ADJUSTMENT)$/;
                const paymentMethodRegex = /^(CASH|CREDIT_CARD|DEBIT_CARD|NET_BANKING|PAYPAL|OTHER)$/;
                const amountRegex = /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/;



                // Required check
                if (!transactionType || !paymentMethod || !amount) {
                    notyf.error("All fields are required.");
                    return;
                }



                // Transaction Type validation
                if (!transactionTypeRegex.test(transactionType)) {
                    notyf.error("Please select a valid transaction type.");
                    return;
                }

                // Payment Method validation
                if (!paymentMethodRegex.test(paymentMethod)) {
                    notyf.error("Please select a valid payment method.");
                    return;
                }

                // Amount validation
                if (!amountRegex.test(amount) || parseFloat(amount) <= 0) {
                    notyf.error("Please enter a valid amount greater than 0.");
                    return;
                }

                let formDataObject = {
                    transaction_type: transactionType,
                    payment_method: paymentMethod,
                    amount: amount,
                    remark: remark,
                }

                await $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formDataObject,
                    dataType: 'json',
                    success: function (response) {
                        if (response.status == 201) {
                            // Success - reset form
                            $('.create-payment-form')[0].reset();
                            notyf.success("Payment created successfully");
                            window.location.reload();

                        } else {
                            notyf.error(response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.error("Raw Response:", xhr.responseText);
                        notyf.error("An error occurred while processing your request. Please try again.",);
                    }
                });

            });
            $(document).on('click', '.show-payment-btn', function () {

                let customerId = $(this).data('customer-id');
                let ledgerId = $(this).data('ledger-id');
                let invoiceId = $(this).data('invoice-id');
                let paymentMethod = $(this).data('payment-method');
                let transactionType = $(this).data('transaction-type');
                let debitAmount = $(this).data('debit-amount');
                let creditAmount = $(this).data('credit-amount');
                let remarks = $(this).data('remarks');

                // Hidden fields
                $('#editLedgerId').val(ledgerId);

                $('select[name="edit_customer_id"]').val(customerId);

                $('select[name="edit_transaction_type"]').val(transactionType);

                // Amount logic (credit OR debit)
                let amount = creditAmount > 0 ? creditAmount : debitAmount;
                $('input[name="editAmount"]').val(amount);

                // Payment method
                if (paymentMethod) {
                    $('#edit_payment_method').val(paymentMethod);
                }

                // Remarks
                $('textarea[name="editRemark"]').val(remarks);
            });
            $(document).on('click', '.view-service', function (e) {
                e.preventDefault();

                let servicesIds = $(this).data('service-id');
                console.log(servicesIds);


                $.ajax({
                    url: 'view-customer-report.php?id=<?php echo $_GET['id'] ?>',
                    type: 'POST',
                    data: { servicesIds: servicesIds },
                    success: function (response) {
                        try {
                            let result = JSON.parse(response);
                            const serviceNames = result.data
                                .flat()
                                .map(service => service.service_name)
                                .filter(name => name);

                            $('#view-notes .custom-modal-body p').text(serviceNames);

                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response content:', response);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX error:', error);
                        console.error('Status:', status);
                        console.error('XHR:', xhr);
                    }
                });
            });
        });
    </script>
</body>

</html>