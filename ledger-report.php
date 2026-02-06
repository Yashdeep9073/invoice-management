<?php
session_start();

require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';
require './utility/formatDateTime.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}



$createdBy = base64_decode($_SESSION['admin_id']);

try {

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1 ;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetch = $db->prepare("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1");
    $stmtFetch->execute();
    $emailSettingData = $stmtFetch->get_result()->fetch_assoc();

    // === Email Settings Fallbacks ===
    $host = $emailSettingData['email_host'] ?? getenv("SMTP_HOST");
    $userName = $emailSettingData['email_address'] ?? getenv('SMTP_USER_NAME');
    $password = $emailSettingData['email_password'] ?? getenv('SMTP_PASSCODE');
    $port = $emailSettingData['email_port'] ?? getenv('SMTP_PORT');
    $fromTitle = $emailSettingData['email_from_title'] ?? "Vibrantick InfoTech Solution";
    $logoUrl = getenv("BASE_URL") . $emailSettingData['logo_url'] ?? 'https://vibrantick.in/assets/images/logo/footer.png ';

    $supportEmail = $emailSettingData['support_email'] ?? 'support@vibrantick.org';
    $phone = $emailSettingData['phone'] ?? '+919870443528';
    $address1 = $emailSettingData['address_line1'] ?? 'Vibrantick InfoTech Solution | D-185, Phase 8B, Sector 74, SAS Nagar';
    $linkedin = $emailSettingData['linkedin_url'] ?? 'https://www.linkedin.com/company/vibrantick-infotech-solutions/posts/?feedView=all';
    $instagram = $emailSettingData['ig_url'] ?? ' https://www.instagram.com/vibrantickinfotech/ ';
    $facebook = $emailSettingData['fb_url'] ?? 'https://www.facebook.com/vibranticksolutions/ ';
    $currentYear = date("Y");

    // Also fetch customers for the filter UI
    $stmtFetchCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1");
    $stmtFetchCustomers->execute();
    $customers = $stmtFetchCustomers->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtFetchCustomers->close();


    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Define the expected query parameters
        $params = [
            'from' => isset($_GET['from']) ? $_GET['from'] : '',
            'to' => isset($_GET['to']) ? $_GET['to'] : '',
        ];

        // Check if at least one parameter is present (non-empty)
        $hasParams = false;
        foreach ($params as $value) {
            if ($value !== '') {
                $hasParams = true;
                break;
            }
        }

        if ($hasParams) {

            // ----------------------------
            // Decode & validate inputs
            // ----------------------------

            $startDate = !empty($params['from'])
                ? date('Y-m-d', strtotime($params['from']))
                : null;

            $endDate = !empty($params['to'])
                ? date('Y-m-d', strtotime($params['to']))
                : null;

            // ----------------------------
            // Base query
            // ----------------------------
            $query = "
        SELECT
            invoice.invoice_id,
            invoice.invoice_number,
            ledger_transactions.*,
            invoice.status AS invoiceStatus,
            customer.customer_id,
            customer.customer_name,
            admin.admin_username,
            tax.tax_rate
        FROM ledger_transactions
        INNER JOIN customer ON customer.customer_id = ledger_transactions.customer_id
        LEFT JOIN invoice  ON invoice.invoice_id = ledger_transactions.invoice_id
        LEFT JOIN admin     ON admin.admin_id = invoice.created_by
        LEFT JOIN tax      ON tax.tax_id = invoice.tax
        WHERE 1 = 1
    ";

            // ----------------------------
            // Dynamic filters
            // ----------------------------
            $conditions = [];
            $paramsToBind = [];
            $types = "";

            if ($startDate) {
                $conditions[] = "ledger_transactions.created_at >= ?";
                $paramsToBind[] = $startDate . " 00:00:00";
                $types .= "s";
            }

            if ($endDate) {
                $conditions[] = "ledger_transactions.created_at <= ?";
                $paramsToBind[] = $endDate . " 23:59:59";
                $types .= "s";
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            $query .= " ORDER BY ledger_transactions.created_at ASC";

            // ----------------------------
            // Prepare & execute
            // ----------------------------
            $stmtFetchLedgerTransaction = $db->prepare($query);

            if ($stmtFetchLedgerTransaction === false) {
                $_SESSION['error'] = 'Query preparation failed';
            } else {

                if (!empty($paramsToBind)) {
                    $stmtFetchLedgerTransaction->bind_param($types, ...$paramsToBind);
                }

                if ($stmtFetchLedgerTransaction->execute()) {
                    $ledgerTransactions = $stmtFetchLedgerTransaction->get_result();
                } else {
                    $_SESSION['error'] = 'Error fetching filtered ledger transactions';
                }

                $stmtFetchLedgerTransaction->close();
            }
        } else {
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
            ORDER BY ledger_transactions.ledger_id ASC;

                ");
            // $stmtFetchLedgerTransaction->bind_param('i', $invoiceId);
            if ($stmtFetchLedgerTransaction->execute()) {
                $ledgerTransactions = $stmtFetchLedgerTransaction->get_result();
            } else {
                $_SESSION['error'] = 'Error for fetching customers';
            }
        }
    }




} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {


        $db->begin_transaction();

        // Required fields
        $requiredFields = ['transaction_type', 'payment_method', 'amount', 'customer_id'];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }



        // Sanitize inputs
        $transactionType = strtoupper(trim($_POST['transaction_type']));
        $paymentMethod = strtoupper(trim($_POST['payment_method']));
        $amount = (float) $_POST['amount'];
        $customerId = (int) $_POST['customer_id'];
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





?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="POS - Bootstrap Admin Template">
    <meta name="keywords"
        content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
    <meta name="author" content="Dreamguys - Bootstrap Admin Template">
    <meta name="robots" content="noindex, nofollow">
    <title>Ledger Report</title>

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

    <div class="main-wrapper">
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
                            <h4>Ledger Report <?= $singleCustomer['customer_name'] ?? "" ?></h4>
                            <h6>Manage Customer's Ledger Transaction</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportToPDF(`ledger_transaction`)"
                                data-bs-placement="top" title="Pdf"><img src="assets/img/icons/pdf.svg" alt="img" /></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportToExcel(`ledger_transaction`)"
                                data-bs-placement="top" title="Excel"><img src="assets/img/icons/excel.svg"
                                    alt="img" /></a>
                        </li>

                        <li>
                            <a href="<?= getenv("BASE_URL") . "ledger-report" ?>" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Refresh"><i data-feather="rotate-ccw"
                                    class="feather-rotate-ccw"></i></a>
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

                <div class="card table-list-card">
                    <div class="card-body">
                        <div class="table-top">
                            <div class="search-set">
                                <div class="search-input">
                                    <a href="" class="btn btn-searchset"><i data-feather="search"
                                            class="feather-search"></i></a>
                                </div>
                            </div>
                            <div class="search-path">
                                <div class="d-flex align-items-center">
                                    <a class="btn btn-filter" id="filter_search">
                                        <i data-feather="filter" class="filter-icon"></i>
                                        <span><img src="assets/img/icons/closes.svg" alt="img" /></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card" id="filter_inputs">
                            <div class="card-body pb-0">
                                <div class="row">

                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <div class="position-relative daterange-wraper">
                                                <input type="date" class="form-control" name="from">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <div class="position-relative daterange-wraper">
                                                <input type="date" class="form-control" name="to">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <a class="btn btn-filters ms-auto">
                                                <i data-feather="search" class="feather-search"></i>
                                                Search
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="table-responsive">
                            <table id="myTable" class="table  datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all">
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Ledger Id</th>
                                        <th>Customer Name</th>
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
                                            <td><?php echo $count ?></td>
                                            <td>
                                                <a class="text-primary" target="_blank"
                                                    href="<?= getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($transaction['customer_id']) ?>">
                                                    <?= $transaction['customer_name'] ?>
                                                </a>

                                            </td>
                                            <td>
                                                <?php echo formatDateTime($transaction['transaction_date'], $localizationSettings); ?>
                                            </td>
                                            <td>
                                                <?= $transaction['transaction_type'] ?>
                                            </td>
                                            <td>
                                                <?= $transaction['payment_method'] ?>
                                            </td>

                                            <td><?php echo formatDateTime($transaction['created_at'], $localizationSettings); ?>
                                            </td>
                                            <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $transaction['debit_amount']; ?>
                                            <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $transaction['credit_amount']; ?>
                                            </td>
                                            <td> <?php if ($transaction['transaction_type'] == 'PAYMENT') { ?> <span
                                                        class="badge badge-lg bg-success">Paid</span>
                                                <?php } elseif ($transaction['transaction_type'] == 'REFUND') { ?> <span
                                                        class="badge badge-lg bg-danger">Cancelled</span>
                                                <?php } elseif ($transaction['transaction_type'] == 'ADJUSTMENT') { ?> <span
                                                        class="badge badge-lg bg-warning">Pending</span>
                                                <?php } elseif ($transaction['transaction_type'] == 'INVOICE') { ?> <span
                                                        class="badge badge-lg bg-primary">Invoice</span> <?php } ?>
                                            </td>



                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
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
                                        <td colspan="6"></td>
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
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>

                            </table>
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
                                <div class="mb-3 add-product">
                                    <label class="form-label">Customer</label>
                                    <select class="select2 form-select" name="customer_id">
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer) { ?>
                                            <option value="<?php echo $customer['customer_id']; ?>">
                                                <?php echo $customer['customer_name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
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
                                    <label class="form-label">Payment Method</label>
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
                                <div class="mb-3 add-product">
                                    <label class="form-label">Customer</label>
                                    <select class="select2 form-select" name="edit_customer_id">
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer) { ?>
                                            <option value="<?php echo $customer['customer_id']; ?>">
                                                <?php echo $customer['customer_name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
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

    <script src="assets/js/feather.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/bootstrap.bundle.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>


    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>


    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/script.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/custom-select2.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="36113e2a9ce2b6f627c18ab9-|49" defer=""></script>
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






            $(document).on("click", ".row .col-lg-3 .input-blocks .btn-filters", function (e) {
                e.preventDefault();

                // Get query parameters
                const params = new URLSearchParams(window.location.search);

                // Fetch values
                const id = params.get("id");
                const uid = params.get("uid");
                let fromDate = $(".row .col-lg-3 .input-blocks .daterange-wraper input[name='from']").val();
                let toDate = $(".row .col-lg-3 .input-blocks .daterange-wraper input[name='to']").val();



                if (!fromDate) {
                    notyf.error("Please select from date");
                    return;
                }
                if (!toDate) {
                    notyf.error("Please select to date");
                    return;
                }


                window.location.href = `ledger-report?from=${fromDate}&to=${toDate}`;
            });


            $(document).on("submit", ".create-payment-form", async function (e) {
                e.preventDefault();

                let transactionType = $('select[name="transaction_type"]').val();
                let paymentMethod = $('select[name="payment_method"]').val();
                let amount = $('input[name="amount"]').val().trim();
                let customerId = $('select[name="customer_id"]').val();
                let remark = $('textarea[name="remark"]').val().trim();

                const transactionTypeRegex = /^(PAYMENT|REFUND|ADJUSTMENT)$/;
                const paymentMethodRegex = /^(CASH|CREDIT_CARD|DEBIT_CARD|NET_BANKING|PAYPAL|OTHER)$/;
                const amountRegex = /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/;



                // Required check
                if (!transactionType || !paymentMethod || !amount || !customerId) {
                    notyf.error("All fields are required.");
                    return;
                }

                // Required check
                if (!customerId) {
                    notyf.error("Please Select Customer.");
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
                    customer_id: customerId,
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
        });
    </script>

</body>

</html>