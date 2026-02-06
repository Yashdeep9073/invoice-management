<?php
ob_start();
session_start();
require './utility/env.php';
require './utility/fileUploader.php';
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}
require "./database/config.php";
require './utility/formatDateTime.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
$uploadDir = "public/upload/customer/image/";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customerName'])) {

    try {

        // Validate required fields
        $required_fields = ['customerName', 'customerPhone', 'customerEmail', 'customerAddress'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                echo json_encode([
                    "status" => 400,
                    "error" => "Missing required field: " . $field
                ]);
                exit;
            }
        }

        $customerName = filter_input(INPUT_POST, 'customerName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $customerPhone = filter_input(INPUT_POST, 'customerPhone', FILTER_SANITIZE_NUMBER_INT);
        $customerEmail = filter_input(INPUT_POST, 'customerEmail', FILTER_SANITIZE_EMAIL);
        $customerAddress = filter_input(INPUT_POST, 'customerAddress', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Get shipping data (will be null if not provided)
        $shippingName = isset($_POST['shippingName']) ? filter_input(INPUT_POST, 'shippingName', FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
        $shippingPhone = isset($_POST['shippingPhone']) ? filter_input(INPUT_POST, 'shippingPhone', FILTER_SANITIZE_NUMBER_INT) : null;
        $shippingEmail = isset($_POST['shippingEmail']) ? filter_input(INPUT_POST, 'shippingEmail', FILTER_SANITIZE_EMAIL) : null;
        $shippingAddress = isset($_POST['shippingAddress']) ? filter_input(INPUT_POST, 'shippingAddress', FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;

        $customerState = filter_input(INPUT_POST, 'customerState', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $customerCity = filter_input(INPUT_POST, 'customerCity', FILTER_SANITIZE_NUMBER_INT);

        $gstNumber = !empty($_POST['gstNumber']) ? filter_input(INPUT_POST, 'gstNumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;


        // Validation Patterns
        $namePattern = "/^[a-zA-Z\s]{2,50}$/";                 // Only letters & spaces, 2-50 chars
        $phonePattern = "/^\+?[1-9]\d{1,14}$/";
        $emailPattern = "/^[\w.\-]+@[\w\-]+\.[a-zA-Z]{2,}$/";   // Basic email format
        $addressPattern = "/^[\p{L}0-9\s,.\-#\/()':]{5,200}$/u";

        $gstPattern = "/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/"; // GSTIN

        // Validate Customer Data
        if (!preg_match($namePattern, $customerName)) {
            echo json_encode([
                "status" => 400,
                "error" => "Invalid customer name",

            ]);
            exit();
        }
        if (!preg_match($phonePattern, $customerPhone)) {
            echo json_encode([
                "status" => 400,
                "error" => "Invalid customer phone",

            ]);
            exit();
        }
        if (!preg_match($emailPattern, $customerEmail)) {
            echo json_encode([
                "status" => 400,
                "error" => "Invalid customer email",

            ]);
            exit();
        }
        if (!preg_match($addressPattern, $customerAddress)) {
            echo json_encode([
                "status" => 400,
                "error" => "Invalid customer address",

            ]);
            exit();
        }

        // Validate Shipping Data (only if provided)
        if (!is_null($shippingName) && $shippingName !== '') {
            if (!preg_match($namePattern, $shippingName)) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Invalid shipping name",
                ]);
                exit();
            }
        }

        if (!is_null($shippingPhone) && $shippingPhone !== '') {
            if (!preg_match($phonePattern, $shippingPhone)) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Invalid shipping phone",
                ]);
                exit();
            }
        }

        if (!is_null($shippingEmail) && $shippingEmail !== '') {
            if (!preg_match($emailPattern, $shippingEmail)) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Invalid shipping email",
                ]);
                exit();
            }
        }

        if (!is_null($shippingAddress) && $shippingAddress !== '') {
            if (!preg_match($addressPattern, $shippingAddress)) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Invalid shipping address",
                ]);
                exit();
            }
        }

        // // Validate GST Number
        // if (!preg_match($gstPattern, strtoupper($gstNumber))) {
        //     $_SESSION['error'] = 'Invalid GST number';
        //     header("Location: customer-details");
        //     exit();
        // }

        // City validation (numeric check already from filter_input)
        if (!is_numeric($customerCity)) {

            echo json_encode([
                "status" => 400,
                "error" => "Invalid city ID",

            ]);
            exit();
        }

        $stmtExistingUser = $db->prepare("SELECT * FROM customer WHERE customer_email = ? OR customer_phone = ?");
        $stmtExistingUser->bind_param("ss", $customerEmail, $customerPhone);
        $stmtExistingUser->execute();

        $result = $stmtExistingUser->get_result();
        if ($result->num_rows > 0) {

            echo json_encode([
                "status" => 400,
                "error" => "Customer Phone or Email Already Existing",

            ]);
            exit();
        }

        $result = uploadMedia($_FILES['image'], $uploadDir, [
            // Images
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'bmp',
            'svg',
            'tiff',
            'ico',
        ], 2 * 1024 * 1024);


        if (isset($result['error'])) {
            echo json_encode([
                "status" => 400,
                "error" => $result['error'] ?? "Error while uploading Image",
            ]);
            exit();
        }
        $image = isset($result['filename']) ? $result['filename'] : "";

        $stmtInsert = $db->prepare('INSERT INTO customer 
        (
        customer_name,
        customer_phone,
        customer_email,
        customer_address,
        ship_name,
        ship_phone,
        ship_email,
        ship_address,
        customer_state,
        customer_city,
        gst_number,
        image
        ) 
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmtInsert->bind_param(
            'sssssssssiss',
            $customerName,
            $customerPhone,
            $customerEmail,
            $customerAddress,
            $shippingName,
            $shippingPhone,
            $shippingEmail,
            $shippingAddress,
            $customerState,
            $customerCity,
            $gstNumber,
            $image
        );
        if ($stmtInsert->execute()) {
            echo json_encode([
                "status" => 201,
                "message" => "Customer created Successfully",

            ]);
            exit();
        } else {
            echo json_encode([
                "status" => 400,
                "error" => "Error While adding Customer",

            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => 500,
            "error" => "Error While adding Customer" . $e->getMessage(),

        ]);
        exit();
    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editCustomerId'])) {
    try {

        $editCustomerId = filter_input(INPUT_POST, 'editCustomerId', FILTER_SANITIZE_NUMBER_INT);

        // Initialize arrays for query fields and values
        $fields = [];
        $values = [];
        $types = '';



        // Check each field and add to update if not empty
        $editCustomerName = filter_input(INPUT_POST, 'editCustomerName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editCustomerName)) {
            $fields[] = 'customer_name = ?';
            $values[] = $editCustomerName;
            $types .= 's';
        }

        $editCustomerPhone = filter_input(INPUT_POST, 'editCustomerPhone', FILTER_SANITIZE_NUMBER_INT);
        if (!empty($editCustomerPhone)) {
            $fields[] = 'customer_phone = ?';
            $values[] = $editCustomerPhone;
            $types .= 's';
        }

        $editCustomerEmail = filter_input(INPUT_POST, 'editCustomerEmail', FILTER_SANITIZE_EMAIL);
        if (!empty($editCustomerEmail)) {
            $fields[] = 'customer_email = ?';
            $values[] = $editCustomerEmail;
            $types .= 's';
        }

        $editCustomerAddress = filter_input(INPUT_POST, 'editCustomerAddress', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editCustomerAddress)) {
            $fields[] = 'customer_address = ?';
            $values[] = $editCustomerAddress;
            $types .= 's';
        }

        $editShippingName = filter_input(INPUT_POST, 'editShippingName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editShippingName)) {
            $fields[] = 'ship_name = ?';
            $values[] = $editShippingName;
            $types .= 's';
        }

        $editShippingPhone = filter_input(INPUT_POST, 'editShippingPhone', FILTER_SANITIZE_NUMBER_INT);
        if (!empty($editShippingPhone)) {
            $fields[] = 'ship_phone = ?';
            $values[] = $editShippingPhone;
            $types .= 's';
        }

        $editShippingEmail = filter_input(INPUT_POST, 'editShippingEmail', FILTER_SANITIZE_EMAIL);
        if (!empty($editShippingEmail)) {
            $fields[] = 'ship_email = ?';
            $values[] = $editShippingEmail;
            $types .= 's';
        }

        $editShippingAddress = filter_input(INPUT_POST, 'editShippingAddress', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editShippingAddress)) {
            $fields[] = 'ship_address = ?';
            $values[] = $editShippingAddress;
            $types .= 's';
        }

        $editCustomerState = filter_input(INPUT_POST, 'editCustomerState', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editCustomerState)) {
            $fields[] = 'customer_state = ?';
            $values[] = $editCustomerState;
            $types .= 's';
        }

        $editCustomerCity = filter_input(INPUT_POST, 'editCustomerCity', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editCustomerCity)) {
            $fields[] = 'customer_city = ?';
            $values[] = $editCustomerCity;
            $types .= 's';
        }

        $editCustomerStatus = filter_input(INPUT_POST, 'editCustomerStatus', FILTER_SANITIZE_NUMBER_INT);
        if (isset($_POST['editCustomerStatus'])) {

            $fields[] = 'isActive = ?';
            $values[] = $editCustomerStatus;
            $types .= 'i';
        }

        $editGstNumber = filter_input(INPUT_POST, 'editGstNumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($editGstNumber)) {
            $fields[] = 'gst_number = ?';
            $values[] = $editGstNumber;
            $types .= 's';
        }


        if (isset($_FILES['editImage']) && $_FILES['editImage']['error'] !== UPLOAD_ERR_NO_FILE) {

            $result = uploadMedia($_FILES['editImage'], $uploadDir, [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'webp',
                'bmp',
                'svg',
                'tiff',
                'ico'
            ], 2 * 1024 * 1024);

            if (isset($result[0]['error'])) {
                echo json_encode([
                    "status" => 400,
                    "error" => $result[0]['error']
                ]);
                exit();
            }

            $image = isset($result['filename']) ? $result['filename'] : "";


            $fields[] = 'image = ?';
            $values[] = $image;
            $types .= 's';
        }



        function hasMoreThanWords(string $text, int $maxWords): bool
        {
            $words = preg_split('/\s+/', trim($text));
            return count($words) > $maxWords;
        }

        if (hasMoreThanWords($editCustomerAddress, 20)) {
            echo json_encode([
                "status" => 400,
                "error" => "Address cannot exceed 20 words "
            ]);
            exit;
        }

        if (hasMoreThanWords($editShippingAddress, 20)) {
            echo json_encode([
                "status" => 400,
                "error" => "Address cannot exceed 20 words "
            ]);
            exit;
        }


        // Add customer_id to the values and types
        $values[] = $editCustomerId;
        $types .= 'i';

        // echo "<pre>";
        // print_r($_POST);
        // print_r($fields);
        // print_r($values);
        // print_r($editCustomerStatus);
        // exit();

        // If there are fields to update, build and execute the query
        if (!empty($fields)) {
            $query = 'UPDATE customer SET ' . implode(', ', $fields) . ' WHERE customer_id = ?';
            $stmtUpdate = $db->prepare($query);
            $stmtUpdate->bind_param($types, ...$values);

            if ($stmtUpdate->execute()) {
                echo json_encode([
                    "status" => 200,
                    "error" => "Customer Updated Successfully "
                ]);
                exit;

            } else {
                echo json_encode([
                    "status" => 400,
                    "error" => "Error while updating customer "
                ]);
                exit;
            }
        } else {
            echo json_encode([
                "status" => 400,
                "error" => "No fields to update "
            ]);
            exit;

        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => 500,
            "error" => 'Exception: ' . $e->getMessage()
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customerId'])) {
    try {
        $customerId = filter_input(INPUT_POST, 'customerId', FILTER_SANITIZE_NUMBER_INT);

        $stmtDelete = $db->prepare('DELETE FROM customer WHERE customer_id = ?');
        $stmtDelete->bind_param('i', $customerId);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Customer deleted successfully.'
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['stateCode'])) {

    try {
        $stateCode = filter_input(INPUT_POST, 'stateCode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $stmtFetchCities = $db->prepare("SELECT * FROM cities WHERE state_code = ?");
        $stmtFetchCities->bind_param('s', $stateCode);

        if ($stmtFetchCities->execute()) {
            $cities = $stmtFetchCities->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode([
                'status' => 200,
                'data' => $cities
            ]);
            exit;
        }


    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => $e->getMessage(),
        ]);
        exit;
    }


}

// Bulk Excel upload
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $fileType = IOFactory::identify($file);
    $reader = IOFactory::createReader($fileType);
    $spreadsheet = $reader->load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $successCount = 0;
    $failCount = 0;
    $errors = [];

    foreach ($rows as $rowIndex => $row) {
        // Skip header row (assuming first row is the header)
        if ($rowIndex == 0 || empty($row[0])) {
            continue;
        }

        $customerName = filter_var($row[0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $customerPhone = preg_replace('/\D/', '', $row[1]); // Keeps only digits
        $customerEmail = filter_var($row[2], FILTER_SANITIZE_EMAIL);
        $customerAddress = filter_var($row[3], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $gstNumber = filter_var($row[4], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Basic validation
        if (empty($customerPhone) || empty($customerEmail)) {
            $failCount++;
            $errors[] = "Row $rowIndex: Missing phone or email.";
            continue;
        }

        // Check for duplicates
        $stmtCheck = $db->prepare("SELECT * FROM customer WHERE customer_phone = ? OR customer_email = ? OR gst_number = ?");
        $stmtCheck->bind_param("sss", $customerPhone, $customerEmail, $gstNumber);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();

        if ($result->num_rows > 0) {
            $failCount++;
            $errors[] = "Row $rowIndex: Duplicate entry (phone/email/gst).";
            continue;
        }

        // Insert into database
        $stmtInsert = $db->prepare("INSERT INTO customer (customer_name, customer_phone, customer_email, customer_address, gst_number) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->bind_param("sssss", $customerName, $customerPhone, $customerEmail, $customerAddress, $gstNumber);

        if ($stmtInsert->execute()) {
            $successCount++;
        } else {
            $failCount++;
            $errors[] = "Row $rowIndex: DB error - " . $stmtInsert->error;
        }

        $stmtCheck->close();
        $stmtInsert->close();
    }

    // Feedback
    $_SESSION['success'] = "$successCount rows inserted successfully.";
    if ($failCount > 0) {
        $_SESSION['error'] = "$failCount rows failed to insert. See logs.";
        file_put_contents("upload_errors.log", implode(PHP_EOL, $errors));
    }

    header("Location: customer-details"); // or wherever you want to redirect
    exit;
}

// delete multiple airlines
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customerIds'])) {


    $customerIds = $_POST['customerIds'];

    // Validate: Must be an array of integers
    if (!is_array($customerIds)) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid data format.'
        ]);
        exit;
    }

    try {
        // Prepare the SQL dynamically
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $types = str_repeat('i', count($customerIds)); // All integers

        $stmt = $db->prepare("DELETE FROM customer WHERE customer_id IN ($placeholders)");
        $stmt->bind_param($types, ...$customerIds);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected customer deleted successfully.',
                'deleted_ids' => $customerIds
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmt->error
            ]);
        }

        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


try {

    $stmtFetchCities = $db->prepare("SELECT * FROM cities");

    if ($stmtFetchCities->execute()) {
        $cities = $stmtFetchCities->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtFetch = $db->prepare("SELECT * FROM customer");
    $stmtFetch->execute();
    $customers = $stmtFetch->get_result();

    $stmtFetchState = $db->prepare("SELECT * FROM state");
    $stmtFetchState->execute();
    $states = $stmtFetchState->get_result();

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

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

    <!-- intl-tel-input -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
    <!-- html to pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <!-- html to excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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
                        <?php if ($isAdmin || hasPermission('Delete Customer', $privileges, $roleData['0']['role_name'])): ?>
                            <li>
                                <a data-bs-toggle="tooltip" class="multi-delete-button" data-bs-placement="top"
                                    title="Delete"><img src="assets/img/icons/delete.png" alt="img" /></a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportToPDF(`customers`)" data-bs-placement="top" title="Pdf"><img
                                    src="assets/img/icons/pdf.svg" alt="img"></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" onclick="exportToExcel(`customers`)" data-bs-placement="top"
                                title="Excel"><img src="assets/img/icons/excel.svg" alt="img"></a>
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
                    <?php if ($isAdmin || hasPermission('Add Customer', $privileges, $roleData['0']['role_name'])): ?>
                        <div class="page-btn">
                            <a href="#" class="btn btn-added" data-bs-toggle="modal" data-bs-target="#add-units"><i
                                    data-feather="plus-circle" class="me-2"></i>Add Customer</a>
                        </div>
                        <div class="page-btn import">
                            <a href="javascript:void(0);" class="btn btn-added color" data-bs-toggle="modal"
                                data-bs-target="#view-notes"><i data-feather="download" class="me-2"></i>Import Customer</a>
                        </div>
                    <?php endif; ?>
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
                            <table class="table datanew" id="myTable">
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
                                        <th>GST Number</th>
                                        <th>Created On</th>
                                        <th>Status</th>
                                        <th class="no-sort text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers->fetch_all(MYSQLI_ASSOC) as $customer) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" name="customerIds"
                                                        value="<?php echo $customer['customer_id'] ?>" />
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <div class="userimgname cust-imgname">
                                                    <a href="javascript:void(0);" class="product-img">
                                                        <img src="<?php echo $customer['image'] ?? "assets/img/users/user-23.jpg" ?>"
                                                            alt="product">
                                                    </a>
                                                    <a class="text-primary"
                                                        href="<?= getenv("BASE_URL") . "view-customer-report?id=" . base64_encode($customer['customer_id']) ?>"><?php echo $customer['customer_name'] ?></a>
                                                </div>
                                            </td>
                                            <td><?php echo $customer['customer_phone'] ?></td>
                                            <td>
                                                <?php echo $customer['customer_email'] ?>
                                            </td>
                                            <td class="ref-number"><?php echo $customer['gst_number'] ?></td>
                                            <td><?php echo formatDateTime($customer['created_at'], $localizationSettings); ?>
                                            </td>
                                            <td>
                                                <?php if ($customer['isActive'] == 1) { ?>
                                                    <span class="badge badge-lg bg-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-lg bg-danger">Inactive</span>
                                                <?php } ?>
                                            </td>

                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
                                                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <?php if ($isAdmin || hasPermission('Edit Customer', $privileges, $roleData['0']['role_name'])): ?>
                                                        <li>
                                                            <a data-bs-toggle="modal" data-bs-target="#edit-units"
                                                                data-customer-id="<?php echo $customer['customer_id'] ?>"
                                                                data-customer-name="<?php echo $customer['customer_name'] ?>"
                                                                data-customer-phone="<?php echo $customer['customer_phone'] ?>"
                                                                data-customer-email="<?php echo $customer['customer_email'] ?>"
                                                                data-customer-address="<?php echo $customer['customer_address'] ?>"
                                                                data-shipping-name="<?php echo $customer['ship_name'] ?>"
                                                                data-shipping-phone="<?php echo $customer['ship_phone'] ?>"
                                                                data-shipping-email="<?php echo $customer['ship_email'] ?>"
                                                                data-shipping-address="<?php echo $customer['ship_address'] ?>"
                                                                data-customer-status="<?php echo $customer['isActive'] ?>"
                                                                data-customer-state="<?php echo $customer['customer_state'] ?>"
                                                                data-customer-city="<?php echo $customer['customer_city'] ?>"
                                                                data-gst-number="<?php echo $customer['gst_number'] ?>"
                                                                class="editButton dropdown-item"><i data-feather="edit"
                                                                    class="info-img"></i>Edit
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($isAdmin || hasPermission('Delete Customer', $privileges, $roleData['0']['role_name'])): ?>
                                                        <li>
                                                            <a href="javascript:void(0);"
                                                                data-customer-id="<?php echo $customer['customer_id'] ?>"
                                                                class="dropdown-item deleteButton mb-0"><i
                                                                    data-feather="trash-2" class="info-img"></i>Delete </a>
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
                                <h4>Add Customer</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form class="customer-create" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Image <span> *</span></label>
                                            <input type="file" accept=".png,.jpeg,.jpg,.webp,.svg" class="form-control"
                                                name="image">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Name <span> *</span></label>
                                            <input type="text" class="form-control" name="customerName"
                                                placeholder="Enter customer name">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Phone <span> *</span></label>
                                            <input type="tel" class="form-control" name="customerPhone"
                                                placeholder="Enter customer phone">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Email <span> *</span></label>
                                            <input type="email" class="form-control" name="customerEmail"
                                                placeholder="Enter customer email">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer State <span> *</span></label>
                                            <select class="form-select" id="customerState" name="customerState">
                                                <option>Select</option>
                                                <?php foreach ($states as $state) { ?>
                                                    <option value="<?php echo $state['state_code']; ?>">
                                                        <?php echo $state['state_name']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer City <span> *</span></label>
                                            <select class="form-select" id="customerCity" name="customerCity" required>
                                                <option>Select a city</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Address <span> *</span></label>
                                            <input type="text" class="form-control" name="customerAddress"
                                                placeholder="Enter customer address">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Name </label>
                                            <input type="text" class="form-control" name="shippingName"
                                                placeholder="Enter shipping name">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Phone </label>
                                            <input type="tel" class="form-control" name="shippingPhone"
                                                placeholder="Enter shipping phone">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Email </label>
                                            <input type="email" class="form-control" name="shippingEmail"
                                                placeholder="Enter shipping email">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Address </label>
                                            <input type="text" class="form-control" name="shippingAddress"
                                                placeholder="Enter shipping address">
                                        </div>
                                    </div>
                                    <!-- GST Input with Stylish Eye Tooltip -->
                                    <div class="col-lg-6">
                                        <div class="input-blocks position-relative mb-4">
                                            <label for="gstNumber" class="form-label fw-semibold text-secondary">GST
                                                Number</label>
                                            <div class="gst-input-wrapper position-relative">
                                                <input type="text" id="gstNumber" name="gstNumber"
                                                    class="form-control stylish-input"
                                                    placeholder="Enter 15-digit GST number">
                                                <span class="gst-icon"
                                                    data-tooltip="GST must be a 15-digit alphanumeric code. Example: 22AAAAA0000A1Z5">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
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
                            <form action="" method="post" class="edit-customer" enctype="multipart/form-data">
                                <input type="hidden" name="editCustomerId" id="editCustomerId">
                                <div class="row">

                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Image <span> *</span></label>
                                            <input type="file" accept=".png,.jpeg,.jpg,.webp,.svg" class="form-control"
                                                name="editImage">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Name</label>
                                            <input type="text" class="form-control" name="editCustomerName"
                                                id="editCustomerName">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Phone</label>
                                            <input type="tel" class="form-control" name="editCustomerPhone"
                                                id="editCustomerPhone">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Email</label>
                                            <input type="email" class="form-control" name="editCustomerEmail"
                                                id="editCustomerEmail">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer State</label>
                                            <select class="form-select" id="editCustomerState" name="editCustomerState">
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
                                            <select class=" form-select" id="editCustomerCity" name="editCustomerCity">
                                                <?php foreach ($cities as $city) { ?>
                                                    <option value="<?= $city['city_id'] ?>"><?= $city['city_name'] ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Customer Address</label>
                                            <input type="text" class="form-control" name="editCustomerAddress"
                                                id="editCustomerAddress">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Name</label>
                                            <input type="text" class="form-control" name="editShippingName"
                                                id="editShippingName">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Phone</label>
                                            <input type="tel" class="form-control" name="editShippingPhone"
                                                id="editShippingPhone">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Email</label>
                                            <input type="email" class="form-control" name="editShippingEmail"
                                                id="editShippingEmail">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>Shipping Address</label>
                                            <input type="text" class="form-control" name="editShippingAddress"
                                                id="editShippingAddress">
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
                                    <div class="col-lg-6">
                                        <div class="input-blocks">
                                            <label>GST Numbers</label>
                                            <input type="text" class="form-control" name="editGstNumber"
                                                id="editGstNumber">
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

    <div class="modal fade" id="view-notes">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Import Product</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-lg-12 col-sm-6 col-12">
                                        <div class="row">
                                            <div>
                                                <div class="modal-footer-btn download-file">
                                                    <a href="public/sample/docs/demo_customer_upload.xlsx"
                                                        class="btn btn-submit">Download Sample
                                                        File</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="input-blocks image-upload-down">
                                            <label> Upload CSV File</label>
                                            <div class="image-upload download">
                                                <input type="file" accept=".xls,.xlsx,.csv" name="excel_file" />
                                                <div class="image-uploads">
                                                    <img src="assets/img/download-img.png" alt="img" />
                                                    <h4>Drag and drop a <span>file to upload</span></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- <div class="col-lg-6">
                                        <div class="input-blocks image-upload-down">
                                            <label> Upload Images </label>
                                            <div class="image-upload download">
                                                <input type="file" accept=".png,.jpg,.jpeg,.webp"
                                                    name="product_images[]" multiple  />
                                                <div class="image-uploads">
                                                    <img src="assets/img/download-img.png" alt="img" />
                                                    <h4>Drag and drop a <span>file to upload</span></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div> -->
                                </div>

                                <div class="col-lg-12">
                                    <div class="modal-footer-btn">
                                        <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">
                                            Cancel
                                        </button>
                                        <button type="submit" name="excel_file" class="btn btn-submit">
                                            Submit
                                        </button>
                                    </div>
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



    <script>
        $(document).ready(function (e) {

            const notyf = new Notyf({
                duration: 3000,
                position: { x: 'center', y: 'top' },
                types: [
                    {
                        type: 'success',
                        background: '#4dc76f',
                        textColor: '#FFFFFF',
                        dismissible: false
                    },
                    {
                        type: 'error',
                        background: '#ff1916',
                        textColor: '#FFFFFF',
                        dismissible: false
                    }
                ]
            });



            $(document).on('input', "#gstNumber", function () {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
            $(document).on('input', "#editGstNumber", function () {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });

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
                let gstNumber = $(this).data("gst-number");



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
                $('#editGstNumber').val(gstNumber);

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
                            url: 'customer-details', // The PHP file that will handle the deletion
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
                    url: 'customer-details', // The PHP file that will handle the deletion
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
                    url: 'customer-details', // The PHP file that will handle the deletion
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

            const input = $(".input-blocks input[name='customerPhone']").get(0); // or use [0]

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

            const inputEdit = $(".input-blocks input[name='editCustomerPhone']").get(0); // or use [0]

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

            const shippingPhoneInput = $(".input-blocks input[name='shippingPhone']").get(0); // or use [0]

            window.intlTelInput(shippingPhoneInput, {
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
                initialCountry: "auto",
                geoIpLookup: function (callback) {
                    fetch('https://ipapi.co/json')
                        .then(response => response.json())
                        .then(data => callback(data.country_code))
                        .catch(() => callback('us'));
                }
            });

            const shippingPhoneInputEdit = $(".input-blocks input[name='editShippingPhone']").get(0); // or use [0]

            window.intlTelInput(shippingPhoneInputEdit, {
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
                initialCountry: "auto",
                geoIpLookup: function (callback) {
                    fetch('https://ipapi.co/json')
                        .then(response => response.json())
                        .then(data => callback(data.country_code))
                        .catch(() => callback('us'));
                }
            });

            $(document).on('click', '.multi-delete-button', function (e) {
                e.preventDefault();

                let customerIds = [];
                $('input[name="customerIds"]:checked').each(function () {
                    customerIds.push(parseInt($(this).val()));
                });

                if (customerIds.length == 0) {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Please select customer!",
                    });
                    return;
                }

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {

                        $.ajax({
                            url: "customer-details",
                            type: "post",
                            data: { customerIds: customerIds },
                            success: function (response) {

                                Swal.fire(
                                    'Deleted!',
                                    'The Customer has been deleted.',
                                    'success'
                                ).then(() => {
                                    // Reload the page
                                    location.reload();
                                });

                            },
                            error: function (error) {
                                console.log(error);
                            },
                        });

                    }
                })


            });


            $(document).on("submit", ".customer-create", async function (e) {
                e.preventDefault();

                let customerName = $('input[name="customerName"]').val().trim();
                let customerPhone = $('input[name="customerPhone"]').val().trim();
                let customerEmail = $('input[name="customerEmail"]').val().trim();
                let customerState = $('select[name="customerState"]').val().trim();
                let customerCity = $('select[name="customerCity"]').val().trim();
                let customerAddress = $('input[name="customerAddress"]').val().trim();

                let shippingName = $('input[name="shippingName"]').val().trim();
                let shippingPhone = $('input[name="shippingPhone"]').val().trim();
                let shippingEmail = $('input[name="shippingEmail"]').val().trim();
                let shippingAddress = $('input[name="shippingAddress"]').val().trim();
                let gstNumber = $('input[name="gstNumber"]').val().trim();
                let imageInput = $('input[name="image"]')[0];
                let imageFile = imageInput.files[0];


                // Regex patterns
                const nameRegex = /^[a-zA-Z\s]{2,50}$/;
                const mobileRegex = /^[0-9]{10}$/;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const addressRegex = /^[a-zA-Z0-9\s,.\-#\/()':]{5,200}$/;
                const gstRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

                function hasMoreThanWords(text, maxWords) {
                    return text.trim().split(/\s+/).length > maxWords;
                }


                // Required fields check
                if (!customerName || !customerPhone || !customerEmail || !customerState || !customerCity || !customerAddress) {
                    notyf.error("All fields are required. Please fill out the form completely.");
                    return;
                }

                // Customer validations
                if (!nameRegex.test(customerName)) {
                    notyf.error("Please enter a valid customer name (letters only, 2–50 characters)");
                    return;
                }
                if (!mobileRegex.test(customerPhone)) {
                    notyf.error("Please enter a valid 10-digit customer phone number");
                    return;
                }
                if (!emailRegex.test(customerEmail)) {
                    notyf.error("Please enter a valid customer email address");
                    return;
                }
                if (!addressRegex.test(customerAddress)) {
                    notyf.error("Please enter a valid customer address (5–200 characters)");
                    return;
                }

                // 20-word limit validation
                if (customerAddress && hasMoreThanWords(customerAddress, 20)) {
                    notyf.error("Customer address cannot exceed 20 words.");
                    return;
                }

                if (shippingAddress && hasMoreThanWords(shippingAddress, 20)) {
                    notyf.error("Shipping address cannot exceed 20 words.");
                    return;
                }


                // // Shipping validations
                // if (!nameRegex.test(shippingName)) {
                //     notyf.error("Please enter a valid shipping name (letters only, 2–50 characters)");
                //     return;
                // }
                // if (!mobileRegex.test(shippingPhone)) {
                //     notyf.error("Please enter a valid 10-digit shipping phone number");
                //     return;
                // }
                // if (!emailRegex.test(shippingEmail)) {
                //     notyf.error("Please enter a valid shipping email address");
                //     return;
                // }
                // if (!addressRegex.test(shippingAddress)) {
                //     notyf.error("Please enter a valid shipping address (5–200 characters)");
                //     return;
                // }

                // GST validation (only if provided)
                if (gstNumber.length > 0 && !gstRegex.test(gstNumber)) {
                    notyf.error("Please enter a valid GST number");
                    return;
                }

                if (!imageFile) {
                    notyf.error("Please select an image.");
                    return;
                }

                let formDataObject = new FormData();

                formDataObject.append('customerName', customerName);
                formDataObject.append('customerPhone', customerPhone);
                formDataObject.append('customerEmail', customerEmail);
                formDataObject.append('customerAddress', customerAddress);
                formDataObject.append('shippingName', shippingName);
                formDataObject.append('shippingPhone', shippingPhone);
                formDataObject.append('shippingEmail', shippingEmail);
                formDataObject.append('shippingAddress', shippingAddress);
                formDataObject.append('customerState', customerState);
                formDataObject.append('customerCity', customerCity);
                formDataObject.append('gstNumber', gstNumber);
                formDataObject.append('image', imageFile);



                // Optional: Log the FormData contents (for debugging)
                // for (let [key, value] of formDataObject.entries()) {
                //     console.log(key, value);
                // };
                // return;



                await $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formDataObject,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.status == 201) {
                            // Success - reset form
                            $('.customer-create')[0].reset();
                            notyf.success("Customer created successfully");
                            window.location.reload();

                        } else {
                            notyf.error(response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.error("Raw Response:", xhr.responseText);
                        notyf.error("An error occurred while processing your request. Please try again.",);
                    }
                });

            });


            $(document).on("submit", ".edit-customer", async function (e) {
                e.preventDefault();

                const editCustomerId = $('#editCustomerId').val();

                const customerName = $('input[name="editCustomerName"]').val().trim();
                const customerPhone = $('input[name="editCustomerPhone"]').val().trim();
                const customerEmail = $('input[name="editCustomerEmail"]').val().trim();
                const customerState = $('select[name="editCustomerState"]').val();
                const customerCity = $('select[name="editCustomerCity"]').val();
                const customerAddress = $('input[name="editCustomerAddress"]').val().trim();

                const shippingName = $('input[name="editShippingName"]').val().trim();
                const shippingPhone = $('input[name="editShippingPhone"]').val().trim();
                const shippingEmail = $('input[name="editShippingEmail"]').val().trim();
                const shippingAddress = $('input[name="editShippingAddress"]').val().trim();

                const status = $('select[name="editCustomerStatus"]').val();
                const gstNumber = $('input[name="editGstNumber"]').val().trim();

                const imageInput = $('input[name="editImage"]')[0];
                const imageFile = imageInput.files[0] || null;

                // Regex
                const nameRegex = /^[a-zA-Z\s]{2,50}$/;
                const mobileRegex = /^[0-9]{10}$/;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const gstRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

                if (!editCustomerId) {
                    notyf.error("Invalid customer ID.");
                    return;
                }

                if (customerName && !nameRegex.test(customerName)) {
                    notyf.error("Invalid customer name.");
                    return;
                }

                if (customerPhone && !mobileRegex.test(customerPhone)) {
                    notyf.error("Invalid phone number.");
                    return;
                }

                if (customerEmail && !emailRegex.test(customerEmail)) {
                    notyf.error("Invalid email address.");
                    return;
                }

                if (gstNumber && !gstRegex.test(gstNumber)) {
                    notyf.error("Invalid GST number.");
                    return;
                }

                // Image validation (ONLY if selected)
                if (imageFile) {
                    const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];

                    if (!allowedTypes.includes(imageFile.type)) {
                        notyf.error("Invalid image format.");
                        return;
                    }

                    if (imageFile.size > 2 * 1024 * 1024) {
                        notyf.error("Image must be under 2MB.");
                        return;
                    }
                }

                const formData = new FormData();

                formData.append('editCustomerId', editCustomerId);
                formData.append('editCustomerName', customerName);
                formData.append('editCustomerPhone', customerPhone);
                formData.append('editCustomerEmail', customerEmail);
                formData.append('editCustomerState', customerState);
                formData.append('editCustomerCity', customerCity);
                formData.append('editCustomerAddress', customerAddress);

                formData.append('editShippingName', shippingName);
                formData.append('editShippingPhone', shippingPhone);
                formData.append('editShippingEmail', shippingEmail);
                formData.append('editShippingAddress', shippingAddress);

                formData.append('editCustomerStatus', status);
                formData.append('editGstNumber', gstNumber);

                // Append image only if selected
                if (imageFile) {
                    formData.append('editImage', imageFile);
                }

                await $.ajax({
                    url: window.location.href,
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.status === 200) {
                            notyf.success("Customer updated successfully");
                            $('#editCustomerModal').modal('hide');
                            window.location.reload();
                        } else {
                            notyf.error(response.error || "Update failed");
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        notyf.error("Server error. Please try again.");
                    }
                });
            });


        })
    </script>


</body>

</html>