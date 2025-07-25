<?php

session_start();
if (!isset($_SESSION["admin_id"])) {
  header("location: index.php");
}
require "./database/config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {

  try {

    // echo '<pre>';
    // print_r($_POST);
    // exit;

    $adminName = filter_input(INPUT_POST, 'adminName', FILTER_SANITIZE_STRING);
    $adminPhone = filter_input(INPUT_POST, 'adminPhone', FILTER_SANITIZE_NUMBER_INT);
    $adminEmail = filter_input(INPUT_POST, 'adminEmail', FILTER_SANITIZE_STRING);
    $adminRole = filter_input(INPUT_POST, 'adminRole', FILTER_SANITIZE_NUMBER_INT);
    $adminPassword = password_hash(filter_input(INPUT_POST, 'adminPassword', FILTER_SANITIZE_STRING), PASSWORD_DEFAULT);
    $stmtInsert = $db->prepare('INSERT INTO admin 
        (
        admin_username,
        admin_email,
        admin_password,
        admin_phone_number,
        admin_role
        ) 
        VALUES(?,?,?,?,?)');
    $stmtInsert->bind_param(
      'sssii',
      $adminName,
      $adminEmail,
      $adminPassword,
      $adminPhone,
      $adminRole
    );
    if ($stmtInsert->execute()) {
      $_SESSION['success'] = 'Admin Added Successfully';
    } else {
      $_SESSION['error'] = 'Error While adding Customer';
    }
  } catch (Exception $e) {
    $_SESSION['error'] = $e;
  }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editAdminId'])) {
  try {


    // echo '<pre>';
    // print_r($_POST);
    // exit;
    // Sanitize and validate input
    $editAdminId = filter_input(INPUT_POST, 'editAdminId', FILTER_SANITIZE_NUMBER_INT);
    $editAdminName = filter_input(INPUT_POST, 'editAdminName', FILTER_SANITIZE_STRING);
    $editAdminPhone = filter_input(INPUT_POST, 'editAdminPhone', FILTER_SANITIZE_STRING); // Changed to STRING
    $editAdminEmail = filter_input(INPUT_POST, 'editAdminEmail', FILTER_VALIDATE_EMAIL);
    $editAdminPassword = filter_input(INPUT_POST, 'adminPassword', FILTER_SANITIZE_STRING); // Note: Field name is adminPassword
    $editAdminStatus = filter_input(INPUT_POST, 'editAdminStatus', FILTER_SANITIZE_NUMBER_INT);
    $editAdminRole = filter_input(INPUT_POST, 'editAdminRole', FILTER_SANITIZE_NUMBER_INT);


    // Validate required inputs
    if (
      empty($editAdminId) ||
      empty($editAdminName) ||
      empty($editAdminPhone) ||
      $editAdminEmail === false ||
      !isset($editAdminStatus)
    ) {

      $_SESSION['error'] = 'All required fields must be valid.';
      header('Location: admin.php'); // Adjust to your form page
      exit;
    }

    // Check if password is provided
    if (!empty($editAdminPassword)) {
      // Hash the new password
      $hashedPassword = password_hash($editAdminPassword, PASSWORD_DEFAULT);
      $query = 'UPDATE admin SET 
              admin_username = ?,
              admin_email = ?,
              admin_password = ?,
              admin_phone_number = ?,
              admin_role = ?,
              is_active = ?
              WHERE admin_id = ?';
      $stmtUpdate = $db->prepare($query);
      $stmtUpdate->bind_param(
        'sssiiii',
        $editAdminName,
        $editAdminEmail,
        $hashedPassword,
        $editAdminPhone,
        $editAdminRole,
        $editAdminStatus,
        $editAdminId
      );
    } else {
      // Update without changing the password
      $query = 'UPDATE admin SET 
              admin_username = ?,
              admin_email = ?,
              admin_phone_number = ?,
              admin_role = ?,
              is_active = ?
              WHERE admin_id = ?';
      $stmtUpdate = $db->prepare($query);
      $stmtUpdate->bind_param(
        'sssiii',
        $editAdminName,
        $editAdminEmail,
        $editAdminPhone,
        $editAdminRole,
        $editAdminStatus,
        $editAdminId
      );
    }

    // Execute the update
    if ($stmtUpdate->execute()) {
      $_SESSION['success'] = 'Admin updated successfully.';
    } else {
      $_SESSION['error'] = 'Error while updating admin: ' . $db->error;
    }

    // Redirect to avoid form resubmission
    header('Location: admin.php'); // Adjust to your form page
    exit;

  } catch (mysqli_sql_exception $e) {
    // Handle specific database errors
    if ($e->getCode() == 1062) { // Duplicate entry error
      $_SESSION['error'] = 'Error: Email or phone number already exists.';
    } else {
      $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    header('Location: admin.php'); // Adjust to your form page
    exit;
  } catch (Exception $e) {
    $_SESSION['error'] = 'Unexpected error: ' . $e->getMessage();
    header('Location: admin.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adminIdForDelete'])) {
  try {
    $adminId = filter_input(INPUT_POST, 'adminIdForDelete', FILTER_SANITIZE_NUMBER_INT);

    $stmtDelete = $db->prepare('DELETE FROM admin WHERE admin_id = ?');
    $stmtDelete->bind_param('i', $adminId);

    if ($stmtDelete->execute()) {
      echo json_encode([
        'status' => 200,
        'message' => 'Admin deleted successfully.'
      ]);
      exit;
    } else {
      echo json_encode([
        'status' => 400,
        'error' => 'Failed to delete customer.'
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

  $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
  $stmtFetchLocalizationSettings->execute();
  $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


  $stmtFetch = $db->prepare("SELECT * FROM admin");
  $stmtFetch->execute();
  $admins = $stmtFetch->get_result();


  // fetch roles data

  $stmtFetchRoles = $db->prepare("SELECT * FROM roles");
  $stmtFetchRoles->execute();
  $roles = $stmtFetchRoles->get_result()->fetch_all(MYSQLI_ASSOC);

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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <meta name="author" content="">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin</title>

  <link rel="shortcut icon" type="image/x-icon"
    href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
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

  <!-- toast  -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

  <!-- html to pdf -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

  <!-- html to excel -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <!-- intl-tel-input -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
  <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>

</head>

<body>
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
      <div class="content">
        <div class="page-header">
          <div class="add-item d-flex">
            <div class="page-title">
              <h4>Admin List</h4>
              <h6>Manage Your Admin User</h6>
            </div>
          </div>
          <ul class="table-top-head">
            <li>
              <a data-bs-toggle="tooltip" onclick="exportToPDF()" data-bs-placement="top" title="Pdf"><img
                  src="assets/img/icons/pdf.svg" alt="img" /></a>
            </li>
            <li>
              <a data-bs-toggle="tooltip" onclick="exportToExcel()" data-bs-placement="top" title="Excel"><img
                  src="assets/img/icons/excel.svg" alt="img" /></a>
            </li>

            <li>
              <a href="" data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i data-feather="rotate-ccw"
                  class="feather-rotate-ccw"></i></a>
            </li>
            <li>
              <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                  data-feather="chevron-up" class="feather-chevron-up"></i></a>
            </li>
          </ul>

          <?php if ($isAdmin || hasPermission('Add User', $privileges, $roleData['0']['role_name'])): ?>
            <div class="page-btn">
              <a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-units"><i
                  data-feather="plus-circle" class="me-2"></i>Add Admin</a>
            </div>
          <?php endif; ?>
        </div>

        <div class="card table-list-card">
          <div class="card-body">
            <div class="table-top">
              <div class="search-set">
                <div class="search-input">
                  <a href="" class="btn btn-searchset"><i data-feather="search" class="feather-search"></i></a>
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
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>email</th>
                    <th>Created On</th>
                    <th>Status</th>
                    <th class="no-sort text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($admins->fetch_all(MYSQLI_ASSOC) as $admin) { ?>
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
                            <a href="javascript:void(0);"><?php echo $admin['admin_username'] ?></a>
                          </div>
                        </div>
                      </td>
                      <td><?php echo $admin['admin_phone_number'] ?></td>
                      <td>
                        <?php echo $admin['admin_email'] ?>
                      </td>
                      <td>
                        <?php $date = new DateTime($admin['created_at']);
                        echo $date->format(isset($localizationSettings["date_format"]) ? $localizationSettings["date_format"] : "d M Y") ?>
                      </td>
                      <td>
                        <?php if ($admin['is_active'] == 1) { ?>
                          <span class="badge badge-lg bg-success">Active</span>
                        <?php } else { ?>
                          <span class="badge badge-lg bg-danger">Inactive</span>
                        <?php } ?>
                      </td>

                      <td class="text-center">
                        <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="true">
                          <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                        </a>
                        <ul class="dropdown-menu">
                          <?php if ($isAdmin || hasPermission('Edit User', $privileges, $roleData['0']['role_name'])): ?>
                            <li>
                              <a data-bs-toggle="modal" data-bs-target="#edit-units"
                                data-admin-id="<?php echo $admin['admin_id'] ?>"
                                data-admin-name="<?php echo $admin['admin_username'] ?>"
                                data-admin-phone="<?php echo $admin['admin_phone_number'] ?>"
                                data-admin-email="<?php echo $admin['admin_email'] ?>"
                                data-admin-role="<?php echo $admin['admin_role'] ?>"
                                data-admin-status="<?php echo $admin['is_active'] ?>" class="editButton dropdown-item"><i
                                  data-feather="edit" class="info-img"></i>Edit
                              </a>
                            </li>
                          <?php endif; ?>

                          <?php if ($isAdmin || hasPermission('Delete User', $privileges, $roleData['0']['role_name'])): ?>

                            <li>
                              <a href="javascript:void(0);" data-admin-id="<?php echo $admin['admin_id'] ?>"
                                class="dropdown-item deleteButton mb-0"><i data-feather="trash-2"
                                  class="info-img"></i>Delete </a>
                            </li>
                          <?php endif; ?>
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
  </div>
  </div>

  <div class="modal fade" id="add-units">
    <div class="modal-dialog modal-dialog-centered custom-modal-two">
      <div class="modal-content">
        <div class="page-wrapper-new p-0">
          <div class="content">
            <div class="modal-header border-0 custom-modal-header">
              <div class="page-title">
                <h4>Add Admin</h4>
              </div>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body custom-modal-body">
              <form action="" method="post">
                <div class="row">

                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Name <span> *</span></label>
                      <input type="text" class="form-control" name="adminName" placeholder="Admin Name" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Email <span> *</span></label>
                      <input type="email" class="form-control" name="adminEmail" placeholder="Admin Name" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Phone <span> *</span></label>
                      <input type="tel" class="form-control" name="adminPhone" placeholder="Admin Phone" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Role <span> *</span></label>
                      <select class="form-select" name="adminRole" id="" placeholder="Admin Name" required>
                        <option>Select</option>
                        <?php foreach ($roles as $role) { ?>
                          <option value="<?php echo $role['role_id'] ?>"><?php echo $role['role_name'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                  </div>

                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Password <span> *</span></label>
                      <div class="pass-group">
                        <input type="password" class="pass-input" name="adminPassword" placeholder="*****" required />
                        <span class="fas toggle-password fa-eye-slash"></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer-btn">
                  <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" name="submit" class="btn btn-submit">Submit</button>
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
                <h4>Edit Admin</h4>
              </div>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body custom-modal-body">
              <form action="" method="post">
                <input type="hidden" name="editAdminId" id="editAdminId">
                <div class="row">

                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Name</label>
                      <input type="text" class="form-control" name="editAdminName" id="editAdminName" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Email</label>
                      <input type="email" class="form-control" name="editAdminEmail" id="editAdminEmail" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Admin Phone</label>
                      <input type="tel" class="form-control" name="editAdminPhone" id="editAdminPhone" required>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Role</label>
                      <select class="form-select" name="editAdminRole" id="editAdminRole" required>
                        <option>Select</option>
                        <?php foreach ($roles as $role) { ?>
                          <option value="<?php echo $role['role_id'] ?>"><?php echo $role['role_name'] ?></option>
                        <?php } ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Status</label>
                      <select class="form-select" name="editAdminStatus" id="editAdminStatus">
                        <option value="1">Enabled</option>
                        <option value="0">Disable</option>

                      </select>
                    </div>
                  </div>

                  <div class="col-lg-6">
                    <div class="input-blocks">
                      <label>Password</label>
                      <div class="pass-group">
                        <input type="password" class="pass-input" name="editAdminPassword" />
                        <span class="fas toggle-password fa-eye-slash"></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer-btn">
                  <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" name="edit" class="btn btn-submit">Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <script src="assets/js/jquery-3.7.1.min.js"></script>
  <script src="assets/js/feather.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/jquery.slimscroll.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/jquery.dataTables.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
  <script src="assets/js/dataTables.bootstrap5.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/bootstrap.bundle.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/plugins/summernote/summernote-bs4.min.js"
    type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/plugins/select2/js/select2.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/moment.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
  <script src="assets/js/bootstrap-datetimepicker.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js"
    type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
    type="dadca703e9170cd1f69d6130-text/javascript"></script>
  <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

  <script src="assets/js/rocket-loader-min.js" data-cf-settings="dadca703e9170cd1f69d6130-|49" defer=""></script>

  <script src="assets/js/custom.js"></script>

  <script>
    $(document).ready(function (e) {

      $(document).on('click', '.editButton', function () {

        let adminId = $(this).data("admin-id");
        let adminName = $(this).data("admin-name");
        let adminPhone = $(this).data("admin-phone");
        let adminEmail = $(this).data("admin-email");
        let adminStatus = $(this).data("admin-status");
        let adminRole = $(this).data("admin-role");

        $('#editAdminId').val(adminId);
        $('#editAdminName').val(adminName);
        $('#editAdminPhone').val(adminPhone);
        $('#editAdminEmail').val(adminEmail);
        $('#editAdminStatus').val(adminStatus);
        $('#editAdminRole').val(adminRole);


      });


      // Handle the click event on the delete button
      $(document).on('click', '.deleteButton', function (event) {
        let adminId = $(this).data('admin-id');

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
              url: 'admin.php', // The PHP file that will handle the deletion
              type: 'POST',
              data: { adminIdForDelete: adminId },
              success: function (response) {

                let result = JSON.parse(response);
                console.log(result);

                // Show success message and reload the page
                Swal.fire(
                  'Deleted!',
                  'The Admin has been deleted.',
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

      const input = $(".input-blocks input[name='adminPhone']").get(0); // or use [0]

      window.intlTelInput(input, {
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
        initialCountry: "auto",
        geoIpLookup: function (callback) {
          fetch('https://ipapi.co/json')
            .then(response => response.json())
            .then(data => callback(data.country_code))
            .catch(() => callback('us'));
        }
      });

      const inputEdit = $(".input-blocks input[name='editAdminPhone']").get(0); // or use [0]

      window.intlTelInput(inputEdit, {
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
        initialCountry: "auto",
        geoIpLookup: function (callback) {
          fetch('https://ipapi.co/json')
            .then(response => response.json())
            .then(data => callback(data.country_code))
            .catch(() => callback('us'));
        }
      });

    })
  </script>


</body>

</html>