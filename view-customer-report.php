<?php

session_start();
require "./database/config.php";
require './utility/formatDateTime.php';

if (!isset($_SESSION["admin_id"]) || !isset($_GET['id'])) {
    header("location: index.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    try {

        $customerId = intval(base64_decode($_GET['id']));


        // all invoices 
        $stmtFetchAll = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.is_active = 1 AND customer.customer_id = ? ");

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

    $stmtBalance = $db->prepare("SELECT 
    customer_id,
    SUM(debit_amount) - SUM(credit_amount) AS balance
    FROM ledger_transactions
    WHERE customer_id = ?
    GROUP BY customer_id
    ");

    $stmtBalance->bind_param("i", $customerId);
    $stmtBalance->execute();
    $balanceResult = $stmtBalance->get_result();
    $balance = $balanceResult->fetch_array(MYSQLI_ASSOC);

    


} catch (\Throwable $th) {
    //throw $th;
    $_SESSION["error"] = $th->getMessage();
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
                                            <td><?= !empty($balance['balance']) ? $balance['balance'] : '' ?>
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
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="refunded-report-tab" data-bs-toggle="pill"
                                data-bs-target="#refunded-report" type="button" role="tab"
                                aria-controls="refunded-report" aria-selected="false">Refunded Invoices</button>
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
                                                        $pendingAmountSum += $allInvoice['total_amount'];
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

                                                                <?php if ($isAdmin || hasPermission('Ledger', $privileges, $roleData['0']['role_name'])): ?>

                                                                    <li>
                                                                        <a target="_blank"
                                                                            href="<?php echo getenv("BASE_URL") . "ledger-transaction?id=" . base64_encode($allInvoice['invoice_id']) . "&uid=" . base64_encode($allInvoice['customer_id']); ?>"
                                                                            class="dropdown-item mb-0"><i
                                                                                data-feather="clipboard"
                                                                                class="info-img"></i>Ledger</a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="6"></td>
                                                    <td><strong><span class="text-success">Total:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td><strong><span class="text-danger">Pending:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($pendingAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="1"></td>
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
                        <div class="tab-pane fade" id="refunded-report" role="tabpanel"
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