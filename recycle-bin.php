<?php
session_start();

require "./database/config.php";
require './utility/formatDateTime.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}


try {
    $stmtFetchInvoices = $db->prepare("SELECT 
     invoice.*,
     customer.customer_id,
     customer.customer_name
     FROM invoice 
    INNER JOIN customer
    ON customer.customer_id = invoice.customer_id
     WHERE invoice.is_active = 0
    ");
    if ($stmtFetchInvoices->execute()) {
        $invoices = $stmtFetchInvoices->get_result();
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1 ;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceId'])) {

    try {
        $invoiceId = intval($_POST['invoiceId']);

        $stmtDelete = $db->prepare('DELETE FROM invoice WHERE invoice_id = ?');
        $stmtDelete->bind_param('i', $invoiceId);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Deleted Successfully',
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmtDelete->error,
            ]);

            exit;

        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage()
        ]);
        exit;
    }

}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdForRestore'])) {

    try {
        $invoiceId = intval($_POST['invoiceIdForRestore']);


        $stmtRestore = $db->prepare('UPDATE invoice SET is_active = 1 WHERE  invoice_id = ?');
        $stmtRestore->bind_param('i', $invoiceId);

        if ($stmtRestore->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Restored Successfully',
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmtRestore->error,
            ]);

            exit;

        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage()
        ]);
        exit;
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdsRestore'])) {


    $invoiceIds = $_POST['invoiceIdsRestore'];

    // Validate: Must be an array of integers
    if (!is_array($invoiceIds)) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid data format.'
        ]);
        exit;
    }

    try {
        // Prepare the SQL dynamically
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $types = str_repeat('i', count($invoiceIds)); // All integers

        $stmt = $db->prepare("UPDATE invoice SET is_active = 1 WHERE invoice_id IN ($placeholders)");
        $stmt->bind_param($types, ...$invoiceIds);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected invoices restored successfully.',
                'restored_ids' => $invoiceIds
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmt->error
            ]);
        }

        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdsDelete'])) {


    $invoiceIds = $_POST['invoiceIdsDelete'];

    // Validate: Must be an array of integers
    if (!is_array($invoiceIds)) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid data format.'
        ]);
        exit;
    }

    try {
        // Prepare the SQL dynamically
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $types = str_repeat('i', count($invoiceIds)); // All integers

        $stmt = $db->prepare("DELETE FROM invoice WHERE invoice_id IN ($placeholders)");
        $stmt->bind_param($types, ...$invoiceIds);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected invoices deleted successfully.',
                'deleted_ids' => $invoiceIds
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmt->error
            ]);
        }

        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'message' => $e->getMessage()
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
    <title>Deleted Invoice</title>

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
    <div id="global-loader">
        <div class="whirly-loader"> </div>
    </div>

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
                            <h4>Invoice Report </h4>
                            <h6>Manage Your Deleted Invoice </h6>
                        </div>
                    </div>
                    <ul class="table-top-head">

                        <li>
                            <a data-bs-toggle="tooltip" class="multi-delete-button" data-bs-placement="top"
                                title="Delete"><img src="assets/img/icons/delete.png" alt="img" /></a>
                        </li>

                        <li>
                            <a data-bs-toggle="tooltip" class="multi-restore-button" data-bs-placement="top"
                                title="Restore"><img src="assets/img/icons/folder-restore.png" alt="img" /></a>
                        </li>
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
                            <table id="myTable" class="table  datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all">
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Invoice No</th>
                                        <th>Customer</th>
                                        <th>Due Date</th>
                                        <th>Created Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="no-sort text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices->fetch_all(MYSQLI_ASSOC) as $invoice) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" name="invoiceIds"
                                                        value="<?php echo $invoice['invoice_id'] ?>">
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td><?php echo $invoice['invoice_number'] ?></td>
                                            <td><?php echo $invoice['customer_name'] ?></td>
                                            <td><?php echo formatDateTime($invoice['created_at'], $localizationSettings); ?>
                                            </td>
                                            <td><?php echo formatDateTime($invoice['due_date'], $localizationSettings); ?>
                                            </td>
                                            <td><?php echo (isset($localizationSettings["currency_symbol"]) ? $localizationSettings["currency_symbol"] : "$") . " " . $invoice['total_amount'] ?>
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
                                                            href="<?php echo getenv("BASE_URL") . "view-invoice?id=" . base64_encode($invoice['invoice_id']) ?>"
                                                            class="editStatus dropdown-item" data-admin-id=""><i
                                                                data-feather="eye" class="info-img"></i>Show
                                                            Detail</a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0);"
                                                            data-invoice-id="<?php echo $invoice['invoice_id'] ?>"
                                                            class="restoreButton dropdown-item"><i
                                                                data-feather="corner-up-left" class="info-img"></i>Restore
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a target="_blank"
                                                            href="<?php echo getenv("BASE_URL") . "download-invoice?id=" . base64_encode($invoice['invoice_id']) ?>"
                                                            class="qrCode dropdown-item"><i data-feather="download"
                                                                class="info-img"></i>Download
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0);"
                                                            data-invoice-id="<?php echo $invoice['invoice_id'] ?>"
                                                            class="dropdown-item deleteButton mb-0"><i
                                                                data-feather="trash-2" class="info-img"></i>Delete </a>
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
            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let invoiceId = $(this).data('invoice-id');

                // console.log(invoiceId);
                // return;

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
                            url: 'recycle-bin.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { invoiceId: invoiceId },
                            success: function (response) {
                                let result = JSON.parse(response);
                                console.log(result);

                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The Invoice has been deleted.',
                                    'success' // Added 'success' to show the success icon
                                ).then(() => {
                                    // Reload the page
                                    location.reload();
                                });
                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error deleting the vendor.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });
            $(document).on('click', '.restoreButton', function (event) {
                let invoiceId = $(this).data('invoice-id');

                // console.log(invoiceId);
                // return;

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Restore it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'recycle-bin.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { invoiceIdForRestore: invoiceId },
                            success: function (response) {
                                let result = JSON.parse(response);
                                console.log(result);

                                // Show success message and reload the page
                                Swal.fire(
                                    'Restored!',
                                    'The Invoice has been Restored.',
                                    'success' // Added 'success' to show the success icon
                                ).then(() => {
                                    // Reload the page
                                    location.reload();
                                });
                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error deleting the vendor.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.multi-delete-button', function (e) {
                e.preventDefault();

                let invoiceIds = [];
                $('input[name="invoiceIds"]:checked').each(function () {
                    invoiceIds.push(parseInt($(this).val()));
                });

                if (invoiceIds.length == 0) {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Please select invoice!",
                    });
                    return;
                }

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {

                        $.ajax({
                            url: "recycle-bin.php",
                            type: "post",
                            data: { invoiceIdsDelete: invoiceIds },
                            success: function (response) {

                                Swal.fire(
                                    'Deleted!',
                                    'The Invoice has been deleted.',
                                    'success'
                                ).then(() => {
                                    // Reload the page
                                    location.reload();
                                });

                            },
                            error: function (error) {
                                console.log(error);
                            },
                        });

                    }
                })


            });

            $(document).on('click', '.multi-restore-button', function (e) {
                e.preventDefault();

                let invoiceIds = [];
                $('input[name="invoiceIds"]:checked').each(function () {
                    invoiceIds.push(parseInt($(this).val()));
                });

                if (invoiceIds.length == 0) {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Please select invoice!",
                    });
                    return;
                }

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {

                        $.ajax({
                            url: "recycle-bin.php",
                            type: "post",
                            data: { invoiceIdsRestore: invoiceIds },
                            success: function (response) {

                                Swal.fire(
                                    'Restored!',
                                    'The Invoice has been Restored.',
                                    'success'
                                ).then(() => {
                                    // Reload the page
                                    location.reload();
                                });

                            },
                            error: function (error) {
                                console.log(error);
                            },
                        });

                    }
                })
            })

        });
    </script>

</body>

</html>