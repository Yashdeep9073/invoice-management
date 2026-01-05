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

// for card details
$invoiceId = intval(base64_decode($_GET['id'])) ?? null;
$customerId = intval(base64_decode($_GET['uid'])) ?? null;
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
    $stmtFetchSingleCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1 AND customer_id = ? ");
    $stmtFetchSingleCustomers->bind_param('i', $customerId);
    $stmtFetchSingleCustomers->execute();
    $singleCustomer = $stmtFetchSingleCustomers->get_result()->fetch_array(MYSQLI_ASSOC);
    $stmtFetchSingleCustomers->close();


    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Define the expected query parameters
        $params = [
            'customer' => isset($_GET['customer']) ? $_GET['customer'] : '',
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

            $customerId = $params['customer'] ?? null;
            $startDate = $params['from'] ?? null;
            $endDate = $params['to'] ?? null;

            // Clean and format dates
            $startDate = $startDate ? date('Y-m-d', strtotime($startDate)) : null;
            $endDate = $endDate ? date('Y-m-d', strtotime($endDate)) : null;

            // Prepare SQL with conditions
            $query = "SELECT 
            invoice.invoice_id,
            invoice.status as invoiceStatus,
            customer.customer_id,
            customer.customer_name,
            admin.admin_username,
            tax.tax_rate
            FROM invoice 
            INNER JOIN customer ON customer.customer_id = invoice.customer_id
            LEFT JOIN admin ON admin.admin_id = invoice.created_by 
             INNER JOIN tax ON tax.tax_id = invoice.tax
            WHERE invoice.is_active = 1
             
            ";

            $conditions = [];
            $paramsToBind = [];

            if ($customerId) {
                $conditions[] = "invoice.customer_id = ?";
                $paramsToBind[] = $customerId;
            }

            if ($startDate) {
                $conditions[] = "DATE(invoice.created_at) >= ?";
                $paramsToBind[] = $startDate;
            }

            if ($endDate) {
                $conditions[] = "DATE(invoice.created_at) <= ?";
                $paramsToBind[] = $endDate;
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            $stmtFetchInvoices = $db->prepare($query);

            if ($stmtFetchInvoices === false) {
                $_SESSION['error'] = 'Query preparation failed';
            } else {
                // Bind parameters dynamically
                if (!empty($paramsToBind)) {
                    $types = str_repeat("s", count($paramsToBind)); // all are strings
                    $stmtFetchInvoices->bind_param($types, ...$paramsToBind);
                }

                if ($stmtFetchInvoices->execute()) {
                    $invoices = $stmtFetchInvoices->get_result();
                } else {
                    $_SESSION['error'] = 'Error fetching filtered invoices';
                }

                $stmtFetchInvoices->close();
            }

            // Also fetch customers for the filter UI
            $stmtFetchCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1");
            $stmtFetchCustomers->execute();
            $customers = $stmtFetchCustomers->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFetchCustomers->close();
        } else {
            $stmtFetchLedgerTransaction = $db->prepare("SELECT
                invoice.invoice_id,
                invoice.invoice_number,
                ledger_transactions.*,
                invoice.status as invoiceStatus,
                customer.customer_id,
                customer.customer_name,
                admin.admin_username,
                tax.tax_rate
                FROM ledger_transactions
                INNER JOIN customer
                ON customer.customer_id =  ledger_transactions.customer_id
                INNER JOIN invoice
                ON invoice.invoice_id =  ledger_transactions.invoice_id
                LEFT JOIN admin 
                ON admin.admin_id = invoice.created_by
                INNER JOIN tax 
                ON tax.tax_id = invoice.tax
                ORDER BY invoice.invoice_id ASC
                ");
            if ($stmtFetchLedgerTransaction->execute()) {
                $ledgerTransactions = $stmtFetchLedgerTransaction->get_result();
            } else {
                $_SESSION['error'] = 'Error for fetching customers';
            }

            // Fetch customers
            $stmtFetchCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1");
            $stmtFetchCustomers->execute();
            $customers = $stmtFetchCustomers->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFetchCustomers->close();
        }
    }


    //     echo "<pre>";
    //     print_r($invoices->fetch_all(MYSQLI_ASSOC));
    // exit;

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Required fields
        $requiredFields = ['transaction_type', 'payment_method', 'amount'];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode([
                    'status' => 400,
                    'error' => "Missing required field: {$field}"
                ]);
                exit;
            }
        }

        // Sanitize
        $transactionType = filter_input(INPUT_POST, 'transaction_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $amountRaw = filter_input(
            INPUT_POST,
            'amount',
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION
        );
        $remarks = $_POST['remark'] ?? null;


        // Regex patterns (STRICT, ENUM-safe)
        $transactionTypeRegex = '/^(|PAYMENT|REFUND|ADJUSTMENT)$/';
        $paymentMethodRegex = '/^(CREDIT_CARD|DEBIT_CARD|CASH|NET_BANKING|PAYPAL|OTHER)$/';
        $amountRegex = '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'; // decimal OPTIONAL


        // Validate
        if (!preg_match($transactionTypeRegex, $transactionType)) {
            echo json_encode(['status' => 400, 'error' => 'Invalid transaction type']);
            exit;
        }

        if (!preg_match($paymentMethodRegex, $paymentMethod)) {
            echo json_encode(['status' => 400, 'error' => 'Invalid payment method']);
            exit;
        }

        if (!is_string($amountRaw) || !preg_match($amountRegex, $amountRaw)) {
            echo json_encode(['status' => 400, 'error' => 'Invalid amount']);
            exit;
        }

        $amount = (float) $amountRaw;

        // Debit / Credit logic
        $debitAmount = 0.00;
        $creditAmount = 0.00;

        switch ($transactionType) {
            case 'PAYMENT':
                $creditAmount = $amount;
                break;

            case 'REFUND':
            case 'ADJUSTMENT':
                $debitAmount = $amount;
                break;

            case 'INVOICE':
                $debitAmount = $amount;
                break;
        }

        // Insert Ledger Transaction
        $stmt = $db->prepare("
            INSERT INTO ledger_transactions
            (customer_id, invoice_id, transaction_type, payment_method, debit_amount, credit_amount, remarks, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'iissddsi',
            $customerId,
            $invoiceId,
            $transactionType,
            $paymentMethod,
            $debitAmount,
            $creditAmount,
            $remarks,
            $createdBy
        );

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 201,
                'message' => 'Ledger transaction created successfully'
            ]);
            exit;
        } else {
            throw new Exception('Database insert failed');
        }

    } catch (Exception $e) {
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
    <title>Ledger</title>

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
                            <a data-bs-toggle="tooltip" onclick="exportToPDF()" data-bs-placement="top" title="Pdf"><img
                                    src="assets/img/icons/pdf.svg" alt="img" /></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportToExcel()" data-bs-placement="top"
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
                                            <i data-feather="user" class="info-img"></i>
                                            <select class="select" name="customerId">
                                                <option value="">Choose Name</option>
                                                <?php foreach ($customers as $customer) { ?>
                                                    <option value="<?php echo $customer['customer_id'] ?>">
                                                        <?php echo $customer['customer_name'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

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
                                        <th>Transaction Date</th>
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

                                    foreach ($ledgerTransactions->fetch_all(MYSQLI_ASSOC) as $invoice) {
                                        $totalPaidAmount += $invoice['credit_amount'];
                                        $totalPendingAmount += $invoice['debit_amount'];
                                        ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" name="invoiceIds"
                                                        value="<?php echo $invoice['ledger_id'] ?>">
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td><?php echo $count ?></td>
                                            <td>
                                                <?php echo formatDateTime($invoice['transaction_date'], $localizationSettings); ?>
                                            </td>

                                            <td><?php echo formatDateTime($invoice['created_at'], $localizationSettings); ?>
                                            </td>
                                            <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $invoice['debit_amount']; ?>
                                            <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $invoice['credit_amount']; ?>
                                            </td>
                                            <td> <?php if ($invoice['transaction_type'] == 'PAYMENT') { ?> <span
                                                        class="badge badge-lg bg-success">Paid</span>
                                                <?php } elseif ($invoice['transaction_type'] == 'REFUND') { ?> <span
                                                        class="badge badge-lg bg-danger">Cancelled</span>
                                                <?php } elseif ($invoice['transaction_type'] == 'ADJUSTMENT') { ?> <span
                                                        class="badge badge-lg bg-warning">Pending</span>
                                                <?php } elseif ($invoice['transaction_type'] == 'INVOICE') { ?> <span
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
                                                                data-ledger-id="<?= $invoice['ledger_id'] ?>"
                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                data-payment-method="<?= $invoice['payment_method'] ?>"
                                                                data-debit-amount="<?= $invoice['debit_amount'] ?>"
                                                                data-credit-amount="<?= $invoice['credit_amount'] ?>"
                                                                data-remarks="<?= $invoice['remarks'] ?>"
                                                                data-created_at="<?= $invoice['created_at'] ?>"><i
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
                                        <td colspan="4"></td>
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

    <script src="assets/js/jquery.dataTables.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/script.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
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

                let ledgerId = $(this).data('ledger-id');
                let invoiceId = $(this).data('invoice-id');
                let paymentMethod = $(this).data('payment-method');
                let debitAmount = $(this).data('debit-amount');
                let creditAmount = $(this).data('credit-amount');
                let remarks = $(this).data('remarks');

                // Hidden fields
                $('#editLedgerId').val(ledgerId);

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
                let customerId = $(".input-blocks select[name='customerId']").val();
                let fromDate = $(".row .col-lg-3 .input-blocks .daterange-wraper input[name='from']").val();
                let toDate = $(".row .col-lg-3 .input-blocks .daterange-wraper input[name='to']").val();

                // Check if customerId is missing or not a number
                // if (!customerId || isNaN(customerId) || !Number.isInteger(Number(customerId))) {
                //     notyf.error("Please select a valid customer");
                //     return;
                // }
                if (!fromDate) {
                    notyf.error("Please select from date");
                    return;
                }
                if (!toDate) {
                    notyf.error("Please select to date");
                    return;
                }

                // Output
                // console.log("Customer ID -", customerId);
                // console.log("From Date -", fromDate);
                // console.log("To Date -", toDate);
                window.location.href = `manage-invoice.php?customer=${customerId}&from=${fromDate}&to=${toDate}`;
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
        });
    </script>

</body>

</html>