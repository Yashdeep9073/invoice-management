<?php
session_start();
ob_start();
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';


// Redirect to login if not authenticated
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit();
}


// Function to fetch role data
function fetchPermissions($db, $roleId)
{
    $stmtRole = $db->prepare("SELECT * FROM roles WHERE role_status = 1 AND role_id = ?");
    $stmtRole->bind_param('i', $roleId);
    $stmtRole->execute();
    return $stmtRole->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Initialize variables
$roleId = null;
$roles = [];
$permissions = [];
$assignedPermissions = [];

try {
    // Fetch all active permissions
    $stmtPermission = $db->prepare("SELECT * FROM permissions WHERE status = 1");
    $stmtPermission->execute();
    $permissions = $stmtPermission->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    // Handle GET request to fetch roleId
    if (isset($_GET['id'])) {
        $encryptedId = $_GET['id'];
        if ($encryptedId === null || $encryptedId === false) {
            throw new Exception("Invalid role ID provided");
        }
        $roleId = base64_decode($encryptedId);
        if ($roleId === false) {
            throw new Exception("Failed to decode role ID");
        }
        $roleId = (int) $roleId; // Ensure roleId is an integer

        // Fetch role and assigned permissions
        $roles = fetchPermissions($db, $roleId);
        if (empty($roles)) {
            throw new Exception("Role not found or inactive");
        }

        $stmtRolePermission = $db->prepare("
            SELECT permissions.*, role_permissions.role_permission_id 
            FROM role_permissions
            INNER JOIN permissions ON role_permissions.permission_id = permissions.permission_id
            WHERE role_permissions.role_id = ?
        ");
        $stmtRolePermission->bind_param('i', $roleId);
        $stmtRolePermission->execute();
        $assignedPermissions = $stmtRolePermission->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Handle POST request for deleting a permission
    if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['permissionId']) && !isset($_POST['submit'])) {

        try {
            $permissionId = filter_input(INPUT_POST, 'permissionId', FILTER_SANITIZE_NUMBER_INT);

            $stmtDelete = $db->prepare("DELETE FROM role_permissions WHERE role_permission_id = ?");
            $stmtDelete->bind_param('i', $permissionId);
            if (!$stmtDelete->execute()) {
                throw new Exception("Failed to delete permission");
            }

            //throw $th;
            echo json_encode([
                "status" => 200,
                "message" => "Permission deleted successfully",
            ]);
            exit;

        } catch (\Throwable $th) {
            //throw $th;
            echo json_encode([
                "status" => 500,
                "message" => "Error:" . $th->getMessage(),
            ]);
            exit;
        }


    }

    // Handle POST request for assigning permissions
    if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['submit'])) {
        $roleId = filter_input(INPUT_POST, 'roleId', FILTER_SANITIZE_NUMBER_INT);
        if (!$roleId) {
            throw new Exception("Role ID is required");
        }

        $permissionIds = isset($_POST['permissionId']) && is_array($_POST['permissionId'])
            ? array_map('intval', $_POST['permissionId'])
            : [];

        if (empty($permissionIds)) {
            throw new Exception("No permissions selected");
        }

        $stmtRolePermission = $db->prepare("
            INSERT INTO role_permissions (role_id, permission_id) 
            VALUES (?, ?)
        ");
        foreach ($permissionIds as $permissionId) {
            $stmtRolePermission->bind_param('ii', $roleId, $permissionId);
            $stmtRolePermission->execute();
        }
        $_SESSION['success'] = "Permissions assigned successfully.";
        header("Location: create-permission.php?id=" . base64_encode($roleId));
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . htmlspecialchars($e->getMessage());
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="">
    <meta name="keywords"
        content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
    <meta name="author" content="">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $roles[0]['role_name'] ?></title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

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
                            <h4>Permission</h4>
                            <h6>Manage your permissions</h6>
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
                                data-feather="plus-circle" class="me-2"></i> Add New Permission</a>
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
                                        <th>Permission</th>
                                        <th class="no-sort">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedPermissions as $data) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox">
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td><?php echo $data['permission_name'] ?></td>
                                            <td class="action-table-data">
                                                <div class="edit-delete-action">

                                                    <a class="deleteButton p-2"
                                                        data-permission-id="<?php echo $data['role_permission_id'] ?>"
                                                        href="javascript:void(0);">
                                                        <i data-feather="trash-2" class="feather-trash-2"></i>
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



    <div class="modal fade" id="add-units">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Create Permission</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="POST">
                                <div class="mb-0">
                                    <label class="form-label">Role Name</label>
                                    <select class="form-select" name="roleId" id="">
                                        <?php foreach ($roles as $role) { ?>
                                            <option selected value="<?php echo $role['role_id']; ?>">
                                                <?php echo $role['role_name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="mb-0">
                                    <input type="hidden" id="editRoleId" name="role_id">
                                    <label class="form-label">Permission</label>
                                    <!-- <select class="select2 form-select" name="serviceName[]"
                                                            multiple="multiple"></select> -->
                                    <select class="select2 form-select" name="permissionId[]" id="" multiple="multiple">
                                        <option value="">Select </option>
                                        <?php foreach ($permissions as $permission) { ?>
                                            <option value="<?php echo $permission['permission_id']; ?>">
                                                <?php echo $permission['permission_name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="assets/js/moment.min.js" type="9700fb0e03ee09216bc68fe3-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="9700fb0e03ee09216bc68fe3-text/javascript"></script>


    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/script.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/custom-select2.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="85b95337cd86ef30623c36b5-|49" defer=""></script>
    <script src="assets/js/custom.js"></script>

    <script type="text/javascript">
        $(document).ready(function () {


            // Handle the click event on the delete button
            $('.deleteButton').on('click', function (event) {
                let permissionId = $(this).data('permission-id');

                console.log("Id is ->" + permissionId);


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
                            url: window.location.href, // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { permissionId: permissionId },
                            success: function (response) {

                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The Permission has been deleted.',
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