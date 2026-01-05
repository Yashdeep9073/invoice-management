<?php
ob_start();
session_start();

require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';
require './utility/formatDateTime.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}


try {

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtRole = $db->prepare("Select * from roles");
    $stmtRole->execute();
    $roles = $stmtRole->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

} catch (PDOException $error) {
    $_SESSION['error'] = $error;
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['submit'])) {
    $roleName = filter_input(INPUT_POST, "roleName", FILTER_SANITIZE_STRING);

    try {
        $stmtRole = $db->prepare("INSERT INTO roles (role_name) VALUES (?)");
        $stmtRole->bind_param('s', $roleName);

        if ($stmtRole->execute()) {
            $_SESSION['success'] = "Role added successfully.";
            header("Location: roles.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add role.";
            header("Location: roles.php");
            exit();
        }

    } catch (PDOException $error) {
        $_SESSION['error'] = $error;
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['roleId'])) {
    $roleId = filter_input(INPUT_POST, "roleId", FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmtRole = $db->prepare("DELETE FROM roles WHERE role_id = ?");
        $stmtRole->bind_param('i', $roleId);
        if ($stmtRole->execute()) {
            $_SESSION['success'] = "Role deleted successfully.";
            header("Location: roles.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to delete role.";
            header("Location: roles.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = $e;
    }
}
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['update'])) {

    $editRoleId = filter_input(INPUT_POST, 'editRoleId', FILTER_SANITIZE_NUMBER_INT);
    $editRoleName = filter_input(INPUT_POST, 'editRoleName', FILTER_SANITIZE_STRING);
    $editRoleStatus = filter_input(INPUT_POST, 'editRoleStatus', FILTER_SANITIZE_NUMBER_INT);

    try {
        $stmtRole = $db->prepare("UPDATE roles SET role_name = ?, role_status = ? WHERE role_id = ?");
        $stmtRole->bind_param(
            'sii',
            $editRoleName,
            $editRoleStatus,
            $editRoleId
        );

        if ($stmtRole->execute()) {
            $_SESSION['success'] = "Role updated successfully.";
            header("Location: roles.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update role.";
            header("Location: roles.php");
            exit();
        }
    } catch (PDOException $error) {
        $_SESSION['error'] = $error;
        exit();
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
    <meta name="author" content="">
    <meta name="robots" content="noindex, nofollow">
    <title>Roles</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/newstyle.css">

    <link rel="stylesheet" href="assets/css/style.css">

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
                            <h4>Roles</h4>
                            <h6>Manage your roles</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">

                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                    <div class="page-btn">
                        <a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-units"><i
                                data-feather="plus-circle" class="me-2"></i> Add New Role</a>
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

                        </div>

                        <div class="card" id="filter_inputs">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <i data-feather="zap" class="info-img"></i>
                                            <select class="select">
                                                <option>Choose Role</option>
                                                <option>Admin</option>
                                                <option>Shop Owner</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <div class="input-blocks">
                                            <i data-feather="calendar" class="info-img"></i>
                                            <div class="input-groupicon">
                                                <input type="text" class="datetimepicker" placeholder="Choose Date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6 col-12 ms-auto">
                                        <div class="input-blocks">
                                            <a class="btn btn-filters ms-auto"> <i data-feather="search"
                                                    class="feather-search"></i> Search
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table  datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all">
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Role Name</th>
                                        <th>Status</th>
                                        <th>Created On</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox">
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td><?php print_r($role['role_name']) ?></td>
                                            <td>
                                                <?php if ($role['role_status'] == 1) { ?>
                                                    <span class="badge badge-lg bg-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-lg bg-danger">Inactive</span>
                                                <?php } ?>
                                            </td>
                                            <td><?php echo formatDateTime($role['created_at'], $localizationSettings); ?></td>

                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
                                                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                </a>
                                                <ul class="dropdown-menu">

                                                    <li>
                                                        <a href="javascript:void(0);    " class="editButton dropdown-item"
                                                            data-bs-toggle="modal" data-role-id="<?= $role['role_id']; ?>"
                                                            data-role-name="<?= $role['role_name']; ?>"
                                                            data-role-status="<?= $role['role_status']; ?>"
                                                            data-bs-target="#edit-units" href="javascript:void();"><i
                                                                data-feather="edit" class="info-img"></i>Edit
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a href="create-permission.php?id=<?= base64_encode($role['role_id']); ?>"
                                                            class="dropdown-item"><i data-feather="plus-circle"
                                                                class="info-img"></i>Create
                                                            Permission</a>
                                                    </li>

                                                    <li>
                                                        <a href="javascript:void(0);"
                                                            class="dropdown-item deleteButton mb-0"
                                                            href="javascript:void(0);"
                                                            data-role-id="<?= $role['role_id']; ?>"><i
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

    <div class="modal fade" id="add-units">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Create Role</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="POST">
                                <div class="mb-0">
                                    <label class="form-label">Role Name</label>
                                    <input type="text" name="roleName" class="form-control">
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">Create
                                        Role</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit-units">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Edit Role</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="POST">
                                <div class="mb-0">
                                    <input type="hidden" id="editRoleId" name="editRoleId">
                                    <label class="form-label">Role Name</label>
                                    <input type="text" id="editRoleName" name="editRoleName" class="form-control">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="editRoleStatus" id="editRoleStatus">
                                        <option value="">Select Status</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="update" class="btn btn-submit">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/js/theme-script.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/js/script.js" type="f0d2c2c569c2768c550dd59d-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="f0d2c2c569c2768c550dd59d-|49" defer=""></script>

    <script src="assets/js/custom.js"></script>

    <script type="text/javascript">
        $(document).ready(function () {
            $('.editButton').on('click', function (event) {

                let roleId = $(this).data('role-id');
                let roleName = $(this).data('role-name');
                let roleStatus = $(this).data('role-status');

                $('#editRoleId').val(roleId);
                $('#editRoleName').val(roleName);
                $('#editRoleStatus').val(roleStatus);

            });

            // Handle the click event on the delete button
            $('.deleteButton').on('click', function (event) {
                let roleId = $(this).data('role-id');

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
                            url: 'roles.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { roleId: roleId },
                            success: function (response) {
                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The Role has been deleted.',
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
        });
    </script>

</body>

</html>