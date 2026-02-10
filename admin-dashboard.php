<?php
ob_start();
session_start();
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';
require './utility/formatDateTime.php';


try {

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1  ");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


    // echo "<pre>";
    // print_r($localizationSettings);
    // exit;

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtNumber = $db->prepare("SELECT COUNT(*) AS total_invoices FROM invoice WHERE is_active = 1 AND status IN ('PAID', 'PENDING');");
    $stmtNumber->execute();
    $totalNumberInvoice = $stmtNumber->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtTotalInvoice = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_payment
    FROM invoice
    WHERE is_active = 1
    AND status IN ('PAID', 'PENDING')
    ");
    $stmtTotalInvoice->execute();
    $totalAmount = $stmtTotalInvoice->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtTotalPaidAmount = $db->prepare('SELECT 
    SUM(credit_amount) AS total_received
        FROM ledger_transactions
        WHERE transaction_type = "PAYMENT";
    ');
    $stmtTotalPaidAmount->execute();
    $totalPaidAmount = $stmtTotalPaidAmount->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtTotalDueAmount = $db->prepare('SELECT 
        (
            SELECT COALESCE(SUM(total_amount),0)
            FROM invoice
            WHERE is_active = 1
        ) 
        -
        (
            SELECT COALESCE(SUM(credit_amount),0)
            FROM ledger_transactions
            WHERE transaction_type = "PAYMENT"
        ) 
    AS total_due;
    ');
    $stmtTotalDueAmount->execute();
    $totalDueAmount = $stmtTotalDueAmount->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtInvoiceCount = $db->prepare('  SELECT 
            status, 
            COUNT(*) AS count 
        FROM 
            invoice 
        WHERE 
            is_active = 1 
        GROUP BY 
            status');

    if (!$stmtInvoiceCount->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $invoiceCounts = $stmtInvoiceCount->get_result()->fetch_all(MYSQLI_ASSOC);

    $statusCounts = [
        'PAID' => 0,
        'PENDING' => 0,
        'CANCELLED' => 0,
        'REFUNDED' => 0
    ];

    foreach ($invoiceCounts as $row) {
        $status = $row['status'];
        $count = $row['count'];

        // Update the count for the corresponding status
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = $count;
        }
    }

    $stmtFetchInvoices = $db->prepare("SELECT 
        invoice.*,
        customer.customer_id,
        customer.customer_name,
        admin.admin_username
        FROM invoice 
        INNER JOIN customer ON customer.customer_id = invoice.customer_id
        LEFT JOIN admin ON admin.admin_id = invoice.created_by 
        WHERE invoice.is_active = 1
        ORDER BY invoice.created_at DESC
        LIMIT 10;
        ");

    if ($stmtFetchInvoices->execute()) {
        $invoices = $stmtFetchInvoices->get_result();

        // echo "<pre>";
        // print_r($invoices->fetch_all());
        // exit;
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    // header("Location: admin-dashboard.php");
    header("Location: " . getenv("BASE_URL") . "dashboard");

    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoiceCount'])) {

    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    $invoiceAmounts = array_fill(0, 12, 0);
    $paidAmounts = array_fill(0, 12, 0);
    $pendingAmounts = array_fill(0, 12, 0);
    $cancelledAmounts = array_fill(0, 12, 0);

    // INVOICE AMOUNT (LEDGER: INVOICE) 
    $stmtInvoice = $db->prepare("
        SELECT 
            MONTH(transaction_date) AS month_number,
            SUM(debit_amount) AS invoice_amount
        FROM ledger_transactions
        WHERE transaction_type = 'INVOICE'
          AND YEAR(transaction_date) = YEAR(CURDATE())
        GROUP BY MONTH(transaction_date)
    ");
    $stmtInvoice->execute();
    $resInvoice = $stmtInvoice->get_result();

    while ($row = $resInvoice->fetch_assoc()) {
        $idx = (int) $row['month_number'] - 1;
        $invoiceAmounts[$idx] = (float) $row['invoice_amount'];
    }

    // PAID AMOUNT (LEDGER: PAYMENT)
    $stmtPaid = $db->prepare("
        SELECT 
            MONTH(transaction_date) AS month_number,
            SUM(credit_amount) AS paid_amount
        FROM ledger_transactions
        WHERE transaction_type = 'PAYMENT'
          AND YEAR(transaction_date) = YEAR(CURDATE())
        GROUP BY MONTH(transaction_date)
    ");
    $stmtPaid->execute();
    $resPaid = $stmtPaid->get_result();

    while ($row = $resPaid->fetch_assoc()) {
        $idx = (int) $row['month_number'] - 1;
        $paidAmounts[$idx] = (float) $row['paid_amount'];
    }

    // CANCELLED AMOUNT (INVOICE TABLE)
    $stmtCancelled = $db->prepare("
        SELECT 
            MONTH(created_at) AS month_number,
            SUM(total_amount) AS cancelled_amount
        FROM invoice
        WHERE status = 'CANCELLED'
          AND is_active = 1
          AND YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
    ");
    $stmtCancelled->execute();
    $resCancelled = $stmtCancelled->get_result();

    while ($row = $resCancelled->fetch_assoc()) {
        $idx = (int) $row['month_number'] - 1;
        $cancelledAmounts[$idx] = (float) $row['cancelled_amount'];
    }

    // ---------- 4. CALCULATE PENDING ----------
    for ($i = 0; $i < 12; $i++) {
        $pendingAmounts[$i] = max(0, $invoiceAmounts[$i] - $paidAmounts[$i]);
    }

    // ---------- RESPONSE ----------
    echo json_encode([
        'months' => $months,
        'invoiceAmounts' => [
            'Paid' => $paidAmounts,
            'Pending' => $pendingAmounts,
            'Cancelled' => $cancelledAmounts
        ]
    ]);
    exit;
}

ob_end_flush();
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
    <title>Dashboard</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

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
                <div class="row">

                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count">
                            <div class="dash-counts">
                                <h4><?php echo $totalNumberInvoice['total_invoices'] ?></h4>
                                <h5><a class="text-white" href="manage-invoice.php">Invoices</a></h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="file"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das1">
                            <div class="dash-counts">
                                <h4>
                                    <?php
                                    $currency = isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$";
                                    $totalAmount = !empty($totalAmount['total_payment']) ? $totalAmount['total_payment'] : 0;
                                    echo $currency . " " . $totalAmount;
                                    ?>
                                </h4>
                                <h5><a class="text-white" href="reports.php">Total Payment</a></h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="credit-card"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das2">
                            <div class="dash-counts">

                                <h4>
                                    <?php
                                    $currency = isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$";
                                    $totalPaidAmount = !empty($totalPaidAmount['total_received']) ? $totalPaidAmount['total_received'] : 0;
                                    echo $currency . " " . $totalPaidAmount;
                                    ?>
                                </h4>

                                <h5><a class="text-white" href="reports.php">Received</a></h5>
                            </div>
                            <div class="dash-imgs">
                                <img src="assets/img/icons/file-text-icon-01.svg" class="img-fluid" alt="icon">
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das3">
                            <div class="dash-counts">
                                <h4>
                                    <?php
                                    $currency = isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$";
                                    $dueAmount = !empty($totalDueAmount['total_due']) ? $totalDueAmount['total_due'] : 0;
                                    echo $currency . " " . $dueAmount;
                                    ?>
                                </h4>

                                <h5><a class="text-white" href="reports.php">Due Payment</a></h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="file"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Income Chart</h5>
                            </div>
                            <div class="card-body">
                                <div id="s-line-area" class="chart-set"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Invoice Chart</h5>
                            </div>
                            <div class="card-body">
                                <div id="donut-chart" class="chart-set"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Recent Invoice</h4>
                        <div class="view-all-link">
                            <a href="manage-invoice.php" class="view-all d-flex align-items-center">
                                View All<span class="ps-2 d-flex align-items-center"><i data-feather="arrow-right"
                                        class="feather-16"></i></span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive dataview">
                            <table class="table dashboard-expired-products">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all" />
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Invoice Number</th>
                                        <th>Customer</th>
                                        <th>Due Date</th>
                                        <th>Created Date</th>
                                        <th class="no-sort">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices->fetch_all(MYSQLI_ASSOC) as $invoice) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" />
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td class="ref-number"><?php echo $invoice['invoice_number'] ?></td>
                                            <td><a class="text-primary"
                                                    href="view-customer-report.php?id=<?= base64_encode($invoice['customer_id']) ?>"><?php echo $invoice['customer_name'] ?></a>
                                            </td>
                                            <td><?php echo formatDateTime($invoice['due_date'], $localizationSettings); ?>
                                            </td>
                                            <td><?php echo formatDateTime($invoice['created_at'], $localizationSettings); ?>
                                            </td>
                                            <td class="text-primary">
                                                <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $invoice['total_amount'] ?>
                                            </td>
                                            <td>
                                                <?php if ($invoice['status'] == 'PAID') { ?>
                                                    <span class="badge badge-lg bg-success">Paid</span>
                                                <?php } elseif ($invoice['status'] == 'CANCELLED') { ?>
                                                    <span class="badge badge-lg bg-danger">Cancelled</span>
                                                <?php } elseif ($invoice['status'] == 'PENDING') { ?>
                                                    <span class="badge badge-lg bg-warning">Pending</span>
                                                <?php } elseif ($invoice['status'] == 'REFUNDED') { ?>
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


    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="c72afee7c2db3f6e0032cd13-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="c72afee7c2db3f6e0032cd13-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="c72afee7c2db3f6e0032cd13-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="c72afee7c2db3f6e0032cd13-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="c72afee7c2db3f6e0032cd13-text/javascript"></script>

    <script src="assets/js/script.js" type="c72afee7c2db3f6e0032cd13-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="c72afee7c2db3f6e0032cd13-|49" defer></script>

    <script src="assets/plugins/morris/raphael-min.js" type="13f35ce1e288ff8d4014a5c5-text/javascript"></script>
    <script src="assets/plugins/morris/morris.min.js" type="13f35ce1e288ff8d4014a5c5-text/javascript"></script>
    <script src="assets/plugins/morris/chart-data.js" type="13f35ce1e288ff8d4014a5c5-text/javascript"></script>

    <script>
        // Donut Chart Configuration using jQuery
        $(document).ready(function () {


            let options = {
                series: [parseInt('<?php echo $statusCounts['PAID'] ?>'), parseInt('<?php echo $statusCounts['PENDING'] ?>'), parseInt('<?php echo $statusCounts['CANCELLED'] ?>'), parseInt('<?php echo $statusCounts['REFUNDED'] ?>')], // Example data for Paid, Pending, Cancelled, Refunded
                labels: ['Paid', 'Pending', 'Cancelled', 'Refunded'], // Labels for each segment
                chart: {
                    type: 'donut',
                },
                colors: ['#00E396', '#FFB020', '#FF4560', '#008FFB'], // Custom colors for each status

                responsive: [
                    {
                        breakpoint: 480,
                        options: {
                            chart: {
                                width: 200,
                            },
                            legend: {
                                position: 'bottom',
                            },
                        },
                    },
                ],
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return value + ' Invoices'; // Tooltip shows the number of invoices
                        }
                    }
                }
            };

            // Initialize and Render Donut Chart
            let chart = new ApexCharts($("#donut-chart")[0], options);
            chart.render();

            $.ajax({
                url: 'admin-dashboard.php',
                type: 'POST',
                data: { invoiceCount: 1 },
                success: function (response) {

                    let result = JSON.parse(response);

                    let options = {
                        series: [
                            { name: 'Paid', data: result.invoiceAmounts.Paid },
                            { name: 'Pending', data: result.invoiceAmounts.Pending },
                            { name: 'Cancelled', data: result.invoiceAmounts.Cancelled }
                        ],
                        chart: {
                            type: 'bar',
                            height: 360,
                            toolbar: { show: false }
                        },
                        colors: ['#00E396', '#FFB020', '#FF4560'],
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '55%',
                                borderRadius: 6
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        xaxis: {
                            categories: result.months
                        },
                        yaxis: {
                            title: {
                                text: 'Amount (<?= $localizationSettings["currency_symbol"] ?? "₹" ?>)'
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return '<?= $localizationSettings["currency_symbol"] ?? "₹" ?>' + val.toFixed(2);
                                }
                            }
                        }
                    };

                    let chart = new ApexCharts(
                        document.querySelector("#s-line-area"),
                        options
                    );
                    chart.render();
                },
                error: function (err) {
                    console.error('Chart AJAX Error:', err);
                }
            });
        });
    </script>

</body>

</html>