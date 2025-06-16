<?php

session_start();
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}
require "./database/config.php";



try {
    $stmtFetch = $db->prepare("SELECT * FROM customer");
    $stmtFetch->execute();
    $customers = $stmtFetch->get_result();

    $stmtFetchInvoices = $db->prepare("SELECT * FROM invoice 
    INNER JOIN customer
    ON customer.customer_id = invoice.customer_id
    WHERE invoice.status = 'PENDING' AND invoice.is_active = 1
    ");
    if ($stmtFetchInvoices->execute()) {
        $invoices = $stmtFetchInvoices->get_result();
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = $e;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <meta name="author" content="" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Pending Payment</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css" />

    <link rel="stylesheet" href="assets/plugins/daterangepicker/daterangepicker.css" />

    <link rel="stylesheet" href="assets/css/animate.css" />

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css" />

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css" />

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css" />
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css" />

    <link rel="stylesheet" href="assets/css/style.css" />

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
    <div id="global-loader">
        <div class="whirly-loader"></div>
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
                <div class="page-header justify-content-between">
                    <div class="page-title">
                        <h4>Payment Report</h4>
                        <h6>Manage your Pending Payment</h6>
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
                        </div>

                        <div class="table-responsive">
                            <table id="myTable" class="table datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all" />
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Due Date</th>
                                        <th>Invoice No.</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th class="no-sort text-center">Action</th>
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
                                            <td><?php $date = new DateTime($invoice['due_date']);
                                            echo $date->format('d M Y') ?></td>
                                            <td class="ref-number" c><?php echo $invoice['invoice_number'] ?></td>
                                            <td>
                                                <?php echo $invoice['customer_name'] ?>
                                            </td>
                                            <td>
                                                ₹<?php echo $invoice['total_amount'] ?>
                                            </td>
                                            <td class="payment-info">
                                                <?php if ($invoice['payment_method'] == 'CREDIT_CARD') { ?>
                                                    <span class="badge badge-lg bg-success">Credit Card</span>
                                                <?php } elseif ($invoice['payment_method'] == 'DEBIT_CARD') { ?>
                                                    <span class="badge badge-lg bg-success">Debit Card</span>
                                                <?php } elseif ($invoice['payment_method'] == 'CASH') { ?>
                                                    <span class="badge badge-lg bg-success">Cash</span>
                                                <?php } elseif ($invoice['payment_method'] == 'NET_BANKING') { ?>
                                                    <span class="badge badge-lg bg-primary">Net Banking</span>
                                                <?php } elseif ($invoice['payment_method'] == 'PAYPAL') { ?>
                                                    <span class="badge badge-lg bg-primary">Paypal</span>
                                                <?php } elseif ($invoice['payment_method'] == 'OTHER') { ?>
                                                    <span class="badge badge-lg bg-warning">Other</span>
                                                <?php } ?>
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
                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
                                                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a target="_blank"
                                                            href="view-invoice.php?id=<?php echo base64_encode($invoice['invoice_id']) ?>"
                                                            class="editStatus dropdown-item" data-admin-id=""><i
                                                                data-feather="eye" class="info-img"></i>Show
                                                            Detail</a>
                                                    </li>
                                                </ul>
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


    <script src="assets/js/jquery-3.7.1.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/feather.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/js/script.js" type="80deaed41ddeb674d2936cb7-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="80deaed41ddeb674d2936cb7-|49" defer=""></script>

    <script src="assets/js/custom.js"></script>
</body>

</html>