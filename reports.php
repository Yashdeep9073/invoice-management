<?php
ob_start();
session_start();
require './utility/env.php';
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}
require "./database/config.php";
require './utility/formatDateTime.php';

try {

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

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

            function buildInvoiceQuery($db, $status, $customerId, $startDate, $endDate)
            {
                $query = "
            SELECT *, invoice.status as paymentStatus 
            FROM invoice 
            INNER JOIN customer ON customer.customer_id = invoice.customer_id
            INNER JOIN tax ON tax.tax_id = invoice.tax
            WHERE invoice.status = ?
              AND invoice.is_active = 1
        ";

                $types = "s";
                $values = [$status];

                if ($customerId) {
                    $query .= " AND customer.customer_id = ?";
                    $types .= "i";
                    $values[] = $customerId;
                }

                if ($startDate && $endDate) {
                    $query .= " AND DATE(invoice.created_at) BETWEEN ? AND ?";
                    $types .= "ss";
                    $values[] = $startDate;
                    $values[] = $endDate;
                }

                $stmt = $db->prepare($query);
                if (!$stmt)
                    return false;

                if (!empty($values)) {
                    $stmt->bind_param($types, ...$values);
                }

                if ($stmt->execute()) {
                    return $stmt->get_result();
                }

                return [];
            }

            // Build query for all invoices (without status filter)
            function buildAllInvoicesQuery($db, $customerId, $startDate, $endDate)
            {
                $query = "
            SELECT *, invoice.status as paymentStatus 
            FROM invoice 
            INNER JOIN customer ON customer.customer_id = invoice.customer_id
            INNER JOIN tax ON tax.tax_id = invoice.tax
            WHERE invoice.is_active = 1
        ";

                $types = "";
                $values = [];

                if ($customerId) {
                    $query .= " AND customer.customer_id = ?";
                    $types .= "i";
                    $values[] = $customerId;
                }

                if ($startDate && $endDate) {
                    $query .= " AND DATE(invoice.created_at) BETWEEN ? AND ?";
                    $types .= "ss";
                    $values[] = $startDate;
                    $values[] = $endDate;
                }

                $stmt = $db->prepare($query);
                if (!$stmt)
                    return false;

                if (!empty($types)) {
                    $stmt->bind_param($types, ...$values);
                }

                if ($stmt->execute()) {
                    return $stmt->get_result();
                }

                return [];
            }

            // Fetch all invoices (without status filter)
            $allInvoices = buildAllInvoicesQuery($db, $customerId, $startDate, $endDate);
            // print_r($allInvoices);
            // exit;

            // Fetch all statuses
            $paidInvoices = buildInvoiceQuery($db, 'PAID', $customerId, $startDate, $endDate);
            $pendingInvoices = buildInvoiceQuery($db, 'PENDING', $customerId, $startDate, $endDate);
            $cancelledInvoices = buildInvoiceQuery($db, 'CANCELLED', $customerId, $startDate, $endDate);
            $refundedInvoices = buildInvoiceQuery($db, 'REFUNDED', $customerId, $startDate, $endDate);

            // Fetch customers
            $stmtFetchCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1");
            $stmtFetchCustomers->execute();
            $customers = $stmtFetchCustomers->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFetchCustomers->close();

        } else {

            // all invoices 
            $stmtFetchAll = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
            INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
            INNER JOIN tax
            ON tax.tax_id = invoice.tax
            WHERE invoice.is_active = 1 ");
            if ($stmtFetchAll->execute()) {
                $allInvoices = $stmtFetchAll->get_result();
            }

            // paid 
            $stmtFetchPaid = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
            INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
            LEFT JOIN tax
            ON tax.tax_id = invoice.tax
            WHERE invoice.status = 'PAID' AND invoice.is_active = 1  ");
            if ($stmtFetchPaid->execute()) {
                $paidInvoices = $stmtFetchPaid->get_result();
            }

            // pending
            $stmtFetchPending = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
            INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
            INNER JOIN tax
            ON tax.tax_id = invoice.tax
            WHERE invoice.status = 'PENDING' AND invoice.is_active = 1");
            if ($stmtFetchPending->execute()) {
                $pendingInvoices = $stmtFetchPending->get_result();
            }

            // cancelled
            $stmtFetchCancelled = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
            INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
            INNER JOIN tax
            ON tax.tax_id = invoice.tax
            WHERE invoice.status = 'CANCELLED' AND invoice.is_active = 1  ");
            if ($stmtFetchCancelled->execute()) {
                $cancelledInvoices = $stmtFetchCancelled->get_result();
            }

            // refunded
            $stmtFetchRefunded = $db->prepare("SELECT *,invoice.status as paymentStatus FROM invoice 
            INNER JOIN customer
            ON customer.customer_id = invoice.customer_id
            INNER JOIN tax
            ON tax.tax_id = invoice.tax
            WHERE invoice.status = 'REFUNDED' AND invoice.is_active = 1");
            if ($stmtFetchRefunded->execute()) {
                $refundedInvoices = $stmtFetchRefunded->get_result();
            }

            $stmtFetchCustomers = $db->prepare("
            SELECT 
            *
            FROM customer
            WHERE isActive = 1
            ");

            // Execute the query and fetch results
            if ($stmtFetchCustomers->execute()) {
                $customers = $stmtFetchCustomers->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $customers = []; // Return an empty array if execution fails
            }

            // Close the statement
            $stmtFetchCustomers->close();
        }
    }

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1 ;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
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

ob_end_clean();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <meta name="robots" content="noindex, nofollow">
    <title>Report</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/plugins/daterangepicker/daterangepicker.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

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

    <!-- copy to clipboard -->
    <script src="https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js"></script>


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
                <div class="page-header justify-content-between">
                    <div class="page-title">
                        <h4>Reports</h4>
                        <h6>Manage your Invoice Reports</h6>
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
                            <a href="reports" data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i
                                    data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>

                <div class="card table-list-card">
                    <div class="card-body">
                        <div class="tabs-set">
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="all-report-tab" data-bs-toggle="tab"
                                        data-bs-target="#all-report" type="button" role="tab" aria-controls="all-report"
                                        aria-selected="true">All Invoices</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="paid-tab" data-bs-toggle="tab"
                                        data-bs-target="#paid-report" type="button" role="tab"
                                        aria-controls="paid-report" aria-selected="true">Paid Invoices</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="pending-tab" data-bs-toggle="tab"
                                        data-bs-target="#pending-report" type="button" role="tab"
                                        aria-controls="pending-report" aria-selected="false">Pending Invoices</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab"
                                        data-bs-target="#cancelled-report" type="button" role="tab"
                                        aria-controls="cancelled-report" aria-selected="false">Cancelled
                                        Invoices</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="refunded-tab" data-bs-toggle="tab"
                                        data-bs-target="#refunded-report" type="button" role="tab"
                                        aria-controls="refunded-report" aria-selected="false">Refunded Invoices</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="all-report" role="tabpanel"
                                    aria-labelledby="all-report-tab">
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalAmountSum = 0; // Initialize total
                                                foreach ($allInvoices->fetch_all(MYSQLI_ASSOC) as $allInvoice) {
                                                    $totalAmountSum += $allInvoice['total_amount']; // Add to total
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

                                                            <div class="userimgname">
                                                                <div>
                                                                    <a class="text-primary"
                                                                        href="<?php echo getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($allInvoice['customer_id']) ?>"><?php echo $allInvoice['customer_name'] ?></a>
                                                                </div>
                                                            </div>


                                                        </td>
                                                        <td><a href="javascript:void(0);" class="view-note view-service"
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
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="7"></td>
                                                    <td><strong><span class="text-danger">Total:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="1"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="paid-report" role="tabpanel" aria-labelledby="paid-tab">
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
                                        <table id="paidTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <label class="checkboxs">
                                                            <input type="checkbox">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Customer</th>
                                                    <th>Due Date</th>
                                                    <th>Tran No</th>
                                                    <th>Invoice No</th>
                                                    <th>Total Amount</th>
                                                    <th>Payment Method</th>
                                                    <th>Discount</th>
                                                    <th>Tax Amount</th>
                                                    <th>Created At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalAmountSum = 0; // Initialize total
                                                foreach ($paidInvoices->fetch_all(MYSQLI_ASSOC) as $paidInvoice) {
                                                    $totalAmountSum += $paidInvoice['total_amount']; // Add to total
                                                
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $paidInvoice['invoice_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td>

                                                            <div class="userimgname">
                                                                <div>
                                                                    <a class="text-primary"
                                                                        href="<?php echo getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($paidInvoice['customer_id']) ?>"><?php echo $paidInvoice['customer_name'] ?></a>
                                                                </div>
                                                            </div>

                                                        </td>
                                                        <td><?php echo formatDateTime($paidInvoice['updated_at'], $localizationSettings); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($paidInvoice['transaction_id']); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($paidInvoice['invoice_number']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($paidInvoice['total_amount']); ?>
                                                        </td>

                                                        <td>
                                                            <?php if ($paidInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($paidInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($paidInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($paidInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($paidInvoice['discount']); ?>%</td>
                                                        <td><?php echo htmlspecialchars($paidInvoice['tax_rate']); ?></td>
                                                        <td><?php echo formatDateTime($paidInvoice['created_at'], $localizationSettings); ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>

                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5"></td>
                                                    <td><strong><span class="text-danger">Total:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($totalAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="4"></td>
                                                </tr>
                                            </tfoot>

                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="pending-report" role="tabpanel"
                                    aria-labelledby="pending-tab">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>
                                        <div class="search-path">
                                            <div class="d-flex align-items-center">
                                                <a class="btn btn-filter" id="filter_search1">
                                                    <i data-feather="filter" class="filter-icon"></i>
                                                    <span><img src="assets/img/icons/closes.svg" alt="img" /></span>
                                                </a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="card" id="filter_inputs1">
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
                                        <table id="pendingTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <label class="checkboxs">
                                                            <input type="checkbox">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Customer</th>
                                                    <th>Due Date</th>
                                                    <th>Tran No</th>
                                                    <th>Invoice No</th>
                                                    <th>Total Amount</th>
                                                    <th>Payment Method</th>
                                                    <th>Discount</th>
                                                    <th>Tax Amount</th>
                                                    <th>Created At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $pendingAmountSum = 0; // Total pending amount
                                                foreach ($pendingInvoices->fetch_all(MYSQLI_ASSOC) as $pendingInvoice) {
                                                    $pendingAmountSum += $pendingInvoice['total_amount']; // Add each total amount
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $pendingInvoice['invoice_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <div class="userimgname">
                                                                <div>
                                                                    <a class="text-primary"
                                                                        href="<?php echo getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($pendingInvoice['customer_id']) ?>"><?php echo $pendingInvoice['customer_name'] ?></a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if (!empty($pendingInvoice['updated_at'])) {
                                                                echo formatDateTime($pendingInvoice['updated_at'], $localizationSettings);
                                                            } else {
                                                                echo "N/A"; // Or any default text
                                                            }
                                                            ?>
                                                        </td>

                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($pendingInvoice['transaction_id']); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($pendingInvoice['invoice_number']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($pendingInvoice['total_amount']); ?>
                                                        </td>

                                                        <td>
                                                            <?php if ($pendingInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($pendingInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($pendingInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($pendingInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo $pendingInvoice['discount']; ?>%</td>
                                                        <td><?php echo $pendingInvoice['tax_rate']; ?></td>
                                                        <td><?php $date = new DateTime($pendingInvoice['created_at']);
                                                        echo htmlspecialchars($date->format(isset($localizationSettings["date_format"]) ? $localizationSettings["date_format"] : "d M Y")); ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5"></td>
                                                    <td><strong><span class="text-danger">Total Pending:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($pendingAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="4"></td>
                                                </tr>
                                            </tfoot>

                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="cancelled-report" role="tabpanel"
                                    aria-labelledby="cancelled-tab">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>
                                        <div class="search-path">
                                            <div class="d-flex align-items-center">
                                                <a class="btn btn-filter" id="filter_search2">
                                                    <i data-feather="filter" class="filter-icon"></i>
                                                    <span><img src="assets/img/icons/closes.svg" alt="img" /></span>
                                                </a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="card" id="filter_inputs2">
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
                                        <table id="cancelledTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <label class="checkboxs">
                                                            <input type="checkbox">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Customer</th>
                                                    <th>Due Date</th>
                                                    <th>Tran No</th>
                                                    <th>Invoice No</th>
                                                    <th>Total Amount</th>
                                                    <th>Payment Method</th>
                                                    <th>Discount</th>
                                                    <th>Tax Amount</th>
                                                    <th>Created At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $cancelledAmountSum = 0;
                                                foreach ($cancelledInvoices->fetch_all(MYSQLI_ASSOC) as $cancelledInvoice) {
                                                    $cancelledAmountSum += $cancelledInvoice['total_amount'];

                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $cancelledInvoice['invoice_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td>

                                                            <div class="userimgname">
                                                                <div>
                                                                    <a class="text-primary"
                                                                        href="<?php echo getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($cancelledInvoice['customer_id']) ?>"><?php echo $cancelledInvoice['customer_name'] ?></a>
                                                                </div>
                                                            </div>

                                                        </td>
                                                        <td><?php echo formatDateTime($cancelledInvoice['updated_at'], $localizationSettings); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($cancelledInvoice['transaction_id']); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($cancelledInvoice['invoice_number']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($cancelledInvoice['total_amount']); ?>
                                                        </td>

                                                        <td>
                                                            <?php if ($cancelledInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($cancelledInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($cancelledInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($cancelledInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cancelledInvoice['discount']); ?>%
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cancelledInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo formatDateTime($cancelledInvoice['created_at'], $localizationSettings); ?>
                                                        </td>

                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5"></td>
                                                    <td><strong><span class="text-danger">Total Cancelled:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($cancelledAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="4"></td>
                                                </tr>
                                            </tfoot>

                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="refunded-report" role="tabpanel"
                                    aria-labelledby="refunded-tab">
                                    <div class="table-top">
                                        <div class="search-set">
                                            <div class="search-input">
                                                <a href="" class="btn btn-searchset"><i data-feather="search"
                                                        class="feather-search"></i></a>
                                            </div>
                                        </div>
                                        <div class="search-path">
                                            <div class="d-flex align-items-center">
                                                <a class="btn btn-filter" id="filter_search3">
                                                    <i data-feather="filter" class="filter-icon"></i>
                                                    <span><img src="assets/img/icons/closes.svg" alt="img" /></span>
                                                </a>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="card" id="filter_inputs3">
                                        <div class="card-body pb-0">
                                            <div class="row">
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <div class="input-blocks">
                                                        <select class="select" name="customerId">
                                                            <option>Choose Name</option>
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
                                        <table id="refundedTable" class="table datanew">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <label class="checkboxs">
                                                            <input type="checkbox">
                                                            <span class="checkmarks"></span>
                                                        </label>
                                                    </th>
                                                    <th>Customer</th>
                                                    <th>Due Date</th>
                                                    <th>Tran No</th>
                                                    <th>Invoice No</th>
                                                    <th>Total Amount</th>
                                                    <th>Payment Method</th>
                                                    <th>Discount</th>
                                                    <th>Tax Amount</th>
                                                    <th>Created At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $refundedAmountSum = 0;
                                                foreach ($refundedInvoices->fetch_all(MYSQLI_ASSOC) as $refundedInvoice) {
                                                    $refundedAmountSum += $refundedInvoice['total_amount'];
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <label class="checkboxs">
                                                                <input type="checkbox" name="invoiceIds"
                                                                    value="<?php echo $refundedInvoice['invoice_id'] ?>">
                                                                <span class="checkmarks"></span>
                                                            </label>
                                                        </td>
                                                        <td>

                                                            <div class="userimgname">
                                                                <div>
                                                                    <a class="text-primary"
                                                                        href="<?php echo getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($refundedInvoice['customer_id']) ?>"><?php echo $refundedInvoice['customer_name'] ?></a>
                                                                </div>
                                                            </div>

                                                        </td>
                                                        <td><?php echo formatDateTime($refundedInvoice['updated_at'], $localizationSettings); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($refundedInvoice['transaction_id']); ?>
                                                        </td>
                                                        <td class="ref-number">
                                                            <?php echo htmlspecialchars($refundedInvoice['invoice_number']); ?>
                                                        </td>
                                                        <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . htmlspecialchars($refundedInvoice['total_amount']); ?>
                                                        </td>

                                                        <td>
                                                            <?php if ($refundedInvoice['paymentStatus'] == 'PAID') { ?>
                                                                <span class="badge badge-lg bg-success">Paid</span>
                                                            <?php } elseif ($refundedInvoice['paymentStatus'] == 'CANCELLED') { ?>
                                                                <span class="badge badge-lg bg-danger">Cancelled</span>
                                                            <?php } elseif ($refundedInvoice['paymentStatus'] == 'PENDING') { ?>
                                                                <span class="badge badge-lg bg-warning">Pending</span>
                                                            <?php } elseif ($refundedInvoice['paymentStatus'] == 'REFUNDED') { ?>
                                                                <span class="badge badge-lg bg-primary">Refunded</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($refundedInvoice['discount']); ?>%
                                                        </td>
                                                        <td><?php echo htmlspecialchars($refundedInvoice['tax_rate']); ?>
                                                        </td>
                                                        <td><?php echo formatDateTime($refundedInvoice['created_at'], $localizationSettings); ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5"></td>
                                                    <td><strong><span class="text-danger">Total Refunded:
                                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . number_format($refundedAmountSum, 2); ?></span></strong>
                                                    </td>
                                                    <td colspan="4"></td>
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


    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="94853a405675f53c594300da-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="94853a405675f53c594300da-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="94853a405675f53c594300da-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="94853a405675f53c594300da-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="94853a405675f53c594300da-text/javascript"></script>
    <script src="assets/js/script.js" type="94853a405675f53c594300da-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="94853a405675f53c594300da-|49" defer=""></script>
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


            $(document).on('click', '.view-service', function (e) {
                e.preventDefault();

                let servicesIds = $(this).data('service-id');
                console.log(servicesIds);


                $.ajax({
                    url: 'reports.php',
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

            // Initialize Clipboard.js for .admin-email links
            let clipboard = new ClipboardJS(".admin-email", {
                text: function (trigger) {
                    // Copy the email from the link's text or href (mailto: part removed)
                    return (
                        $(trigger).text().trim() ||
                        $(trigger).attr("href").replace("mailto:", "")
                    );
                },
            });

            // Success feedback
            clipboard.on("success", function (e) {
                console.log("Copied:", e.text);
                notyf.success("Email copied to clipboard!");

                // Temporarily update the link's appearance
                let $link = $(e.trigger);
                $link.text("Copied!").addClass("copied");

                // Reset link after 2 seconds
                setTimeout(function () {
                    $link.text(e.text).removeClass("copied");
                }, 2000);

                e.clearSelection();
            });

            // Error feedback
            clipboard.on("error", function (e) {
                console.error("Error:", e.action);
                notyf.error("Failed to copy email.");

                // Temporarily update the link's appearance
                let $link = $(e.trigger);
                $link.text("Error!").addClass("error");

                // Reset link after 2 seconds
                setTimeout(function () {
                    $link
                        .text(e.text || $link.attr("href").replace("mailto:", ""))
                        .removeClass("error");
                }, 2000);
            });


            let today = new Date().toISOString().split('T')[0];
            $("input[name='from']").val(today);
            $("input[name='to']").val(today);

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
                console.log("Customer ID -", customerId);
                console.log("From Date -", fromDate);
                console.log("To Date -", toDate);
                window.location.href = `reports?customer=${customerId}&from=${fromDate}&to=${toDate}`;
            });

        });

    </script>
</body>

</html>