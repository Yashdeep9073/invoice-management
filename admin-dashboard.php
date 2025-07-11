<?php
ob_start();
session_start();
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';


try {

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


    // echo "<pre>";
    // print_r($localizationSettings);
    // exit;

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtNumber = $db->prepare("SELECT COUNT(*) AS total_invoices FROM invoice WHERE is_active = 1 AND status != 'CANCELLED';");
    $stmtNumber->execute();
    $totalNumberInvoice = $stmtNumber->get_result()->fetch_all(MYSQLI_ASSOC);


    $stmtTotalAmount = $db->prepare("SELECT SUM(total_amount) AS total_payment FROM invoice WHERE is_active = 1 AND status != 'CANCELLED'; ");
    $stmtTotalAmount->execute();
    $totalAmount = $stmtTotalAmount->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtTotalPaidAmount = $db->prepare('SELECT SUM(total_amount) AS total_paid_payment FROM invoice WHERE status = "PAID" AND is_active = 1');
    $stmtTotalPaidAmount->execute();
    $totalPaidAmount = $stmtTotalPaidAmount->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtTotalDueAmount = $db->prepare('SELECT SUM(total_amount) AS total_due_payment FROM invoice WHERE status = "PENDING" AND is_active = 1');
    $stmtTotalDueAmount->execute();
    $totalDueAmount = $stmtTotalDueAmount->get_result()->fetch_all(MYSQLI_ASSOC);

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
    header("Location: admin-dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceCount'])) {

    // Query to get invoice count by month and status
    $stmtInvoiceMonthCount = $db->prepare("
        SELECT 
            MONTH(created_at) AS month_number,
            MONTHNAME(created_at) AS month_name,
            status,
            COUNT(*) AS invoice_count,
            SUM(total_amount) AS total_sum
        FROM invoice
        WHERE status IN ('PAID', 'PENDING', 'CANCELLED') AND is_active = 1
        GROUP BY month_number, month_name, status
        ORDER BY month_number, FIELD(status, 'PAID', 'PENDING', 'CANCELLED');
    ");

    $stmtInvoiceMonthCount->execute();
    $result = $stmtInvoiceMonthCount->get_result();

    $totalInvoiceMonthCount = $result->fetch_all(MYSQLI_ASSOC);

    // Initialize arrays
    $invoiceCounts = [
        'PAID' => array_fill(1, 12, 0),
        'PENDING' => array_fill(1, 12, 0),
        'CANCELLED' => array_fill(1, 12, 0)
    ];

    $invoiceAmounts = [
        'PAID' => array_fill(1, 12, 0),
        'PENDING' => array_fill(1, 12, 0),
        'CANCELLED' => array_fill(1, 12, 0)
    ];

    foreach ($totalInvoiceMonthCount as $value) {
        $monthNumber = $value['month_number'];
        $status = $value['status'];

        $invoiceCounts[$status][$monthNumber] = $value['invoice_count'];
        $invoiceAmounts[$status][$monthNumber] = round((float) $value['total_sum'], 2);
    }

    // Prepare response
    $responseData = [
        'status' => true,
        'data' => [
            'months' => [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December'
            ],
            'invoiceCounts' => [
                'Paid' => array_values($invoiceCounts['PAID']),
                'Pending' => array_values($invoiceCounts['PENDING']),
                'Cancelled' => array_values($invoiceCounts['CANCELLED'])
            ],
            'invoiceAmounts' => [
                'Paid' => array_values($invoiceAmounts['PAID']),
                'Pending' => array_values($invoiceAmounts['PENDING']),
                'Cancelled' => array_values($invoiceAmounts['CANCELLED'])
            ]
        ]
    ];

    echo json_encode([
        'status' => true,
        'data' => $responseData['data']
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
    <div id="global-loader">
        <div class="whirly-loader"> </div>
    </div>

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
                                <h4><?php echo $totalNumberInvoice['0']['total_invoices'] ?></h4>
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
                                    <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $totalAmount[0]['total_payment'];
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
                                <h4> <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $totalPaidAmount['0']['total_paid_payment']; ?>
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
                                <h4> <?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $totalDueAmount['0']['total_due_payment']; ?>
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
                                            <td><a
                                                    href="view-customer-report.php?id=<?= base64_encode($invoice['customer_id']) ?>"><?php echo $invoice['customer_name'] ?></a>
                                            </td>
                                            <td><?php $date = new DateTime($invoice['due_date']);
                                            echo $date->format('d M Y') ?></td>
                                            <td><?php $date = new DateTime($invoice['created_at']);
                                            echo $date->format('d M Y') ?></td>
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
                    let result = JSON.parse(response).data;

                    // Extract data
                    let months = result.months;
                    let invoiceCounts = result.invoiceCounts;
                    let invoiceAmounts = result.invoiceAmounts;

                    // Bar chart for total amounts
                    let optionBar = {
                        series: [
                            {
                                name: 'Paid',
                                data: invoiceAmounts.Paid
                            },
                            {
                                name: 'Pending',
                                data: invoiceAmounts.Pending
                            },
                            {
                                name: 'Cancelled',
                                data: invoiceAmounts.Cancelled
                            }
                        ],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: true }
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '55%',
                                borderRadius: 5,
                                borderRadiusApplication: 'end'
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: months.map(month => month.substring(0, 3)), // Jan, Feb, etc.
                        },
                        yaxis: {
                            title: {
                                text: 'Total Amount (<?= (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") ?>)'
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return '<?= (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") ?>' + val.toFixed(2);
                                }
                            }
                        }
                    };

                    var chartBar = new ApexCharts(document.querySelector("#s-line-area"), optionBar);
                    chartBar.render();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                }
            });
        });
    </script>

</body>

</html>