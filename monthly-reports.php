<?php 
error_reporting(1);
session_start();

// Checking Session Value
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}

// Database Connection
require "./database/config.php";

// Retrieve Billing Info Data
$stmtProduct = $db->prepare("SELECT * FROM invoice");
$stmtProduct->execute();
$resultProduct = $stmtProduct->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<meta name="description" content="POS - Bootstrap Admin Template">
<meta name="keywords" content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
<meta name="author" content="Dreamguys - Bootstrap Admin Template">
<meta name="robots" content="noindex, nofollow">
<?php
    require "./database/config.php";

    // SQL query to fetch general settings and company logo
    $sql = "SELECT title FROM general_settings WHERE status = 1";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        // Loop through each testimonial
        while ($row = $result->fetch_assoc()) {
    ?>
    <title><?php echo $row['title']; ?></title>
    <?php
        }}
     ?>

<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">

<link rel="stylesheet" href="assets/css/bootstrap.min.css">

<link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

<link rel="stylesheet" href="assets/css/animate.css">

<link rel="stylesheet" href="assets/css/feather.css">

<link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

<link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css">

<link rel="stylesheet" href="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css">

<link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

<link rel="stylesheet" href="assets/css/style.css">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<!-- Buttons Extensions -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.copy.min.js"></script>

<!-- PDFMake and vfs_fonts for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>


<style>
    .btn {
    padding: 10px 15px;
    margin: 5px;
    border-radius: 4px;
    color: #fff;
    text-transform: uppercase;
    font-size: 12px;
    border: none;
}

.btn-primary {
    background-color: #007bff;
}

.btn-success {
    background-color: #28a745;
}

.btn-info {
    background-color: #17a2b8;
}

.btn-danger {
    background-color: #dc3545;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529; 
}
div.dt-buttons>.dt-button, div.dt-buttons>div.dt-button-split .dt-button {
    position: relative;
    display: inline-block;
    box-sizing: border-box;
    margin-left: .167em;
    margin-right: .167em;
    margin-bottom: .333em;
    padding: .5em 1em;
    border: 1px solid rgba(0, 0, 0, 0.3);
    border-radius: 21px;
    cursor: pointer;
    font-size: .88em;
    line-height: 1.6em;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    background-color: rgba(0, 0, 0, 0.1);
    background: linear-gradient(to bottom, rgba(230, 230, 230, 0.1) 0%, rgba(0, 0, 0, 0.1) 100%);
    filter: progid:DXImageTransform.Microsoft.gradient(GradientType=0,StartColorStr="rgba(230, 230, 230, 0.1)", EndColorStr="rgba(0, 0, 0, 0.1)");
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    text-decoration: none;
    outline: none;
    text-overflow: ellipsis;
}
</style>

</head>
<body>
<!-- <div id="global-loader">
<div class="whirly-loader"> </div>
</div> -->

<div class="main-wrapper">

<!-- Header Start -->
<div class="header">
<?php require_once("header.php");?>
</div>
<!-- Header End -->


<!-- Sidebar Start -->
<div class="sidebar" id="sidebar">
<?php require_once("sidebar.php");?>
</div>

<div class="sidebar collapsed-sidebar" id="collapsed-sidebar">
    <?php require_once("sidebar-collapsed.php");?>
</div>

<div class="sidebar horizontal-sidebar">
    <?php require_once("sidebar-horizontal.php");?>
</div>
<!-- Sidebar End -->


<div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="add-item d-flex">
                <div class="page-title">
                    <h4>Monthly Report</h4>
                    <h6>Manage your Reports</h6>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
   
<form method="GET" action="">
    <div class="row">
        <div class="col-md-3">
            <label for="start_month">Start Month:</label>
            <select name="start_month" id="start_month" class="form-control">
                <option value="">Select Month</option>
                <?php 
                for ($i = 1; $i <= 12; $i++) {
                    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $selected = (isset($_GET['start_month']) && $_GET['start_month'] == $month) ? 'selected' : '';
                    echo "<option value=\"$month\" $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="end_month">End Month:</label>
            <select name="end_month" id="end_month" class="form-control">
                <option value="">Select Month</option>
                <?php 
                for ($i = 1; $i <= 12; $i++) {
                    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $selected = (isset($_GET['end_month']) && $_GET['end_month'] == $month) ? 'selected' : '';
                    echo "<option value=\"$month\" $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="payment_status">Payment Status:</label>
            <select name="payment_status" id="payment_status" class="form-control">
                <option value="">All</option>
                <option value="0" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '0') ? 'selected' : ''; ?>>Pending</option>
                <option value="1" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '1') ? 'selected' : ''; ?>>Paid</option>
                <option value="2" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '2') ? 'selected' : ''; ?>>Cancelled</option>
                <option value="3" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '3') ? 'selected' : ''; ?>>Refunded</option>
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary mt-2">Search</button>
        </div>
    </div>
</form>


        <div class="table-responsive product-list mt-4">
        <?php
// Filtering Logic
$conditions = [];
$params = [];

// Filter by month
if (!empty($_GET['start_date'])) {
    $startMonth = date('Y-m', strtotime($_GET['start_date'])); // Extract YYYY-MM format
    $conditions[] = "DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $startMonth;
}

// Filter by payment status
if (isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
    $conditions[] = "status = ?";
    $params[] = $_GET['payment_status'];
}

// Initial query
$query = "SELECT * FROM invoice";

// Apply filters if there are any conditions
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$stmtProduct = $db->prepare($query);
$stmtProduct->execute($params);
$resultProduct = $stmtProduct->get_result();

// Helper function for status labels
function getStatusLabel($status) {
    $statusLabels = [
        0 => ['Pending', '#ffc107', 'orange'],
        1 => ['Paid', '#28a745', 'green'],
        2 => ['Cancelled', '#dc3545', 'red'],
        3 => ['Refunded', '#17a2b8', 'blue'],
    ];
    if (isset($statusLabels[$status])) {
        [$label, $borderColor, $textColor] = $statusLabels[$status];
        return "<span style='padding: 5px 10px; border: 2px solid $borderColor; border-radius: 4px; color: $textColor; font-size: 14px;'>$label</span>";
    }
    return "<span style='padding: 5px 10px; border: 2px solid #6c757d; border-radius: 4px; color: #383d41; font-size: 14px;'>Unknown</span>";
}
?>

<table class="table datanew">
    <thead>
        <tr>
            <th class="no-sort">
                <label class="checkboxs">
                    <input type="checkbox" id="select-all">
                    <span class="checkmarks"></span>
                </label>
            </th>
            <th>Invoice Number</th>
            <th>Total Tickets</th>
            <th>Payment Status</th>
            <th>Payment Method</th>
            <th>Transaction ID</th>
            <th>Due Date</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Tax (%)</th>
            <th>Discount (%)</th>
            <th>Total Amount</th>
            <th>Issue Date/Time</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($rowProduct = $resultProduct->fetch_assoc()) { ?>
            <tr>
                <td>
                    <label class="checkboxs">
                        <input type="checkbox">
                        <span class="checkmarks"></span>
                    </label>
                </td>
                <td>
                    <div class="productimgname">
                        <a href="javascript:void(0);"><?php echo $rowProduct['invoice_number']; ?></a>
                    </div>
                </td>
                <td><?php echo $rowProduct['total_tickets']; ?></td>
                <td><?php echo getStatusLabel($rowProduct['status']); ?></td>
                <td><?php echo $rowProduct['payment_method']; ?></td>
                <td><?php echo $rowProduct['transaction_id']; ?></td>
                <td><?php echo $rowProduct['due_date']; ?></td>
                <td><?php echo $rowProduct['price']; ?></td>
                <td><?php echo $rowProduct['quantity']; ?></td>
                <td><?php echo $rowProduct['tax']; ?></td>
                <td><?php echo $rowProduct['discount']; ?></td>
                <td><?php echo $rowProduct['total_amount']; ?></td>
                <td><?php echo $rowProduct['date']; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datanew').DataTable({
        dom: 'Bfrtip',  // Button placement
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Invoice Report',
                text: 'Export to Excel',
                className: 'btn btn-success'
            },
            {
                extend: 'pdfHtml5',
                title: 'Invoice Report',
                text: 'Export to PDF',
                className: 'btn btn-danger',
                orientation: 'landscape' // Change orientation to landscape
            }
        ],
        searching: false,  // Disable search
        paging: false      // Disable pagination
    });
});
</script>

<!-- <script>
$(document).ready(function () {
    $('.datanew').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: 'Copy',
                className: 'btn btn-primary'
            },
            {
                extend: 'csv',
                text: 'Export CSV',
                className: 'btn btn-success'
            },
            {
                extend: 'excel',
                text: 'Export Excel',
                className: 'btn btn-info'
            },
            {
                extend: 'pdf',
                text: 'Export PDF',
                className: 'btn btn-danger'
            },
            {
                extend: 'print',
                text: 'Print',
                className: 'btn btn-warning'
            }
        ],
        paging: false,
        searching: false,
        responsive: true,
    });
});

</script> -->
<script src="assets/js/jquery-3.7.1.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/feather.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/jquery.slimscroll.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/jquery.dataTables.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
<script src="assets/js/dataTables.bootstrap5.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/bootstrap.bundle.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/plugins/summernote/summernote-bs4.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/plugins/select2/js/select2.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/moment.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/plugins/sweetalert/sweetalert2.all.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
<script src="assets/plugins/sweetalert/sweetalerts.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/theme-script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
<script src="assets/js/script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

<script src="assets/js/rocket-loader-min.js" data-cf-settings="dadca703e9170cd1f69d6130-|49" defer=""></script></body>
</html>