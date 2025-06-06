<?php
ob_start();
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}
require "./database/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {

    try {

        // echo "<pre>";
        // print_r($_POST);
        // exit();
        $taxName = filter_input(INPUT_POST, 'taxName', FILTER_SANITIZE_STRING);
        $taxRate = filter_input(INPUT_POST, 'taxRate', FILTER_SANITIZE_STRING);

        $stmtInsert = $db->prepare('INSERT INTO tax 
        (
        tax_name,
        tax_rate
        ) 
        VALUES(?,?)');
        $stmtInsert->bind_param(
            'ss',
            $taxName,
            $taxRate
        );
        if ($stmtInsert->execute()) {
            $_SESSION['success'] = 'Tax Added Successfully';
            header("Location: tax-details.php");
            exit();
        } else {
            $_SESSION['error'] = 'Error While adding Tax';
            header("Location: tax-details.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e;
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editTaxId'])) {
    try {
        $editTaxId = filter_input(INPUT_POST, 'editTaxId', FILTER_SANITIZE_NUMBER_INT);
        $editTaxName = filter_input(INPUT_POST, 'editTaxName', FILTER_SANITIZE_STRING);
        $editTaxRate = filter_input(INPUT_POST, 'editTaxRate', FILTER_SANITIZE_STRING);
        $editTaxStatus = filter_input(INPUT_POST, 'editTaxStatus', FILTER_SANITIZE_NUMBER_INT);

        // echo "<pre>";
        // print_r($_POST);
        // exit();

        // Debug

        $stmtUpdate = $db->prepare('UPDATE tax SET 
            tax_name = ?, 
            tax_rate = ?, 
            status = ?
            WHERE tax_id = ?
        ');

        $stmtUpdate->bind_param(
            'ssii',
            $editTaxName,
            $editTaxRate,
            $editTaxStatus,
            $editTaxId
        );

        if ($stmtUpdate->execute()) {
            $_SESSION['success'] = 'Tax Updated Successfully';
            header("Location: tax-details.php");
            exit();
        } else {
            $_SESSION['error'] = 'Error while tax customer';
            header("Location: tax-details.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Exception: ' . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['taxId'])) {
    try {
        $taxId = filter_input(INPUT_POST, 'taxId', FILTER_SANITIZE_NUMBER_INT);

        $stmtDelete = $db->prepare('DELETE FROM tax WHERE tax_id = ?');
        $stmtDelete->bind_param('i', $taxId);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Tax deleted successfully.'
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 400,
                'error' => 'Failed to delete tax.'
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


try {
    $stmtFetch = $db->prepare("SELECT * FROM tax");
    $stmtFetch->execute();
    $taxes = $stmtFetch->get_result();
} catch (Exception $e) {
    $_SESSION['error'] = $e;
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
    <title>Tax Detail</title>

    <link rel="shortcut icon" type="image/x-icon" href="assets/img/fav/vis-favicon.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
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
                <div class="page-header">
                    <div class="add-item d-flex">
                        <div class="page-title">
                            <h4>Tax List</h4>
                            <h6>Manage Your Tax Rates</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"><img
                                    src="assets/img/icons/pdf.svg" alt="img" /></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"><img
                                    src="assets/img/icons/excel.svg" alt="img" /></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Print"><i data-feather="printer"
                                    class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a href='' data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i
                                    data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                    <div class="page-btn">
                        <a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-tax"><i
                                data-feather="plus-circle" class="me-2"></i>Add Tax </a>
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
                            <div class="form-sort">
                                <i data-feather="sliders" class="info-img"></i>
                                <select class="select">
                                    <option>Sort by Date</option>
                                    <option>Newest</option>
                                    <option>Oldest</option>
                                </select>
                            </div>
                        </div>

                        <div class="card" id="filter_inputs">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <i data-feather="user" class="info-img"></i>
                                            <select class="select">
                                                <option>Choose Name</option>
                                                <option>Lilly</option>
                                                <option>Benjamin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <i data-feather="stop-circle" class="info-img"></i>
                                            <select class="select">
                                                <option>Choose Status</option>
                                                <option>Active</option>
                                                <option>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <i data-feather="zap" class="info-img"></i>
                                            <select class="select">
                                                <option>Choose Role</option>
                                                <option>Store Keeper</option>
                                                <option>Salesman</option>
                                            </select>
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
                            <table class="table datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all" />
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Tax Name</th>
                                        <th>Tax Rate</th>
                                        <th>Status</th>
                                        <th>Created On</th>
                                        <th class="no-sort text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taxes->fetch_all(MYSQLI_ASSOC) as $tax) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" />
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <div class="userimgname">

                                                    <div>
                                                        <a href="javascript:void(0);"><?php echo $tax['tax_name'] ?></a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="ref-number"><?php echo $tax['tax_rate'] ?></td>
                                            <td>
                                                <?php if ($tax['status'] == 1) { ?>
                                                    <span class="badge badge-lg bg-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-lg bg-danger">Inactive</span>
                                                <?php } ?>

                                            </td>
                                            <td><?php $date = new DateTime($tax['created_at']);
                                            echo $date->format('d M Y') ?></td>

                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
                                                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                </a>
                                                <ul class="dropdown-menu">

                                                    <li>
                                                        <a data-bs-toggle="modal" data-bs-target="#edit-tax"
                                                            data-tax-id="<?php echo $tax['tax_id'] ?>"
                                                            data-tax-name="<?php echo $tax['tax_name'] ?>"
                                                            data-tax-value="<?php echo $tax['tax_rate'] ?>"
                                                            data-tax-status="<?php echo $tax['status'] ?>"
                                                            class="editButton dropdown-item"><i data-feather="edit"
                                                                class="info-img"></i>Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0);"
                                                            data-tax-id="<?php echo $tax['tax_id'] ?>"
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

    <div class="modal fade" id="add-tax">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Add Tax Rates</h4>
                            </div>

                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span> *</span></label>
                                            <input type="text" name="taxName" placeholder="Name" class="form-control"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Tax Rate % <span> *</span></label>
                                            <input type="text" class="form-control" name="taxRate"
                                                placeholder="Tax Rate %" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="edit-tax">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Edit Tax Rates</h4>
                            </div>

                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <input type="hidden" id="editTaxId" name="editTaxId">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span> *</span></label>
                                            <input type="text" class="form-control" name="editTaxName" id="editTaxName"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Tax Rate % <span> *</span></label>
                                            <input type="text" class="form-control" name="editTaxRate" id="editTaxRate"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Status <span> *</span></label>
                                            <select class="form-select" name="editTaxStatus" id="editTaxStatus"
                                                required>
                                                <option value="1">Enable</option>
                                                <option value="0">Unable</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="edit" class="btn btn-submit">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/script.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="e4d57c54f4cedf5e1ec30f61-|49" defer=""></script>

    <script>
        $(document).ready(function (e) {

            $(document).on('click', '.editButton', function () {

                let taxId = $(this).data('tax-id');
                let taxName = $(this).data("tax-name");
                let taxRate = $(this).data("tax-value");
                let taxStatus = $(this).data("tax-status");

                $('#editTaxId').val(taxId);
                $('#editTaxName').val(taxName);
                $('#editTaxRate').val(taxRate);
                $('#editTaxStatus').val(taxStatus);


            });


            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let taxId = $(this).data('tax-id');

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
                            url: 'tax-details.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { taxId: taxId },
                            success: function (response) {

                                let result = JSON.parse(response);
                                console.log(result);

                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The tax has been deleted.',
                                ).then(() => {
                                    // Reload the page or remove the deleted row from the UI
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

        })
    </script>

</body>

</html>