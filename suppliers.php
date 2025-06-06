<?php 
session_start();
// error_reporting(0);

//Checking Session Value
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}

// Database Connection
require "./database/config.php";
$upload_directory = "assets/img/supplier/";

//Add Supplier
if (isset($_POST['submit'])) {

  // Get the supplier data from the form
  $supplierName = filter_input(INPUT_POST, 'supplierName', FILTER_SANITIZE_STRING);
  $supplierEmail = filter_input(INPUT_POST, 'supplierEmail', FILTER_VALIDATE_EMAIL);
  $supplierPhone = filter_input(INPUT_POST, 'supplierPhone', FILTER_SANITIZE_STRING);
  $supplierAddress = filter_input(INPUT_POST, 'supplierAddress', FILTER_SANITIZE_STRING);
  $supplierCountry = filter_input(INPUT_POST, 'supplierCountry', FILTER_SANITIZE_STRING);
  $supplierCity = filter_input(INPUT_POST, 'supplierCity', FILTER_SANITIZE_STRING);

  // Initialize an empty string for the image
  $supplierProfileImage = '';

  // Check if an image file was uploaded
  if (!empty($_FILES["supplierImage"]["name"])) {
      $temp_name = $_FILES["supplierImage"]["tmp_name"];
      $original_name = $_FILES["supplierImage"]["name"];
      $file_size = $_FILES["supplierImage"]["size"];

      // Check file type and size
      $allowed_types = ["image/jpeg", "image/png", "image/gif"];
      $file_type = mime_content_type($temp_name);
      
      if (!in_array($file_type, $allowed_types)) {
          $_SESSION['msg'] = "Please Upload a Valid Image File.";
      } else {
          if ($file_size < 2 * 1024 * 1024) { // File size < 2MB
              $unique_filename = uniqid() . '_' . $original_name;
              $supplierProfileImage = $unique_filename; // Set the image name for the DB

              // Move the uploaded file to the correct directory
              if (!move_uploaded_file($temp_name, $upload_directory . $unique_filename)) {
                  $_SESSION['msg'] = "Error uploading file.";
              }
          } else {
              $_SESSION['msg'] = "File size exceeds 2MB limit.";
          }
      }
  }

  // Prepare the SQL query to insert data into the database
  $stmtAddSupplier = $db->prepare("INSERT INTO vendor(vendor_name,vendor_email,vendor_phone_number,vendor_profile_image,vendor_address,vendor_country,vendor_city) values(?,?,?,?,?,?,?)");
  $stmtAddSupplier->bind_param("sssssss", $supplierName, $supplierEmail, $supplierPhone, $supplierProfileImage, $supplierAddress, $supplierCountry, $supplierCity);

  // Execute the query and check for success
  if ($stmtAddSupplier->execute()) {
      $_SESSION['msg'] = "Supplier Added Successfully";
      header("Location: suppliers.php");
      exit();
  } else {
      $_SESSION['msg'] = "Error adding supplier.";
  }
}

//Edit Supplier
if (isset($_POST['edit'])) {
    
    print_r($_POST);
  // Get the supplier data from the form
    $vendorId = filter_input(INPUT_POST, 'vendor_id', FILTER_SANITIZE_STRING);
    $supplierNameEdit = filter_input(INPUT_POST, 'supplierNameEdit', FILTER_SANITIZE_STRING);
    $supplierEmailEdit = filter_input(INPUT_POST, 'supplierEmailEdit', FILTER_VALIDATE_EMAIL);
    $supplierPhoneEdit = filter_input(INPUT_POST, 'supplierPhoneEdit', FILTER_SANITIZE_STRING);
    $supplierAddressEdit = filter_input(INPUT_POST, 'supplierAddressEdit', FILTER_SANITIZE_STRING);
    $supplierCountryEdit = filter_input(INPUT_POST, 'supplierCountryEdit', FILTER_SANITIZE_STRING);
    $supplierCityEdit = filter_input(INPUT_POST, 'supplierCityEdit', FILTER_SANITIZE_STRING);

  // Initialize an empty string for the image
    $supplierProfileImageEdit = '';
  // Check if an image file was uploaded
    if (!empty($_FILES["supplierImageEdit"]["name"])) {
        $temp_name = $_FILES["supplierImageEdit"]["tmp_name"];
        $original_name = $_FILES["supplierImageEdit"]["name"];
        $file_size = $_FILES["supplierImageEdit"]["size"];

      // Check file type and size
        $allowed_types = ["image/jpeg", "image/png", "image/gif"];
        $file_type = mime_content_type($temp_name);
        
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['msg'] = "Please Upload a Valid Image File.";
        } else {
          if ($file_size < 2 * 1024 * 1024) { // File size < 2MB
                $unique_filename = uniqid() . '_' . $original_name;
              $supplierProfileImageEdit = $unique_filename; // Set the image name for the DB
                
              // Move the uploaded file to the correct directory
                if (!move_uploaded_file($temp_name, $upload_directory . $unique_filename)) {
                    $_SESSION['msg'] = "Error uploading file.";
                }
            } else {
                $_SESSION['msg'] = "File size exceeds 2MB limit.";
            }
        }
    }

// Prepare the SQL query to update data in the database
$stmtEdit = $db->prepare("UPDATE vendor SET vendor_name=?, vendor_email=?, vendor_phone_number=?, vendor_profile_image=?, vendor_address=?, vendor_country=?, vendor_city=? WHERE vendor_id=?");
$stmtEdit->bind_param("ssssssss", $supplierNameEdit, $supplierEmailEdit, $supplierPhoneEdit, $supplierProfileImageEdit, $supplierAddressEdit, $supplierCountryEdit, $supplierCityEdit, $vendorId);

  // Execute the query and check for success
    if ($stmtEdit->execute()) {
        $_SESSION['msg'] = "Supplier Added Successfully";
        header("Location: suppliers.php");
        exit();
    } else {
        $_SESSION['msg'] = "Error adding supplier.";
    }
}

//Delete Supplier
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

  if(isset($_GET['vendorId'])){
      // Get the vendor_id from the AJAX request
  $vendorId = $_GET['vendorId'];

  // Prepare the SQL statement to delete the vendor
  $stmt = $db->prepare("DELETE FROM vendor WHERE vendor_id = ?");
  $stmt->bind_param("i", $vendorId);

  if ($stmt->execute()) {
      // Return a success response
      echo json_encode(['status' => 'success']);
  } else {
      // Return an error response
      echo json_encode(['status' => 'error']);
  }

  $stmt->close();
  $conn->close();
  }

}

// Retrieve data
$stmtSupplier = $db->prepare("SELECT * FROM vendor");
$stmtSupplier->execute();
$result = $stmtSupplier->get_result();

//Get cities
$stmtCity = $db->prepare("SELECT * FROM cities");
$stmtCity->execute();
$resultCity = $stmtCity->get_result();

//Get cities
$stmtCity2 = $db->prepare("SELECT * FROM cities");
$stmtCity2->execute();
$resultCity2 = $stmtCity2->get_result();


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
<title>Dreams Pos Admin Template</title>

<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/animate.css">
<link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">
<link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div id="global-loader">
<div class="whirly-loader"> </div>
</div>

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
<h4>Supplier List</h4>
<h6>Manage Your Supplier</h6>
</div>
</div>
<ul class="table-top-head">
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"><img src="assets/img/icons/pdf.svg" alt="img"></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"><img src="assets/img/icons/excel.svg" alt="img"></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="Print"><i data-feather="printer" class="feather-rotate-ccw"></i></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i data-feather="chevron-up" class="feather-chevron-up"></i></a>
</li>
</ul>
<div class="page-btn">
<a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-units"><i data-feather="plus-circle" class="me-2"></i>Add New Supplier</a>
</div>
</div>

<div class="card table-list-card">
<div class="card-body">
<div class="table-top">
<div class="search-set">
<div class="search-input">
<a href="" class="btn btn-searchset"><i data-feather="search" class="feather-search"></i></a>
</div>
</div>
<div class="search-path">
<div class="d-flex align-items-center">
<a class="btn btn-filter" id="filter_search">
<i data-feather="filter" class="filter-icon"></i>
<span><img src="assets/img/icons/closes.svg" alt="img"></span>
</a>
</div>
</div>
<div class="form-sort">
<i data-feather="sliders" class="info-img"></i>
<select class="select">
<option>Sort by Date</option>
<option>25 9 23</option>
<option>12 9 23</option>
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
<option>Choose Supplier Name</option>
<option>Dazzle Shoes</option>
<option>A-Z Store</option>
</select>
</div>
</div>
<div class="col-lg-3 col-sm-6 col-12">
<div class="input-blocks">
<i data-feather="globe" class="info-img"></i>
<select class="select">
<option>Choose Country</option>
<option>Mexico</option>
<option>Italy</option>
</select>
</div>
</div>
<div class="col-lg-6 col-sm-6 col-12">
<div class="input-blocks">
<a class="btn btn-filters ms-auto"> <i data-feather="search" class="feather-search"></i> Search </a>
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
<th>Supplier Name</th>
<th>code</th>
<th>email</th>
<th>Phone</th>
<th>Country</th>
<th>City</th>
<th class="no-sort">Action</th>
</tr>
</thead>
<tbody>
<?php  while ($row = $result->fetch_assoc()) { ?>
<tr>
<td>
<label class="checkboxs">
<input type="checkbox">
<span class="checkmarks"></span>
</label>
</td>
<td>
<div class="productimgname">
<a href="javascript:void(0);" class="product-img supplier-img">
<img src=<?php echo "assets/img/supplier/".$row['vendor_profile_image']?> alt="product">
</a>
<div>
<a href="javascript:void(0);" class="ms-2"><?php print_r($row['vendor_name']); ?></a>
</div>
</div>
</td>
<td><?php print_r($row['vendor_id']); ?></td>
<td><a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="4b2a3b2e332824263b3e3f2e39380b2e332a263b272e65282426"><?php print_r($row['vendor_email']); ?></a></td>
<td><?php print_r($row['vendor_phone_number']); ?> </td>
<td><?php print_r($row['vendor_country']); ?></td>
<td><?php print_r($row['vendor_city']); ?></td>
<td class="action-table-data">
<div class="edit-delete-action">
<a class="editButton me-2 p-2 mb-0" data-bs-toggle="modal" data-bs-target="#edit-units"
    data-vendor-image="<?= $row['vendor_profile_image']; ?>"
    data-vendor-id="<?= $row['vendor_id']; ?>"
    data-vendor-name="<?= $row['vendor_name']; ?>"
    data-vendor-email="<?= $row['vendor_email']; ?>"
    data-vendor-phone="<?= $row['vendor_phone_number']; ?>"
    data-vendor-address="<?= $row['vendor_address']; ?>"
    data-vendor-country="<?= $row['vendor_country']; ?>"
    data-vendor-city="<?= $row['vendor_city']; ?>">
    <i data-feather="edit" class="feather-edit"></i>
</a>

<a href="javascript:void(0);" class="deleteButton " data-vendor-id
="<?= $row['vendor_id']; ?>">
  <i data-feather="trash-2" class="feather-trash-2"></i>
</a>
</div>
</td>
</tr>
<?php }?>
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
<h4>Add Supplier</h4>
</div>
<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body custom-modal-body">
<form action="" method="POST" enctype="multipart/form-data">
<div class="row">
<div class="col-lg-12">
<div class="new-employee-field">
<span>Avatar</span>
<div class="profile-pic-upload mb-2">
<div class="profile-pic">
<span><img id="output" src="" alt=""></span>
</div>
<div class="input-blocks mb-0">
<div class="image-upload mb-0">
<input type="file" name="supplierImage" accept="image/*" onchange="loadFile(event)">
<div class="image-uploads">
<h4>Change Image</h4>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="col-lg-4">
<div class="input-blocks">
<label>Supplier Name</label>
<input type="text" name="supplierName" required class="form-control">
</div>
</div>
<div class="col-lg-4">
<div class="input-blocks">
<label>Email</label>
<input type="email" name="supplierEmail" required class="form-control">
</div>
</div>
<div class="col-lg-4">
<div class="input-blocks">
<label>Phone</label>
<input type="tel" name="supplierPhone" required class="form-control">
</div>
</div>
<div class="col-lg-12">
<div class="input-blocks">
<label>Address</label>
<input type="text" name="supplierAddress" class="form-control">
</div>
</div>
<div class="col-lg-6 col-sm-10 col-10">
<div class="input-blocks">
<label>Country</label>
<select name="supplierCountry" class="select">
<option>Choose</option>
<option>Japan</option>
</select>
</div>
</div>
<div class="col-lg-6 col-sm-10 col-10">
<div class="input-blocks">
<label>City</label>
<select name="supplierCity" class="select">
<option>Choose</option>
<?php while($city = $resultCity->fetch_assoc()){?>
<option value=<?php print_r($city['name']); ?>><?php print_r($city['name']);?></option>
<?php }?>
</select>
</div>
</div>
<div class="col-md-12">
<div class="mb-0 input-blocks">
<label class="form-label">Descriptions</label>
<textarea class="form-control mb-1"></textarea>
<p>Maximum 600 Characters</p>
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
<h4>Edit Supplier</h4>
</div>
<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body custom-modal-body">
<form action="" method="POST" enctype="multipart/form-data">
<div class="row">
<div class="col-lg-12">
<div class="new-employee-field">
<span>Avatar</span>
<div class="profile-pic-upload edit-pic">
<div class="profile-pic">
<span><img id="output2" src="" alt=""></span>
</div>
<div class="input-blocks mb-0">
<div class="image-upload mb-0">
<input type="file" name="supplierImageEdit" onchange="loadFile2(event)" required>
<div class="image-uploads">
<h4>Change Image</h4>
</div>
</div>
</div>
</div>
</div>
</div>
<input type="hidden" id="editVendorId" name="vendor_id">
<div class="col-lg-4">
<div class="input-blocks">
<label>Supplier Name</label>
<input type="text" id="editVendorName" name="supplierNameEdit" placeholder="">
</div>
</div>
<div class="col-lg-4">
<div class="input-blocks">
<label>Email</label>
<input type="email" id="editVendorEmail" name="supplierEmailEdit" placeholder="">
</div>
</div>
<div class="col-lg-4">
<div class="input-blocks">
<label>Phone</label>
<input type="text" id="editVendorPhone"name="supplierPhoneEdit" placeholder="">
</div>
</div>
<div class="col-lg-12">
<div class="input-blocks">
<label>Address</label>
<input type="text" id="editVendorAddress" name="supplierAddressEdit" placeholder="" >
</div>
</div>

<div class="col-lg-6 col-sm-10 col-10">
<div class="input-blocks">
<label>Country</label>
<select name="supplierCountryEdit" class="select">
<option>Japan</option>
</select>
</div>
</div>
<div class="col-lg-6 col-sm-10 col-10">
<div class="input-blocks">
<label>City</label>
<select name="supplierCityEdit" id="editVendorCity" class="select">
<?php while($city2 = $resultCity2->fetch_assoc()){?>
<option value=<?php print_r($city2['name']); ?>><?php print_r($city2['name']);?></option>
<?php }?>
</select>
</div>
</div>
<div class="mb-0 input-blocks">
<label class="form-label">Descriptions</label>
<textarea class="form-control mb-1"></textarea>
<p>Maximum 600 Characters</p>
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



<script data-cfasync="false" src="../../cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
<script src="assets/js/jquery-3.7.1.min.js"></script>
<script src="assets/js/feather.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/jquery.slimscroll.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/jquery.dataTables.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/dataTables.bootstrap5.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/moment.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/bootstrap.bundle.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/plugins/select2/js/select2.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/plugins/sweetalert/sweetalert2.all.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/plugins/sweetalert/sweetalerts.min.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/theme-script.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/script.js" type="31c8b69b4242b6d9fa401d80-text/javascript"></script>
<script src="assets/js/rocket-loader-min.js" data-cf-settings="31c8b69b4242b6d9fa401d80-|49" defer=""></script>

<script>
  if ( window.history.replaceState ) 
  {
    window.history.replaceState( null, null, window.location.href );
  }
</script>
<script>
  var loadFile = function(event) {
    var reader = new FileReader();
    reader.onload = function(){
      var output = document.getElementById('output');
      output.src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
  };
</script>
<script>
  var loadFile2 = function(event) {
    var reader2 = new FileReader();
    reader2.onload = function(){
      var output2 = document.getElementById('output2');
      output2.src = reader2.result;
    };
    reader2.readAsDataURL(event.target.files[0]);
  };
</script>
<script type="text/javascript">
  $(document).ready(function() {
    $('.editButton').on('click', function(event) {
        // event.preventDefault(); // Prevent the default anchor action
        // Get vendor data from the button
        let vendorImage = $(this).data('vendor-image');
        let vendorId = $(this).data('vendor-id');
        let vendorName = $(this).data('vendor-name');
        let vendorEmail = $(this).data('vendor-email');
        let vendorPhone = $(this).data('vendor-phone');
        let vendorAddress = $(this).data('vendor-address');
        let vendorCountry = $(this).data('vendor-country');
        let vendorCity = $(this).data('vendor-city');
        let imagePath = 'assets/img/supplier/' + vendorImage;
        $('#output2').attr('src', imagePath);
        $('#editVendorId').val(vendorId);
        $('#editVendorName').val(vendorName);
        $('#editVendorEmail').val(vendorEmail);
        $('#editVendorPhone').val(vendorPhone);
        $('#editVendorAddress').val(vendorAddress);
        $('#editVendorCountry').val(vendorCountry);
        $('#editVendorCity').val(vendorCity);

        let selectedOptionCity = $('#editVendorCity option[value="' + vendorCity + '"]');
        selectedOptionCity.remove();
        selectedOptionCity.prependTo($('#editVendorCity'));
        
        // Debugging output
        // console.log("Hello");
        // console.log("Vendor ID: " + vendorId);
    });

  // Handle the click event on the delete button
  $('.deleteButton').on('click', function(event) {
    // Get the vendor ID from the data attribute
    let vendorId = $(this).data('vendor-id');
    console.log("Vendor ID: " + vendorId);
      // Show SweetAlert confirmation popup
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
            url: 'suppliers.php', // The PHP file that will handle the deletion
            type: 'GET',
            data: { vendorId: vendorId },
            success: function(response) {
              // Show success message and reload the page
              Swal.fire(
                'Deleted!',
                'The vendor has been deleted.', 
              ).then(() => {
                // Reload the page or remove the deleted row from the UI
                location.reload();
              });
            },
            error: function(xhr, status, error) {
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