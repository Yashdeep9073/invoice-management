<?php
session_start();

// Include database configuration
require "./database/config.php";

// Check database connection
if (!isset($db) || !$db) {
    die("Database connection failed.");
}

// Check if the admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Initialize form message variable
$formMessage = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $taxName = trim($_POST['tax_name']);
    $taxRate = (float) $_POST['tax_rate']; // Convert to a float value
    $status = trim($_POST['status']);

    // Validate required fields
    if (empty($taxName) || empty($taxRate) || empty($status)) {
        $formMessage = "All fields are required.";
    } else {
        // Prepare SQL query
        $query = "INSERT INTO tax (tax_name, tax_rate, status) VALUES (?, ?, ?)";

        // Prepare and execute the statement
        if ($stmt = $db->prepare($query)) {
            $stmt->bind_param(
                "sds", // Types: string (tax_name), double (tax_rate), string (status)
                $taxName,
                $taxRate,
                $status
            );

            try {
                if ($stmt->execute()) {
                    $formMessage = "Tax details saved successfully.";
                } else {
                    $formMessage = "Failed to save tax details: " . $stmt->error;
                }
            } catch (Exception $e) {
                $formMessage = "Error: " . htmlspecialchars($e->getMessage());
            }

            $stmt->close();
        } else {
            $formMessage = "Error preparing statement: " . $db->error;
        }
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
    <title>Add Tax</title>

    <link rel="shortcut icon" type="image/x-icon" href="assets/img/fav/vis-favicon.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/css/feather.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-message {
            padding: 10px;
            margin: 10px 0;
            background-color: rgb(230, 246, 199);
            /* Light green background */
            color: #33691e;
            /* Dark green text */
            border: 1px solid green;
            /* Border */
            border-radius: 5px;
        }
    </style>
    <style>
        /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Firefox */
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
    <style>
        select.form-control {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 30px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M12.1 5.3l-4 4a.5.5 0 0 1-.7 0l-4-4a.5.5 0 0 1 .7-.7L8 8.6l3.4-3.3a.5.5 0 0 1 .7.7z"></path></svg>');
            /* Add custom arrow */
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
    </style>
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"> </div>
    </div>

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
                            <h4>New Tax</h4>
                            <h6>Create New Tax</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <div class="page-btn">
                                <a href="tax-details.php" class="btn btn-secondary"><i data-feather="arrow-left"
                                        class="me-2"></i>Back to Tax Details</a>
                            </div>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>
                <?php if (!empty($formMessage)): ?>
                    <div id="formMessage" class="form-message">
                        <?= $formMessage; ?>
                    </div>
                <?php endif; ?>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="card">
                        <div class="card-body add-product pb-0">
                            <div class="accordion-card-one accordion" id="accordionExample">
                                <div class="accordion-item">
                                    <div class="accordion-header" id="headingOne">
                                        <div class="accordion-button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseOne" aria-controls="collapseOne">
                                            <div class="addproduct-icon">
                                                <h5><i data-feather="info" class="add-info"></i><span>Tax Details</span>
                                                </h5>
                                                <a href="javascript:void(0);"><i data-feather="chevron-down"
                                                        class="chevron-down-add"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="collapseOne" class="accordion-collapse collapse show"
                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <!-- Tax Name -->
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Tax Name</label>
                                                        <input type="text" id="tax_name" name="tax_name"
                                                            placeholder="Enter Tax Name" class="form-control" required>
                                                    </div>
                                                </div>
                                                <!-- Tax Rate -->
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Tax Rate</label>
                                                        <input type="text" id="tax_rate" name="tax_rate"
                                                            placeholder="Enter Tax Rate" class="form-control" required>
                                                    </div>
                                                </div>
                                                <!-- Status -->
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">

                                                        <label class="form-label" for="name">Status
                                                            </span></label>
                                                        <select id="" name="status" class="form-control"
                                                            oninvalid="this.setCustomValidity('Please Select Status')"
                                                            oninput="setCustomValidity('')" required>
                                                            <option value="">Choose</option>
                                                            <option value="1">Enable</option>
                                                            <option value="0">Disable</option>

                                                        </select>
                                                    </div>
                                                </div>
                                                <!-- Submit Button -->
                                                <div class="col-lg-12">
                                                    <div class="btn-addproduct mb-4">
                                                        <button type="submit" class="btn btn-primary btn-block">Save Tax
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
                </form>


            </div>
        </div>
    </div>



    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/feather.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/jquery.slimscroll.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/jquery.dataTables.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/bootstrap.bundle.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/plugins/select2/js/select2.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/moment.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js"
        type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/theme-script.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/script.js" type="4d1dcc7bc385033e88cb5c1e-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="4d1dcc7bc385033e88cb5c1e-|49" defer=""></script>

    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <script type="text/javascript">
        $(document).ready(function () {
            $('#generateCode').on('click', function (event) {
                event.preventDefault();

                // Get the product name from the input field
                var name = $("#productName").val();

                // Check if the product name is not empty
                if (name !== "") {
                    // Generate a random number between 10000 and 99999
                    var randomNumber = Math.floor(Math.random() * 90000) + 10000;

                    // Generate a timestamp (Unix time in seconds)
                    var timestamp = Math.floor(Date.now() / 1000);

                    // Concatenate the product name with the random number and display it
                    $('#skuDisplay').val(name + "-" + randomNumber + "-" + timestamp);
                } else {
                    // If the name is empty, do not add anything to #skuDisplay
                    $('#skuDisplay').val(randomNumber); // Optionally, display only the random number
                }
            });

            $("#productDiscountType").on('change', function () {

                let productDiscountType = this.value;
                let productDiscountValue = $("#productDiscountValue").val();
                let productPrice = $('#productPrice').val();

                if (productDiscountType == "Percentage") {

                    // console.log(productPrice);
                    // console.log(productDiscountValue);
                    // console.log(productDiscountType);
                    let percentage = productDiscountValue / 100;

                    let discountPrice = percentage * productPrice;

                    let productNetprice = productPrice - discountPrice;

                    $('#productNetprice').val(productNetprice);

                } if (productDiscountType == "Cash") {

                    // console.log(productPrice);
                    // console.log(productDiscountValue);
                    // console.log(productDiscountType);

                    let productNetprice = productPrice - productDiscountValue;

                    $('#productNetprice').val(productNetprice);
                }

            });
        });
        // });
    </script>
    <script>
        document.getElementById('price').addEventListener('input', calculateTotal);
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('gst').addEventListener('input', calculateTotal);

        function calculateTotal() {
            const price = parseFloat(document.getElementById('price').value) || 0;
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const gst = parseFloat(document.getElementById('gst').value) || 0;

            const subTotal = price * quantity;
            const gstAmount = (subTotal * gst) / 100;
            const totalAmount = subTotal + gstAmount;

            document.getElementById('total_amount').value = totalAmount.toFixed(2);
        }
    </script>
    <script>
        function calculateRowTotal(row) {
            const price = parseFloat(row.querySelector('[name="product_price[]"]').value) || 0;
            const quantity = parseFloat(row.querySelector('[name="product_quantity[]"]').value) || 0;
            const gst = parseFloat(row.querySelector('[name="product_gst[]"]').value) || 0;

            const subTotal = price * quantity;
            const gstAmount = (subTotal * gst) / 100;
            const totalAmount = subTotal + gstAmount;

            row.querySelector('[name="product_total_amount[]"]').value = totalAmount.toFixed(2);
        }

        function calculateGrandTotal() {
            let grandTotal = 0;

            document.querySelectorAll('.row').forEach(row => {
                calculateRowTotal(row);
                const rowTotal = parseFloat(row.querySelector('[name="product_total_amount[]"]').value) || 0;
                grandTotal += rowTotal;
            });

            document.getElementById('overall_total').value = grandTotal.toFixed(2);
        }

        // Attach event listeners to dynamically added rows
        document.addEventListener('input', function (event) {
            if (event.target.closest('.row')) {
                calculateGrandTotal();
            }
        });
    </script>
    <script>
        // Hide the form message after 5 seconds
        setTimeout(function () {
            const formMessage = document.getElementById('formMessage');
            if (formMessage) {
                formMessage.style.display = 'none';
            }
        }, 8000); // 8000 milliseconds = 8 seconds
    </script>



</body>

</html>