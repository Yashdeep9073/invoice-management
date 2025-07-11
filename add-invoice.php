<?php
session_start();

require "./database/config.php";
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}


try {

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchCustomers = $db->prepare("SELECT * FROM customer WHERE isActive = 1");
    if ($stmtFetchCustomers->execute()) {
        $customers = $stmtFetchCustomers->get_result();
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

    $stmtFetchService = $db->prepare("SELECT * FROM services WHERE isActive = 1");
    if ($stmtFetchService->execute()) {
        $services = $stmtFetchService->get_result();
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

    $stmtFetchTax = $db->prepare("SELECT tax_id, tax_name, tax_rate FROM tax WHERE status = 1");
    if ($stmtFetchTax->execute()) {
        $taxOptions = $stmtFetchTax->get_result();
    }

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);
    $timezone = $localizationSettings["timezone"] ?? "UTC";


} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}



if (isset($_POST['invoiceNumber']) && $_SERVER['REQUEST_METHOD'] == "POST") {
    // Get current date in YYYYMMDD format
    $date = date('Ymd'); // e.g., '20250530'

    // Use a transaction to ensure atomicity
    try {
        $db->begin_transaction();

        $stmtFetchInvoiceSettings = $db->prepare("SELECT * FROM invoice_settings");
        $stmtFetchInvoiceSettings->execute();
        $invoiceSettings = $stmtFetchInvoiceSettings->get_result()->fetch_array(MYSQLI_ASSOC);
        $prefix = isset($invoiceSettings['invoice_prefix']) ? $invoiceSettings['invoice_prefix'] : "VIS";

        // Lock the row for the current date
        $stmt = $db->prepare("SELECT last_sequence FROM invoice_sequence WHERE date = ? FOR UPDATE");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // No sequence for today, create one
            $stmt = $db->prepare("INSERT INTO invoice_sequence (date, last_sequence) VALUES (?, 0)");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $lastSequence = 0;
        } else {
            $row = $result->fetch_assoc();
            $lastSequence = $row['last_sequence'];
        }

        // Increment sequence
        $newSequence = $lastSequence + 1;

        // Update sequence
        $stmt = $db->prepare("UPDATE invoice_sequence SET last_sequence = ? WHERE date = ?");
        $stmt->bind_param("is", $newSequence, $date);
        $stmt->execute();

        $db->commit();

        // Format invoice number
        $invoiceNumber = sprintf("$prefix-%s-%05d", $date, $newSequence); // e.g., VIS-20250530-00001
        echo json_encode([
            "status" => 201,
            "data" => $invoiceNumber
        ]);
        exit;
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode([
            "status" => 500,
            "error" => $e->getMessage()
        ]);
        exit;
    }
}



if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['submit'])) {
    try {
        // echo "<pre>";
        // print_r($_POST);
        // exit();

        $invoiceNumber = htmlspecialchars($_POST['invoice_number'] ?? '', ENT_QUOTES, 'UTF-8');
        $paymentMethod = htmlspecialchars($_POST['payment_method'] ?? '', ENT_QUOTES, 'UTF-8');
        $transactionId = htmlspecialchars($_POST['transaction_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8');
        $dueDate = htmlspecialchars($_POST['due_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $fromDate = htmlspecialchars($_POST['from_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $toDate = htmlspecialchars($_POST['to_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $invoiceType = htmlspecialchars($_POST['invoice_type'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerId = filter_input(INPUT_POST, 'customerName', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $tax = filter_input(INPUT_POST, 'tax', FILTER_VALIDATE_INT);
        $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $invoiceTitle = htmlspecialchars($_POST["invoice_title"], ENT_QUOTES, 'UTF-8');
        $repeatCycle = htmlspecialchars($_POST["repeatCycle"], ENT_QUOTES, 'UTF-8');
        $createBefore = htmlspecialchars($_POST["createBefore"], ENT_QUOTES, 'UTF-8');

        $createdBy = base64_decode($_SESSION['admin_id']);

        // Handle serviceName array
        $serviceIds = isset($_POST['serviceName']) && is_array($_POST['serviceName'])
            ? array_map('intval', $_POST['serviceName']) // Sanitize to integers
            : [];
        $serviceIdsJson = json_encode($serviceIds); // Convert to JSON string, e.g., "[2, 3, 5]"

        // Validate required fields
        if (
            empty($invoiceNumber) || empty($paymentMethod) || empty($status) ||
            empty($dueDate) || empty($customerId) || empty($amount) || empty($quantity) ||
            empty($totalAmount) || empty($serviceIds)
        ) {
            throw new Exception('All required fields must be filled, and at least one service must be selected.');
        }

        // Validate payment_method and status enums
        $validPaymentMethods = ['CREDIT_CARD', 'DEBIT_CARD', 'CASH', 'NET_BANKING', 'PAYPAL', 'OTHER'];

        $validStatuses = ['PAID', 'PENDING', 'CANCELLED', 'REFUNDED'];

        $validTypes = ['FIXED', 'RECURSIVE'];

        if (!in_array($paymentMethod, $validPaymentMethods)) {
            throw new Exception('Invalid payment method.');
        }
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status.');
        }
        if (!in_array($invoiceType, $validTypes)) {
            throw new Exception('Invalid Types.');
        }

        // Prepare and execute the SQL query
        $sql = "INSERT INTO `invoice` (
            `invoice_number`, `payment_method`, `transaction_id`, `status`, 
            `amount`, `quantity`, `tax`, `discount`, `total_amount`, 
            `due_date`, `from_date`,`to_date` , `customer_id`, `service_id`, `description`,`created_by`,`invoice_type`,`invoice_title`,`repeat_cycle`,`create_before`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $db->error);
        }
        $stmt->bind_param(
            'ssssdiiddsssississsi',
            $invoiceNumber,
            $paymentMethod,
            $transactionId,
            $status,
            $amount,
            $quantity,
            $tax,
            $discount,
            $totalAmount,
            $dueDate,
            $fromDate,
            $toDate,
            $customerId,
            $serviceIdsJson,
            $description,
            $createdBy,
            $invoiceType,
            $invoiceTitle,
            $repeatCycle,
            $createBefore
        );

        // Execute the query
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $stmt->close();

        $_SESSION['success'] = 'Invoice created successfully!';
        header("Location: manage-invoice.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
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
    <title>Create Invoice</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
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
                            <h4>New Invoice</h4>
                            <h6>Create New Invoice</h6>
                        </div>
                    </div>

                </div>
                <div class="page-header">
                    <ul class="table-top-head">
                        <li>
                            <div class="page-btn">
                                <a href="manage-invoice.php" class="btn btn-secondary"><i data-feather="arrow-left"
                                        class="me-2"></i>Back to
                                    Invoices</a>
                            </div>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>

                <form action="" method="post">
                    <div class="card">
                        <div class="card-body add-product pb-0">
                            <div class="accordion-card-one accordion" id="accordionExample">
                                <div class="accordion-item">
                                    <div class="accordion-header" id="headingOne">
                                        <div class="accordion-button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseOne" aria-controls="collapseOne">
                                            <div class="addproduct-icon">
                                                <h5><i data-feather="info" class="add-info"></i><span>Invoice
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
                                                    <div class="input-blocks add-product list">
                                                        <label class="form-label">Invoice Title </label>
                                                        <input type="text" id="invoice_title" name="invoice_title"
                                                            placeholder="Enter Invoice Title" class="form-control">
                                                    </div>
                                                </div>

                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="input-blocks add-product list">
                                                        <label class="form-label">Invoice Number <span> *</span></label>
                                                        <input type="text" id="invoice_number" name="invoice_number"
                                                            placeholder="Enter Invoice Number" class="form-control"
                                                            required>
                                                        <button type="button"
                                                            class="btn-2 btn-primaryadd invoiceNumber">
                                                            Generate
                                                        </button>
                                                    </div>
                                                </div>


                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Payment Method: <span>
                                                                *</span></label>
                                                        <select id="payment_method" name="payment_method"
                                                            class="form-control" required>
                                                            <option>Select Method</option>
                                                            <option value="CASH">Cash</option>
                                                            <option value="CREDIT_CARD">Credit Card</option>
                                                            <option value="DEBIT_CARD">Debit Card</option>
                                                            <option value="NET_BANKING">Net Banking</option>
                                                            <option value="PAYPAL">Paypal</option>
                                                            <option value="OTHER">Other</option>
                                                        </select>
                                                    </div>
                                                </div>


                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="input-blocks add-product list">
                                                        <label>Transaction ID: <span> *</span></label>
                                                        <input type="text" id="transaction_id" name="transaction_id"
                                                            placeholder="Enter Transaction ID" class="form-control">
                                                        <button type="button"
                                                            class="btn-2 btn-primaryadd transactionNumber">
                                                            Generate
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Payment Status <span> *</span></label>
                                                        <select id="payment_status" name="status" class="form-control"
                                                            required>
                                                            <option>Select Status</option>
                                                            <option value="PAID">Paid</option>
                                                            <option value="PENDING">Pending</option>
                                                            <option value="CANCELLED">Cancelled</option>
                                                            <option value="REFUNDED">Refunded</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Due Date: <span> *</span></label>
                                                        <input type="date" id="due_date" name="due_date"
                                                            placeholder="Enter Due Date" class="form-control"
                                                            autocomplete="off" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Customer <span> *</span></label>

                                                        <select class="select2 form-control" id="" name="customerName"
                                                            required>
                                                            <option>Select Customer</option>
                                                            <?php foreach ($customers->fetch_all(MYSQLI_ASSOC) as $customer) { ?>
                                                                <option value="<?php echo $customer['customer_id']; ?>">
                                                                    <?php echo $customer['customer_name'] . " | " . $customer['customer_phone'] . " | " . $customer['customer_email']; ?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>


                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Services <span> *</span></label>

                                                        <select class="select2 form-select" name="serviceName[]"
                                                            multiple="multiple">
                                                            <option value="">Select Service</option>
                                                            <?php foreach ($services->fetch_all(MYSQLI_ASSOC) as $service) { ?>
                                                                <option value="<?php echo $service['service_id']; ?>">
                                                                    <?php echo $service['service_name']; ?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>

                                                    </div>
                                                </div>

                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Type : <span>
                                                                *</span></label>
                                                        <select id="invoice_type" name="invoice_type"
                                                            class="form-control" required>
                                                            <option>Select</option>
                                                            <option value="FIXED">Fixed</option>
                                                            <option value="RECURSIVE">Recursive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12 from-date apexcharts-toolbar">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">From Date: <span> *</span></label>
                                                        <input type="date" id="from_date" name="from_date"
                                                            placeholder="Enter From Date" class="form-control"
                                                            autocomplete="off">
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12 to-date apexcharts-toolbar">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">To Date: <span> *</span></label>
                                                        <input type="date" id="to_date" name="to_date"
                                                            placeholder="Enter To Date" class="form-control"
                                                            autocomplete="off">
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12 repeat-cycle apexcharts-toolbar">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Repeat Cycle: <span> *</span></label>
                                                        <select class="form-select" name="repeatCycle" id="repeatCycle">
                                                            <option>Select</option>
                                                            <option value="DAILY">Every day (daily)</option>
                                                            <option value="WEEKLY">Every 7 days (weekly)</option>
                                                            <option value="MONTHLY">Every month (monthly)</option>
                                                            <option value="QUARTERLY">Every 3 months (quarterly)
                                                            </option>
                                                            <option value="SEMIQUARTERLY">Every 6 months (semiannually)
                                                            </option>
                                                            <option value="ANNUALLY">Every year (annually)</option>
                                                            <option value="BIENNIALLY">Every 2 years (biennially)
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12 create-before apexcharts-toolbar">
                                                    <div class="mb-3 add-product">
                                                        <label class="form-label">Create before (days): <span>
                                                                *</span></label>
                                                        <input type="number" min="1" id="createBefore"
                                                            name="createBefore" placeholder="Enter To Date"
                                                            class="form-control" autocomplete="off">
                                                    </div>
                                                </div>


                                                <div class="col-lg-12">
                                                    <div class="input-blocks summer-description-box transfer mb-3">
                                                        <label>Description</label>
                                                        <textarea class="form-control h-100" name="description"
                                                            rows="5"></textarea>
                                                        <p class="mt-1">Maximum 60 Characters</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-card-one accordion" id="accordionExample2">
                                <div class="accordion-item">
                                    <div class="accordion-header" id="headingTwo">
                                        <div class="accordion-button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseTwo" aria-controls="collapseTwo">
                                            <div class="text-editor add-list">
                                                <div class="addproduct-icon list icon">
                                                    <h5>
                                                        <i data-feather="life-buoy" class="add-info"></i><span>Amount
                                                            Details </span>
                                                    </h5>
                                                    <a href="javascript:void(0);"><i data-feather="chevron-down"
                                                            class="chevron-down-add"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="collapseTwo" class="accordion-collapse collapse show"
                                        aria-labelledby="headingTwo" data-bs-parent="#accordionExample2">
                                        <div class="accordion-body">

                                            <div class="tab-content" id="pills-tabContent">
                                                <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                                                    aria-labelledby="pills-home-tab">
                                                    <div class="row">
                                                        <div class="col-lg-3 col-sm-6 col-12">
                                                            <div class="mb-3 add-product">
                                                                <label class="form-label">Amount <span> *</span></label>
                                                                <input type="number" id="invoiceAmount" name="amount"
                                                                    class="form-control" placeholder="Enter Price"
                                                                    required>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-3 col-sm-6 col-12">
                                                            <div class="mb-3 add-product">
                                                                <label class="form-label">Quantity <span>
                                                                        *</span></label>
                                                                <input type="number" id="quantity" name="quantity"
                                                                    class="form-control" placeholder="Enter Quantity"
                                                                    value="1" min="1" step="1" style="appearance: auto;"
                                                                    required>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-3 col-sm-6 col-12">
                                                            <div class="mb-3 add-product">
                                                                <label class="form-label">Tax (%) <span>
                                                                        *</span></label>
                                                                <select id="tax" name="tax" class="form-control"
                                                                    required>
                                                                    <option>Select Tax Rate
                                                                    </option>
                                                                    <?php foreach ($taxOptions->fetch_all(MYSQLI_ASSOC) as $tax) { ?>
                                                                        <option
                                                                            data-value="<?= htmlspecialchars($tax['tax_rate']) ?>"
                                                                            value="<?= htmlspecialchars($tax['tax_id']) ?>">
                                                                            <?= htmlspecialchars($tax['tax_name']) ?>
                                                                            (<?= htmlspecialchars($tax['tax_rate']) ?>)
                                                                        </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-3 col-sm-6 col-12">
                                                            <div class="mb-3 add-product">
                                                                <label class="form-label">Discount (%)</label>
                                                                <input type="number" id="discount" value="0"
                                                                    name="discount" class="form-control"
                                                                    placeholder="Enter Discount">
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-3 col-sm-6 col-12">
                                                            <div class="mb-3 add-product">
                                                                <label class="form-label">Total Amount</label>
                                                                <input type="text" id="total_amount" name="total_amount"
                                                                    class="form-control"
                                                                    placeholder="Enter Total Amount" readonly>
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
                    <div class="col-lg-12">
                        <div class="btn-addproduct mb-4">
                            <button type="button" class="btn btn-cancel me-2">
                                Cancel
                            </button>
                            <button type="submit" name="submit" class="btn btn-submit">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
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


    <script type="text/javascript">
        $(document).ready(function () {
            $(document).on('click', '.transactionNumber', function (event) {
                event.preventDefault();

                // Generate transaction ID in format TX-YYYYMMDD-RRRRR-XXXX
                function generateTransactionId() {
                    // Get current date in YYYYMMDD format
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are 0-based
                    const day = String(now.getDate()).padStart(2, '0');
                    const dateStr = `${year}${month}${day}`;

                    // Generate 5-digit random number (10000–99999)
                    const randomNumber = Math.floor(Math.random() * 90000) + 10000;

                    // Generate 4-character random alphanumeric suffix
                    const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    let suffix = '';
                    for (let i = 0; i < 4; i++) {
                        suffix += chars.charAt(Math.floor(Math.random() * chars.length));
                    }

                    // Combine parts
                    return `TX-${dateStr}-${randomNumber}-${suffix}`;
                }

                // Set transaction ID in the input field
                $('#transaction_id').val(generateTransactionId());
            });



            $(document).on('click', '.invoiceNumber', async function (event) {

                try {
                    event.preventDefault();
                    let invoiceNumber = 1;

                    const response = await $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { invoiceNumber: invoiceNumber },
                    });

                    let result = JSON.parse(response);

                    // Set transaction ID in the input field
                    $('#invoice_number').val(result.data);
                } catch (error) {
                    console.error('Error fetching invoice data:', error);
                }

            });


            $(document).on('input', '#quantity', function (event) {
                event.preventDefault();

                function calculateTotal() {
                    const amount = parseFloat($('#invoiceAmount').val()) || 0;
                    const quantity = parseInt($('#quantity').val()) || 1;
                    const taxRate = parseFloat($('#tax option:selected').attr('data-value')) / 100 || 0;
                    const discount = parseFloat($('#discount').val()) / 100 || 0;

                    // Calculate subtotal (amount × quantity)
                    const subtotal = amount * quantity;
                    // Apply tax (subtotal × tax%)
                    const taxAmount = subtotal * taxRate;
                    // Apply discount (subtotal × discount%)
                    const discountAmount = subtotal * discount;
                    // Final total (subtotal + tax - discount)
                    const total = subtotal + taxAmount - discountAmount;

                    return total.toFixed(2); // Return with 2 decimal places
                }

                // Update total amount
                $('#total_amount').val(calculateTotal());
            });
            $(document).on('input', '#discount', function (event) {
                event.preventDefault();

                function calculateTotal() {
                    const amount = parseFloat($('#invoiceAmount').val()) || 0;
                    const quantity = parseInt($('#quantity').val()) || 1;
                    const taxRate = parseFloat($('#tax option:selected').attr('data-value')) / 100 || 0;
                    const discount = parseFloat($('#discount').val()) / 100 || 0;

                    // Calculate subtotal (amount × quantity)
                    const subtotal = amount * quantity;
                    // Apply tax (subtotal × tax%)
                    const taxAmount = subtotal * taxRate;
                    // Apply discount (subtotal × discount%)
                    const discountAmount = subtotal * discount;
                    // Final total (subtotal + tax - discount)
                    const total = subtotal + taxAmount - discountAmount;

                    return total.toFixed(2); // Return with 2 decimal places
                }

                // Update total amount
                $('#total_amount').val(calculateTotal());
            });

            $(document).on('input', '#tax', function (event) {
                event.preventDefault();

                function calculateTotal() {
                    const amount = parseFloat($('#invoiceAmount').val()) || 0;
                    const quantity = parseInt($('#quantity').val()) || 1;
                    const taxRate = parseFloat($('#tax option:selected').attr('data-value')) / 100 || 0;
                    const discount = parseFloat($('#discount').val()) / 100 || 0;

                    // Calculate subtotal (amount × quantity)
                    const subtotal = amount * quantity;
                    // Apply tax (subtotal × tax%)
                    const taxAmount = subtotal * taxRate;
                    // Apply discount (subtotal × discount%)
                    const discountAmount = subtotal * discount;
                    // Final total (subtotal + tax - discount)
                    const total = subtotal + taxAmount - discountAmount;

                    return total.toFixed(2); // Return with 2 decimal places
                }

                // Update total amount
                $('#total_amount').val(calculateTotal());
            });

            $(document).on('input', '#invoiceAmount', function (event) {
                event.preventDefault();

                function calculateTotal() {
                    const amount = parseFloat($('#invoiceAmount').val()) || 0;
                    const quantity = parseInt($('#quantity').val()) || 1;
                    const taxRate = parseFloat($('#tax option:selected').attr('data-value')) / 100 || 0;
                    const discount = parseFloat($('#discount').val()) / 100 || 0;

                    // Calculate subtotal (amount × quantity)
                    const subtotal = amount * quantity;
                    // Apply tax (subtotal × tax%)
                    const taxAmount = subtotal * taxRate;
                    // Apply discount (subtotal × discount%)
                    const discountAmount = subtotal * discount;
                    // Final total (subtotal + tax - discount)
                    const total = subtotal + taxAmount - discountAmount;

                    return total.toFixed(2); // Return with 2 decimal places
                }

                // Update total amount
                $('#total_amount').val(calculateTotal());
            });

            $(document).on('change', "#invoice_type", function (e) {
                const selectedType = $(this).val();

                if (selectedType === 'FIXED') {
                    $('.from-date').addClass('apexcharts-toolbar');
                    $('.to-date').addClass('apexcharts-toolbar');
                    $('.repeat-cycle').addClass('apexcharts-toolbar');
                    $('.create-before').addClass('apexcharts-toolbar');
                } else if (selectedType === 'RECURSIVE') {
                    $('.from-date').removeClass('apexcharts-toolbar');
                    $('.to-date').removeClass('apexcharts-toolbar');
                    $('.repeat-cycle').removeClass('apexcharts-toolbar');
                    $('.create-before').removeClass('apexcharts-toolbar');
                }
            })
        });
    </script>


</body>

</html>