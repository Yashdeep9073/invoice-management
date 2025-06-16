<?php
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {

    $stmtFetch = $db->prepare("SELECT * FROM email_settings WHERE is_active = 1");
    $stmtFetch->execute();
    $emailSettingData = $stmtFetch->get_result()->fetch_all(MYSQLI_ASSOC);

    $host = !empty($emailSettingData[0]['email_host']) ? $emailSettingData[0]['email_host'] : getenv("SMTP_HOST");
    $userName = !empty($emailSettingData[0]['email_address']) ? $emailSettingData[0]['email_address'] : getenv('SMTP_USER_NAME');
    $password = !empty($emailSettingData[0]['email_password']) ? $emailSettingData[0]['email_password'] : getenv('SMTP_PASSCODE');
    $port = !empty($emailSettingData[0]['email_port']) ? $emailSettingData[0]['email_port'] : getenv('SMTP_PORT');
    $title = !empty($emailSettingData[0]['email_from_title']) ? $emailSettingData[0]['email_from_title'] : "Vibrantick InfoTech Solution";

    // Get current date for due_date comparison
    $currentDate = date('Y-m-d');

    // Fetch pending invoices with reminder_enabled = 1 and expired due_date
    $stmtFetch = $db->prepare('
    SELECT invoice.*, tax.*, customer.customer_email, customer.customer_name
    FROM invoice 
    INNER JOIN customer 
        ON customer.customer_id = invoice.customer_id
    INNER JOIN tax
        ON tax.tax_id = invoice.tax
    WHERE invoice.status = "PENDING" 
    AND invoice.reminder_enabled = 1 
    AND invoice.due_date <= ?
    ');
    $stmtFetch->bind_param('s', $currentDate);

    if (!$stmtFetch->execute()) {
        throw new Exception('Failed to execute invoice query');
    }

    $invoices = $stmtFetch->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtFetch->close();

    if (empty($invoices)) {
        echo "No overdue invoices found for reminders.";
        exit;
    }

    // Group invoices by customer
    $invoicesByCustomer = [];
    foreach ($invoices as $invoice) {
        $customerEmail = $invoice['customer_email'];
        if (!isset($invoicesByCustomer[$customerEmail])) {
            $invoicesByCustomer[$customerEmail] = [
                'customer_name' => $invoice['customer_name'],
                'invoices' => []
            ];
        }
        $invoicesByCustomer[$customerEmail]['invoices'][] = $invoice;
    }

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
    $mail->setFrom($userName, $title);
    $mail->isHTML(true);

    // Prepare statement for updating reminder_count
    $stmtUpdate = $db->prepare('UPDATE invoice SET reminder_count = reminder_count + 1 WHERE invoice_id = ?');

    // Send emails to each customer
    foreach ($invoicesByCustomer as $customerEmail => $customerData) {
        $customerName = $customerData['customer_name'];
        $customerInvoices = $customerData['invoices'];

        // Build email body with header, table, and footer
        $emailBody = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Reminder</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { background-color: #007bff; padding: 20px; text-align: center; color: #ffffff; }
                .header img { max-width: 150px; height: auto;background-color: #fff; }
                .header h1 { margin: 10px 0; font-size: 24px; }
                .content { padding: 20px; }
                .content p { line-height: 1.6; color: #333333; }
                .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .invoice-table th, .invoice-table td { border: 1px solid #dddddd; padding: 12px; text-align: left; }
                .invoice-table th { background-color: #007bff; color: #ffffff; font-weight: bold; }
                .invoice-table tr:nth-child(even) { background-color: #f9f9f9; }
                .invoice-table tr:hover { background-color: #f1f1f1; }
                .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #666666; }
                .footer a { color: #007bff; text-decoration: none; margin: 0 10px; }
                .footer img { width: 24px; height: 24px; vertical-align: middle; }
                .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                @media only screen and (max-width: 600px) {
                    .container { width: 100%; margin: 10px; }
                    .header img { max-width: 120px; }
                    .header h1 { font-size: 20px; }
                    .invoice-table th, .invoice-table td { font-size: 14px; padding: 8px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Header with Logo -->
                <div class="header">
                    <img src="https://vibrantick.in/assets/images/logo/footer.png" alt="Vibrantick InfoTech Solution Logo">
                    <h1>Payment Reminder</h1>
                </div>
                <!-- Content -->
                <div class="content">
                    <p>Dear ' . htmlspecialchars($customerName) . ',</p>
                    <p>We hope this message finds you well. The following invoice(s) are overdue. Kindly make the payment at your earliest convenience to avoid any service interruptions.</p>
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
    ';

        // Add invoice rows
        foreach ($customerInvoices as $invoice) {
            $emailBody .= '
                            <tr>
                                <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                                <td>' . htmlspecialchars($invoice['due_date']) . '</td>
                                <td>Rs: ' . number_format($invoice['amount'], 2) . '</td>
                                <td>' . htmlspecialchars($invoice['tax_rate']) . '</td>
                                <td>' . htmlspecialchars($invoice['discount']) . '</td>
                                <td>Rs: ' . number_format($invoice['total_amount'], 2) . '</td>
                            </tr>
        ';
        }

        $emailBody .= '
                        </tbody>
                    </table>
                    <!-- Call to Action -->
                    <p>Please settle the outstanding amount at your earliest convenience. For any questions or assistance, contact our support team at <a href="mailto:support@vibrantick.in">support@vibrantick.org</a> or call <a href="tel:+919870443528">+91-9870443528</a>.</p>
                    <p>Thank you for your prompt attention to this matter.</p>
                    <p>Best regards,<br>Vibrantick InfoTech Solution Team</p>
                </div>
                <!-- Footer -->
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' Vibrantick InfoTech Solution. All rights reserved.</p>
                    <p>Vibrantick InfoTech Solution | D-185, Phase 8B, Sector 74, SAS Nagar | <a href="mailto:support@vibrantick.org">support@vibrantick.org</a></p>
                    <p>
                        <a href="https://www.linkedin.com/company/vibrantick-infotech-solutions/posts/?feedView=all" target="_blank">
                            <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn">
                        </a>
                    <a href="https://www.instagram.com/vibrantickinfotech/" target="_blank">
                        <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram">
                    </a>
                        <a href="https://www.facebook.com/vibranticksolutions/" target="_blank">
                            <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook">
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>
    ';

        // Set email details
        $mail->clearAddresses();
        $mail->addAddress($customerEmail, $customerName);
        $mail->Subject = 'Payment Reminder: Overdue Invoices';
        $mail->Body = $emailBody;
        $mail->AltBody = "Dear {$customerName},\n\nThe following invoice(s) are overdue:\n\n" .
            implode("\n", array_map(function ($invoice) {
                return "Invoice: {$invoice['invoice_number']}, Due: {$invoice['due_date']}, Total: ₹" . number_format($invoice['total_amount'], 2);
            }, $customerInvoices)) .
            "\n\nPlease contact support@vibrantick.in for assistance.\n\nThank you,\nVibrantick InfoTech Solution";

        // Send email
        if ($mail->send()) {
            echo "Successfully sent reminder to {$customerEmail}\n";

            // Update reminder_count for each invoice
            foreach ($customerInvoices as $invoice) {
                $stmtUpdate->bind_param('i', $invoice['invoice_id']);
                if (!$stmtUpdate->execute()) {
                    echo "Failed to update reminder_count for invoice {$invoice['invoice_id']}\n";
                }
            }
        } else {
            echo "Failed to send reminder to {$customerEmail}: " . $mail->ErrorInfo . "\n";
        }
    }

    $stmtUpdate->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    $db->close();
}
?>