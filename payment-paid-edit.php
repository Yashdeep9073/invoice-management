<?php
error_reporting(1);
session_start();

// Checking Session Value
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit();
}

// Database Connection
require "./database/config.php";

// Fetching existing billing info for the form
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Assuming the ID is passed in the URL query
    $query = "SELECT * FROM payment WHERE id = ?";

    if ($stmt = $db->prepare($query)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
        } else {
            die("Billing information not found.");
        }

        $stmt->close();
    } else {
        die("Error preparing statement: " . $db->error);
    }
}

// Updating Billing Info
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST['id']); // Ensure id is an integer

    // Initialize update fields
    $updateFields = [];
    $updateParams = [];
    $paramTypes = "";



    if (!empty($_POST['invoice_number'])) {
        $updateFields[] = "invoice_number = ?";
        $updateParams[] = trim($_POST['invoice_number']);
        $paramTypes .= "s"; // String
    }

    if (!empty($_POST['total_amount'])) {
        $updateFields[] = "total_amount = ?";
        $updateParams[] = floatval($_POST['total_amount']);
        $paramTypes .= "d"; // Float
    }

    if (isset($_POST['status'])) {
        $updateFields[] = "status = ?";
        $updateParams[] = trim($_POST['status']);
        $paramTypes .= "s"; // String
    }

    // Ensure at least one field is being updated
    if (!empty($updateFields)) {
        $updateQuery = "UPDATE payment SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $paramTypes .= "i"; // Integer for ID
        $updateParams[] = $id;

        // Prepare and execute the query
        if ($stmt = $db->prepare($updateQuery)) {
            $stmt->bind_param($paramTypes, ...$updateParams);

            if ($stmt->execute()) {
                header("Location: payment-paid.php"); // Redirect after successful update
                exit();
            } else {
                echo "Error updating billing information: " . $stmt->error;
            }

            $stmt->close();
        } else {
            die("Error preparing statement: " . $db->error);
        }
    } else {
        echo "No fields to update.";
    }
}

try {

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);
} catch (\Throwable $th) {
    //throw $th;
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
        }
    }
    ?>

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
</head>

<body>
    <!-- <div id="global-loader">
<div class="whirly-loader"> </div>
</div> -->

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
                            <h4>Update Payment</h4>
                            <h6>Update New Payment</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <div class="page-btn">
                                <a href="product-list.php" class="btn btn-secondary"><i data-feather="arrow-left"
                                        class="me-2"></i>Back to Billing Details</a>
                            </div>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>



                <form action="edit-status.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
                    <div class="card">
                        <div class="card-body add-product pb-0">
                            <div class="accordion-card-one accordion" id="accordionExample">
                                <div class="accordion-item">
                                    <div class="accordion-header" id="headingOne">
                                        <div class="accordion-button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseOne" aria-controls="collapseOne">
                                            <div class="addproduct-icon">
                                                <h5><i data-feather="info" class="add-info"></i><span>Billing
                                                        Details</span></h5>
                                                <a href="javascript:void(0);"><i data-feather="chevron-down"
                                                        class="chevron-down-add"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="collapseOne" class="accordion-collapse collapse show"
                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <div class="row">

                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Invoice Number:</label>
                                                        <input type="text" id="invoice_number" name="invoice_number"
                                                            class="form-control" style="border: 1px solid black;"
                                                            value="<?php echo htmlspecialchars($row['invoice_number']); ?>"
                                                            required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Total Amount:</label>
                                                        <input type="text" id="total_amount" name="total_amount"
                                                            class="form-control" style="border: 1px solid black;"
                                                            value="<?php echo htmlspecialchars($row['total_amount']); ?>">
                                                    </div>
                                                </div>
                                                <!-- <div class="col-lg-4 col-sm-6 col-12">
                                    <div class="mb-3 add-product">
                                        <label class="form-label">Payment Method:</label>
                                        <select id="payment_method" name="payment_method" class="form-control" style="border: 1px solid black;">
                                            <option value="Credit Card" <?php echo ($row['payment_method'] == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                                            <option value="Debit Card" <?php echo ($row['payment_method'] == 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                                            <option value="Net Banking" <?php echo ($row['payment_method'] == 'Net Banking') ? 'selected' : ''; ?>>Net Banking</option>
                                        </select>
                                    </div>
                                </div> -->
                                                <!-- <div class="col-lg-4 col-sm-6 col-12">
                                    <div class="mb-3 add-product">
                                        <label class="form-label">Transaction ID:</label>
                                        <input type="text" id="transaction_id" name="transaction_id" class="form-control" style="border: 1px solid black;" value="<?php echo htmlspecialchars($row['transaction_id']); ?>">
                                    </div>
                                </div> -->
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Payment Status</label>
                                                        <select id="payment_method" name="status" class="form-control"
                                                            style="border: 1px solid black;">
                                                            <option value="0" <?php echo (isset($row['status']) && $row['status'] == 0) ? 'selected' : ''; ?>>Pending
                                                            </option>
                                                            <option value="1" <?php echo (isset($row['status']) && $row['status'] == 1) ? 'selected' : ''; ?>>Paid</option>
                                                            <option value="2" <?php echo (isset($row['status']) && $row['status'] == 2) ? 'selected' : ''; ?>>Cancelled
                                                            </option>
                                                            <option value="3" <?php echo (isset($row['status']) && $row['status'] == 3) ? 'selected' : ''; ?>>Refunded
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>


                                                <div class="col-lg-12">
                                                    <div class="btn-addproduct mb-4">
                                                        <button type="submit" class="btn btn-submit">Update Billing
                                                            Info</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden ID field -->
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                </form>



            </div>
        </div>
    </div>




    <script src="assets/js/jquery-3.7.1.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

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

    <script src="assets/js/theme-script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
    <script src="assets/js/script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

    <script src="assets/js/rocket-loader-min.js" data-cf-settings="dadca703e9170cd1f69d6130-|49" defer=""></script>
</body>

</html>