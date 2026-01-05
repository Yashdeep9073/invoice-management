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

// Define the upload directory
$uploadDirectory = 'public/upload/auth/images/';

try {
    $stmtFetch = $db->prepare("SELECT * FROM currency");
    $stmtFetch->execute();
    $currencies = $stmtFetch->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}



// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        //code...

        $currencyName = $_POST['currencyName'];
        $currencySymbol = $_POST['currencySymbol'];
        $currencyCode = $_POST['currencyCode'];

        $stmtInsert = $db->prepare("INSERT INTO currency (currency_name,currency_code,currency_symbol)
            VALUES(?,?,?)
         ");

        $stmtInsert->bind_param(
            "sss",
            $currencyName,
            $currencyCode,
            $currencySymbol
        );
        $stmtInsert->execute();

        $_SESSION['success'] = "Currency is added successfully";
        header("Location: currency-settings.php");
        exit;

    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: currency-settings.php");
        exit;

    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['currencyId'])) {
    try {

        $currencyId = $_POST['currencyId'];

        $stmtDelete = $db->prepare("DELETE FROM currency WHERE currency_id = ? ");
        $stmtDelete->bind_param("i", $currencyId);
        $stmtDelete->execute();

        //throw $th;
        echo json_encode([
            "status" => 200,
            "message" => "Currency Deleted successfully",
        ]);
        exit;

    } catch (\Throwable $th) {
        //throw $th;
        echo json_encode([
            "status" => 500,
            "error" => $th->getMessage(),
            "message" => "Error while deleting currency",

        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['edit'])) {
    try {
        $editCurrencyId = $_POST['editCurrencyId'];
        $editCurrencyName = $_POST['editCurrencyName'];
        $editCurrencySymbol = $_POST['editCurrencySymbol'];
        $editCurrencyCode = $_POST['editCurrencyCode'];
        $editCurrencyStatus = $_POST['editCurrencyStatus'];

        $stmtUpdate = $db->prepare("UPDATE currency SET
        currency_name = ?,
        currency_code = ?,
        currency_symbol = ?,
        is_active = ?
        WHERE currency_id = ?
        ");

        $stmtUpdate->bind_param(
            "sssii",
            $editCurrencyName,
            $editCurrencyCode,
            $editCurrencySymbol,
            $editCurrencyStatus,
            $editCurrencyId
        );

        $stmtUpdate->execute();

        $_SESSION['success'] = "Currency Updated Successfully";
        header("Location: currency-settings.php");
        exit;
    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: currency-settings.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <meta name="robots" content="noindex, nofollow">
    <title>Currencies</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">

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
                            dismissible: false,
                            duration: 3000
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
                            dismissible: false,
                            duration: 3000
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
            <div class="content settings-content">
                <div class="page-header settings-pg-header">
                    <div class="add-item d-flex">
                        <div class="page-title">
                            <h4>Settings</h4>
                            <h6>Manage your settings on portal</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i
                                    data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>
                <div class="row">
                    <div class="col-xl-12">
                        <div class="settings-wrapper d-flex">
                            <div class="sidebars settings-sidebar theiaStickySidebar" id="sidebar2">
                                <div class="sidebar-inner slimscroll">
                                    <div id="sidebar-menu5" class="sidebar-menu">
                                        <ul>
                                            <li class="submenu-open">
                                                <?php
                                                require("./settings-siderbar.php");
                                                ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-page-wrap w-50">
                                <div class="setting-title">
                                    <h4>Currency</h4>
                                </div>
                                <div class="page-header bank-settings justify-content-end">
                                    <div class="page-btn">
                                        <a href="#" class="btn btn-added" data-bs-toggle="modal"
                                            data-bs-target="#add-currency"><i data-feather="plus-circle"
                                                class="me-2"></i>Add New Currency</a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="card table-list-card">
                                            <div class="card-body">
                                                <div class="table-top">
                                                    <div class="search-set">
                                                        <div class="search-input">
                                                            <a href="" class="btn btn-searchset"><i
                                                                    data-feather="search"
                                                                    class="feather-search"></i></a>
                                                        </div>
                                                    </div>

                                                </div>

                                                <div class="card" id="filter_inputs">
                                                    <div class="card-body pb-0">
                                                        <div class="row">
                                                            <div class="col-lg-4 col-sm-6 col-12">
                                                                <div class="input-blocks">
                                                                    <i data-feather="user" class="info-img"></i>
                                                                    <select class="select">
                                                                        <option>Choose Name</option>
                                                                        <option>Euro</option>
                                                                        <option>England Pound</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-sm-6 col-12 ms-auto">
                                                                <div class="input-blocks">
                                                                    <a class="btn btn-filters ms-auto"> <i
                                                                            data-feather="search"
                                                                            class="feather-search"></i> Search </a>
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
                                                                        <input type="checkbox" id="select-all">
                                                                        <span class="checkmarks"></span>
                                                                    </label>
                                                                </th>
                                                                <th>Currency Name</th>
                                                                <th>Code </th>
                                                                <th>Symbol</th>
                                                                <th>Status</th>
                                                                <th>Created On</th>
                                                                <th class="no-sort text-end">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($currencies as $currency) { ?>
                                                                <tr>
                                                                    <td>
                                                                        <label class="checkboxs">
                                                                            <input type="checkbox">
                                                                            <span class="checkmarks"></span>
                                                                        </label>
                                                                    </td>
                                                                    <td>
                                                                        <?= $currency['currency_name'] ?>
                                                                    </td>
                                                                    <td>
                                                                        <?= $currency['currency_code'] ?>
                                                                    </td>
                                                                    <td>
                                                                        <?= $currency['currency_symbol'] ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($currency['is_active'] == 1) { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-success">Active</span>
                                                                        <?php } else { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-danger">Inactive</span>
                                                                        <?php } ?>


                                                                    </td>
                                                                    <td><?php echo formatDateTime($currency['created_at'], $localizationSettings); ?>
                                                                    </td>
                                                                    <td class="action-table-data justify-content-end">
                                                                        <div class="edit-delete-action">
                                                                            <a class="editButton me-2 p-2" href="#"
                                                                                data-currency-id="<?= $currency['currency_id'] ?>"
                                                                                data-currency-name="<?= $currency['currency_name'] ?>"
                                                                                data-currency-code="<?= $currency['currency_code'] ?>"
                                                                                data-currency-symbol="<?= $currency['currency_symbol'] ?>"
                                                                                data-currency-status="<?= $currency['is_active'] ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#edit-currency">
                                                                                <i data-feather="edit"
                                                                                    class="feather-edit"></i>
                                                                            </a>
                                                                            <a class="deleteButton p-2"
                                                                                data-currency-id="<?= $currency['currency_id'] ?>"
                                                                                href="javascript:void(0);">
                                                                                <i data-feather="trash-2"
                                                                                    class="feather-trash-2"></i>
                                                                            </a>
                                                                        </div>
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
            </div>
        </div>
    </div>


    <div class="modal fade" id="add-currency">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Add Currency</h4>
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
                                            <label class="form-label">Currency Name <span> *</span></label>
                                            <input type="text" class="form-control" name="currencyName"
                                                placeholder="Enter Currency Name" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Currency Symbol <span> *</span></label>
                                            <input type="text" class="form-control" name="currencySymbol"
                                                placeholder="Enter Currency Symbol" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Currency Code <span> *</span></label>
                                            <input type="text" class="form-control" placeholder="Enter Currency Code"
                                                name="currencyCode" required>
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


    <div class="modal fade" id="edit-currency">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Edit Currency</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <input type="hidden" name="editCurrencyId" id="editCurrencyId">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Currency Name <span> *</span></label>
                                            <input type="text" class="form-control" name="editCurrencyName"
                                                id="editCurrencyName" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Currency Symbol <span> *</span></label>
                                            <input type="text" class="form-control" name="editCurrencySymbol"
                                                id="editCurrencySymbol" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Currency Code <span> *</span></label>
                                            <input type="text" class="form-control" name="editCurrencyCode"
                                                id="editCurrencyCode" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Status <span> *</span></label>
                                            <select class="form-select" name="editCurrencyStatus"
                                                id="editCurrencyStatus" required>
                                                <option>Select</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
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

    <script src="assets/js/feather.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="8653368217031db4c81c5dd1-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="8653368217031db4c81c5dd1-text/javascript"></script>

    <script src="assets/js/script.js" type="8653368217031db4c81c5dd1-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="8653368217031db4c81c5dd1-|49" defer=""></script>


    <script>
        $(document).ready(function (e) {

            $(document).on('click', '.editButton', function () {
                let currencyId = $(this).data('currency-id');
                let currencyName = $(this).data("currency-name");
                let currencyCode = $(this).data("currency-code");
                let currencySymbol = $(this).data("currency-symbol");
                let currencyStatus = $(this).data("currency-status");

                $('#editCurrencyId').val(currencyId);
                $('#editCurrencyName').val(currencyName);
                $('#editCurrencyCode').val(currencyCode);
                $('#editCurrencySymbol').val(currencySymbol);
                $('#editCurrencyStatus').val(currencyStatus);
            });


            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let currencyId = $(this).data('currency-id');

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
                            url: 'currency-settings.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { currencyId: currencyId },
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