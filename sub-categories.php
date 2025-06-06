<?php 
session_start();
// error_reporting(0);
//Checking Session Value
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}

// Database Connection
require "./database/config.php";

//Add Sub-Category Data
if($_SERVER['REQUEST_METHOD'] == "POST"){
    if(isset($_POST['submit'])){
        $adminIdEncoded = $_SESSION["admin_id"];
        $adminIdDecoded = base64_decode($adminIdEncoded);
        $subCategory = filter_input(INPUT_POST,'sub-category',FILTER_SANITIZE_STRING);
        $categoryId = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
        $status = filter_input(INPUT_POST,'status',FILTER_SANITIZE_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);

        $stmtCheckSubCategory = $db->prepare("SELECT * FROM product_sub_category WHERE sub_category_name = ?");
        $stmtCheckSubCategory->bind_param("s",$subCategory);
        $stmtCheckSubCategory->execute();
        $resultCheckSubCategory = $stmtCheckSubCategory->get_result();
        // $rowCheckSubCategory = $resultCheckSubCategory->fetch_assoc();

        if($resultCheckSubCategory->num_rows > 0){
          $msgError = "Sub-category already exists!";
        }else{
        
          //Creating Unique Id For Sub Category
        $stmtCategoryId = $db->prepare("SELECT * FROM product_category WHERE category_id = ?");
        $stmtCategoryId->bind_param("s",$categoryId);
        $stmtCategoryId->execute();
        $resultCategoryId = $stmtCategoryId->get_result();
        $rowCategoryId = $resultCategoryId->fetch_assoc();

        function generateSubCategoryCode($prefix) {
            return $prefix . "-" . time();
        }
        $firstFour = substr($rowCategoryId['category_name'], 0, 4);
        $capitalized = strtoupper($firstFour); // Convert to uppercase
        $subCategoryCode = generateSubCategoryCode($capitalized);

        $stmtSubCategory = $db->prepare("INSERT INTO product_sub_category (sub_category_name,product_category_id,sub_category_description,created_by,status,product_sub_category_code) VALUES(?, ?, ?, ?, ?, ?)");
        $stmtSubCategory->bind_param("ssssss",$subCategory,$categoryId,$discription,$adminIdDecoded,$status,$subCategoryCode);
        if($stmtSubCategory->execute()){
          $msgSuccess =  "Sub-category added successfully!";
        }else{
          $msgError = "Failed to add sub-category.";
        };
        }
    }
}

//Edit Sub-Category Data
if($_SERVER['REQUEST_METHOD'] == "POST"){

    if(isset($_POST['edit'])){
        $category = filter_input( INPUT_POST,'editCategory',FILTER_SANITIZE_STRING);
        $subCategory = filter_input( INPUT_POST,'editSubCategory',FILTER_SANITIZE_STRING);
        $editStatus = filter_input( INPUT_POST,'editSubCategoryId',FILTER_SANITIZE_STRING);

    }
}

//Delete 
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Get the vendor_id from the AJAX request
    if(isset($_GET['subcategoryId'])){
        $subcategoryId = $_GET['subcategoryId'];  
        // Prepare the SQL statement to delete the vendor
        $stmtDelete = $db->prepare("DELETE FROM product_sub_category WHERE sub_category_id = ?");
        $stmtDelete->bind_param("i", $subcategoryId);
      
        if ($stmtDelete->execute()) {
            // Return a success response
            echo json_encode(['status' => 'success']);
        } else {
            // Return an error response
            echo json_encode(['status' => 'error']);
        }
      
        // $stmt->close();
        // $conn->close();
      }
}



//Retrive Category Data
$stmtCategory = $db->prepare("SELECT * FROM product_category");
$stmtCategory->execute();
$resultCategory = $stmtCategory->get_result();

//Retrive Category Data
$stmtCategory2 = $db->prepare("SELECT * FROM product_category");
$stmtCategory2->execute();
$resultCategory2 = $stmtCategory2->get_result();


//Retrive Sub-Category Data
$stmtSubCategory = $db->prepare("
    SELECT * FROM product_sub_category 
    INNER JOIN product_category 
        ON product_sub_category.product_category_id = product_category.category_id
    INNER JOIN admin 
        ON product_sub_category.created_by = admin.admin_id
");

$stmtSubCategory->execute();
$resultSubCategory = $stmtSubCategory->get_result();



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
<link rel="stylesheet" href="assets/css/feather.css">
<link rel="stylesheet" href="assets/css/animate.css">

<link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

<link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

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
<?php if(isset($msgSuccess)){?>
<div class="alert alert-outline-success alert-dismissible fade show">
<?php echo $msgSuccess?>
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><i class="fas fa-xmark"></i></button>
</div>
<?php }?>
<?php if(isset($msgError)){?>
  <div class="alert alert-danger alert-dismissible fade show custom-alert-icon shadow-sm d-flex align-items-centers" role="alert">
<i class="feather-alert-octagon flex-shrink-0 me-2"></i>
<?php echo $msgError?>
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><i class="fas fa-xmark"></i></button>
</div>
<?php }?>
<div class="page-header">
<div class="add-item d-flex">
<div class="page-title">
<h4>Sub Category list</h4>
<h6>Manage your subcategories</h6>
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
<a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-category"><i data-feather="plus-circle" class="me-2"></i> Add Sub Category</a>
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
<a class="btn btn-filter" id="filter_search">
<i data-feather="filter" class="filter-icon"></i>
<span><img src="assets/img/icons/closes.svg" alt="img"></span>
</a>
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
<i data-feather="zap" class="info-img"></i>
<select class="select">
<option>Choose Category</option>
<option>Laptop</option>
<option>Electronics</option>
<option>Shoe</option>
</select>
</div>
</div>
<div class="col-lg-3 col-sm-6 col-12">
<div class="input-blocks">
<i data-feather="zap" class="info-img"></i>
<select class="select">
<option>Choose SubCategory</option>
<option>Fruits</option>
</select>
</div>
</div>
<div class="col-lg-3 col-sm-6 col-12">
<div class="input-blocks">
<i data-feather="stop-circle" class="info-img"></i>
<select class="select">
<option>Category Code</option>
<option>CT001</option>
<option>CT002</option>
</select>
</div>
</div>
<div class="col-lg-3 col-sm-6 col-12 ms-auto">
<div class="input-blocks">
<a class="btn btn-filters ms-auto"> <i data-feather="search" class="feather-search"></i> Search </a>
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
<!-- <th>Image</th> -->
<th>Category</th>
<th>Parent category</th>
<th>Category Code</th>
<th>Description</th>
<th>Created By</th>
<th class="no-sort">Action</th>
</tr>
</thead>
<tbody>
<?php while($rowSubCategory = $resultSubCategory->fetch_assoc()){?>
<tr>
<td>
<label class="checkboxs">
<input type="checkbox">
<span class="checkmarks"></span>
</label>
</td>
<!-- <td>
<a class="product-img">
<img src="assets/img/products/product1.jpg" alt="product">
</a>
</td> -->
<td><?php print_r($rowSubCategory['sub_category_name'])?></td>
<td><?php print_r($rowSubCategory['category_name'])?></td>
<td><?php print_r($rowSubCategory['product_sub_category_code'])?></td>
<td><?php print_r($rowSubCategory['sub_category_description'])?></td>
<td><?php print_r($rowSubCategory['admin_username'])?></td>
<td class="action-table-data">
<div class="edit-delete-action">
<a class="editButton me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#edit-category" 
data-subcategory-id="<?= $rowSubCategory['product_sub_category_code']; ?>"
data-category-id="<?= $rowSubCategory['category_id']; ?>"
data-subcategory-name="<?= $rowSubCategory['sub_category_name']; ?>"
data-subcategory-description="<?= $rowSubCategory['sub_category_description']; ?>"
data-subcategory-status="<?= $rowSubCategory['status']; ?>"
>
<i data-feather="edit" class="feather-edit"></i>
</a>
<a class="deleteButton " href="javascript:void(0);" data-subcategory-id="<?= $rowSubCategory['sub_category_id']; ?>">
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


<div class="modal fade" id="add-category">
<div class="modal-dialog modal-dialog-centered custom-modal-two">
<div class="modal-content">
<div class="page-wrapper-new p-0">
<div class="content">
<div class="modal-header border-0 custom-modal-header">
<div class="page-title">
<h4>Create Sub Category</h4>
</div>
<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body custom-modal-body">
<form action="" enctype="multipart/form-data" method="POST">
<div class="mb-3">
<label class="form-label">Parent Category</label>
<select name="category" class="select">
<option>Choose Category</option>
<?php while($rowCategory = $resultCategory->fetch_assoc()){ ?>
<option value=<?php print_r($rowCategory['category_id']);?>><?php print_r($rowCategory['category_name']);?></option>
<?php }?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Category Name</label>
<input type="text" name="sub-category" class="form-control">
</div>
<div class="mb-3">
<label class="form-label">Status</label>
<select name="status" class="select">
<option>Choose</option>
<option value="1">Enable</option>
<option value="0">Disabled</option>
</select>
</div>
<div class="mb-3 input-blocks">
<label class="form-label">Description</label>
<textarea name="description" class="form-control"></textarea>
</div>

<div class="modal-footer-btn">
<button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">Cancel</button>
<button type="submit" name="submit" class="btn btn-submit">Create Subcategory</button>
</div>
</form>
</div>
</div>
</div>
</div>
</div>
</div>


<div class="modal fade" id="edit-category">
<div class="modal-dialog modal-dialog-centered custom-modal-two">
<div class="modal-content">
<div class="page-wrapper-new p-0">
<div class="content">
<div class="modal-header border-0 custom-modal-header">
<div class="page-title">
<h4>Edit Sub Category</h4>
</div>
<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body custom-modal-body">
<form action="" method="POST" >
<div class="mb-3">
<label class="form-label">Parent Category</label>
<select id="editCategoryName" name="editCategory" class="select">
<?php while ($rowCategory2 = $resultCategory2->fetch_assoc() ) {?>
    <option value=<?php print_r($rowCategory2['category_id'])?>> <?php print_r($rowCategory2['category_name'])?> </option>
<?php }?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Category Name</label>
<input type="text" id="subcategoryName" name="editSubCategory" class="form-control">
</div>
<div class="mb-3">
<label class="form-label">Category Code</label>
<input type="text" id="subcategoryId" name="editSubCategoryId" class="form-control">
</div>
<div class="mb-3">
<label class="form-label">Status</label>
<select id="subcategoryStatus" name="statusEdit" class="select">
<option value="1">Enable</option>
<option value="0">Disabled</option>
</select>
</div>
<div class="mb-3 input-blocks">
<label class="form-label">Description</label>
<textarea id='subcategoryDescription' class="form-control"></textarea>
</div>
<div class="modal-footer-btn">
<button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">Cancel</button>
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
<script src="assets/js/feather.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/jquery.slimscroll.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/jquery.dataTables.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/dataTables.bootstrap5.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/bootstrap.bundle.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/plugins/select2/js/select2.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/plugins/sweetalert/sweetalert2.all.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/plugins/sweetalert/sweetalerts.min.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/theme-script.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/script.js" type="c3f7afb65ab6a608086a11a8-text/javascript"></script>
<script src="assets/js/rocket-loader-min.js" data-cf-settings="c3f7afb65ab6a608086a11a8-|49" defer=""></script>

<script>
  if ( window.history.replaceState ) 
  {
    window.history.replaceState( null, null, window.location.href );
  }
</script>

<script type="text/javascript">
  $(document).ready(function() {
    $('.editButton').on('click', function(event) {

        // Get subcategory data from the button
        let subcategoryId = $(this).data('subcategory-id');
        let categoryName = $(this).data('category-id');
        let subcategoryName = $(this).data('subcategory-name');
        let subcategoryStatus = $(this).data('subcategory-status');
        let subcategoryDescription = $(this).data('subcategory-description');

        console.log(subcategoryDescription);
        
        
        $('#subcategoryId').val(subcategoryId);
        $('#editCategoryName').val(categoryName);
        $('#subcategoryName').val(subcategoryName);
        $('#subcategoryStatus').val(subcategoryStatus);
        $('#subcategoryDescription').val(subcategoryDescription);

          // Reorder options to bring the selected role to the top
        let selectedOptionRoles = $('#editCategoryName option[value="' + categoryName + '"]');
        selectedOptionRoles.remove();
        selectedOptionRoles.prependTo($('#editCategoryName'));
        
        let selectedOptionStatus = $('#subcategoryStatus option[value="' + subcategoryStatus + '"]');
        selectedOptionStatus.remove();
        selectedOptionStatus.prependTo($('#subcategoryStatus'));
    });

  // Handle the click event on the delete button
  $('.deleteButton').on('click', function(event) {
    // Get the vendor ID from the data attribute
    let subcategoryId = $(this).data('subcategory-id');

    
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
            url: 'sub-categories.php', // The PHP file that will handle the deletion
            type: 'GET',
            data: { subcategoryId: subcategoryId },
            success: function(response) {
              // Show success message and reload the page
              Swal.fire(
                'Deleted!',
                'The Sub Category has been deleted.', 
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