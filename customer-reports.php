<?php
ob_start();
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}
require "./database/config.php";

try {
    $stmtFetch = $db->prepare("SELECT 
    c.*,
    COALESCE(SUM(CASE WHEN i.status = 'PAID' THEN i.total_amount ELSE 0 END), 0) AS total_paid,
    COALESCE(SUM(CASE WHEN i.status = 'PENDING' THEN i.total_amount ELSE 0 END), 0) AS total_pending
FROM 
    customer c
LEFT JOIN 
    invoice i ON c.customer_id = i.customer_id
GROUP BY 
    c.customer_id, c.customer_name
ORDER BY 
    c.customer_name;

    ");
    $stmtFetch->execute();
    $customers = $stmtFetch->get_result();

    $stmtFetchState = $db->prepare("SELECT * FROM state");
    $stmtFetchState->execute();
    $states = $stmtFetchState->get_result();





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
    <title>Customers</title>

    <link rel="shortcut icon" type="image/x-icon" href="assets/img/fav/vis-favicon.png">

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
                            <h4>Customer List</h4>
                            <h6>Manage Your Customer</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
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
                                        <th>Email</th>
                                        <th>Total Paid</th>
                                        <th>Total Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers->fetch_all(MYSQLI_ASSOC) as $customer) { ?>
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
                                                        <a class="text-primary"
                                                            href="view-customer-report.php?id=<?php echo base64_encode($customer['customer_id']) ?>"><?php echo $customer['customer_name'] ?></a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $customer['customer_phone'] ?></td>
                                            <td>
                                                <?php echo $customer['customer_email'] ?>
                                            </td>
                                            <td>
                                                ₹<?php echo $customer['total_paid'] ?>
                                            </td>
                                            <td>
                                                ₹<?php echo $customer['total_pending'] ?>
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
                                <h4>Add Customer</h4>
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
                                            <label>Customer Name</label>
                                            <input type="text" class="form-control" name="customerName"  required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Phone</label>
                                            <input type="tel" class="form-control" name="customerPhone" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Email</label>
                                            <input type="email" class="form-control" name="customerEmail" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer State</label>
                                            <select class=" form-select" id="customerState" name="customerState"
                                                required>
                                                <option>Select</option>
                                                <?php foreach ($states as $state) { ?>
                                                    <option value="<?php echo $state['state_code'] ?>">
                                                        <?php echo $state['state_name'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer City</label>
                                            <select class="form-select" id="customerCity" name="customerCity" required>

                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Address</label>
                                            <input type="text" class="form-control" name="customerAddress" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Name</label>
                                            <input type="text" class="form-control" name="shippingName" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Phone</label>
                                            <input type="tel" class="form-control" name="shippingPhone" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Email</label>
                                            <input type="email" class="form-control" name="shippingEmail" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Address</label>
                                            <input type="text" class="form-control" name="shippingAddress" required>
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


    <div class="modal fade" id="edit-units">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Edit Customer</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <input type="hidden" name="editCustomerId" id="editCustomerId">
                                <div class="row">

                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Name</label>
                                            <input type="text" class="form-control" name="editCustomerName"
                                                id="editCustomerName" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Phone</label>
                                            <input type="tel" class="form-control" name="editCustomerPhone"
                                                id="editCustomerPhone" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Email</label>
                                            <input type="email" class="form-control" name="editCustomerEmail"
                                                id="editCustomerEmail" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer State</label>
                                            <select class="form-select" id="editCustomerState" name="editCustomerState"
                                                required>
                                                <?php foreach ($states as $state) { ?>
                                                    <option value="<?php echo $state['state_code'] ?>">
                                                        <?php echo $state['state_name'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer City</label>
                                            <select class=" form-select" id="editCustomerCity" name="editCustomerCity"
                                                required>

                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Address</label>
                                            <input type="text" class="form-control" name="editCustomerAddress"
                                                id="editCustomerAddress" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Name</label>
                                            <input type="text" class="form-control" name="editShippingName"
                                                id="editShippingName" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Phone</label>
                                            <input type="tel" class="form-control" name="editShippingPhone"
                                                id="editShippingPhone" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Email</label>
                                            <input type="email" class="form-control" name="editShippingEmail"
                                                id="editShippingEmail" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Address</label>
                                            <input type="text" class="form-control" name="editShippingAddress"
                                                id="editShippingAddress" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Status</label>
                                            <select class="form-select" name="editCustomerStatus"
                                                id="editCustomerStatus">
                                                <option value="1">Enabled</option>
                                                <option value="0">Disabled</option>
                                            </select>
                                        </div>
                                    </div>


                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
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

    <script src="assets/js/feather.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>

    <script src="assets/js/script.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/custom-select2.js" type="85b95337cd86ef30623c36b5-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="85b95337cd86ef30623c36b5-|49" defer=""></script>
    <script src="assets/js/custom.js"></script>
    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="dadca703e9170cd1f69d6130-text/javascript"></script>
    <script src="assets/js/moment.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

    <script src="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js"
        type="dadca703e9170cd1f69d6130-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>

    <script src="assets/js/script.js" type="dadca703e9170cd1f69d6130-text/javascript"></script>

    <script src="assets/js/custom.js"></script>



    <script>
        $(document).ready(function (e) {


            $(document).on('click', '.editButton', function () {

                let customerId = $(this).data("customer-id");
                let customerName = $(this).data("customer-name");
                let customerPhone = $(this).data("customer-phone");
                let customerEmail = $(this).data("customer-email");
                let customerAddress = $(this).data("customer-address");
                let shippingName = $(this).data("shipping-name");
                let shippingPhone = $(this).data("shipping-phone");
                let shippingEmail = $(this).data("shipping-email");
                let shippingAddress = $(this).data("shipping-address");
                let customerStatus = $(this).data("customer-status");
                let customerState = $(this).data("customer-state");
                let customerCity = $(this).data("customer-city");



                $('#editCustomerId').val(customerId);
                $('#editCustomerName').val(customerName);
                $('#editCustomerPhone').val(customerPhone);
                $('#editCustomerEmail').val(customerEmail);
                $('#editCustomerAddress').val(customerAddress);
                $('#editShippingName').val(shippingName);
                $('#editShippingPhone').val(shippingPhone);
                $('#editShippingEmail').val(shippingEmail);
                $('#editShippingAddress').val(shippingAddress);
                $('#editCustomerStatus').val(customerStatus);
                $('#editCustomerState').val(customerState);
                $('#editCustomerCity').val(customerCity);

            });


            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let customerId = $(this).data('customer-id');

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
                            url: 'customer-details.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { customerId: customerId },
                            success: function (response) {

                                let result = JSON.parse(response);
                                console.log(result);

                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The User has been deleted.',
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

            $(document).on('change', '#customerState', function (e) {
                e.preventDefault();

                let stateCode = $(this).val();
                $.ajax({
                    url: 'customer-details.php', // The PHP file that will handle the deletion
                    type: 'POST',
                    data: { stateCode: stateCode },
                    success: function (response) {

                        let result = JSON.parse(response);

                        if (result.status === 200) {
                            let citySelect = $('#customerCity');
                            citySelect.empty(); // Clear previous options
                            citySelect.append('<option value="">Select City</option>'); // Default option

                            result.data.forEach(function (city) {
                                citySelect.append('<option value="' + city.city_id + '">' + city.city_name + '</option>');
                                console.log(city.city_name);

                            });

                            // Refresh Select2 (if applied)
                            citySelect.trigger('change');
                        } else {
                            console.warn('No cities found or server error');
                        }


                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                        console.error(xhr);
                        console.error(status);

                    }
                });

            });

            $(document).on('change', '#editCustomerState', function (e) {
                e.preventDefault();

                let stateCode = $(this).val();
                $.ajax({
                    url: 'customer-details.php', // The PHP file that will handle the deletion
                    type: 'POST',
                    data: { stateCode: stateCode },
                    success: function (response) {

                        let result = JSON.parse(response);

                        if (result.status === 200) {
                            let citySelect = $('#editCustomerCity');
                            citySelect.empty(); // Clear previous options
                            citySelect.append('<option value="">Select City</option>'); // Default option

                            result.data.forEach(function (city) {
                                citySelect.append('<option value="' + city.city_id + '">' + city.city_name + '</option>');
                                console.log(city.city_name);

                            });

                            // Refresh Select2 (if applied)
                            citySelect.trigger('change');
                        } else {
                            console.warn('No cities found or server error');
                        }


                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                        console.error(xhr);
                        console.error(status);

                    }
                });

            })

        })
    </script>


</body>

</html>