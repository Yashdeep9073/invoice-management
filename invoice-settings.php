<?php
ob_start();
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}
require "./database/config.php";

// Define the upload directory
$uploadDirectory = 'public/upload/invoice/images/';

try {
    $stmtFetch = $db->prepare("SELECT * FROM invoice_settings");
    $stmtFetch->execute();
    $invoiceSettings = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

function uploadImage($file, $uploadDirectory)
{
    // Check if the upload directory exists, if not, create it
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0777, true); // Create directory with permissions
    }

    // Get file details
    $fileName = basename($file['name']); // File name (e.g., bg.jpg)
    $fileTmpPath = $file['tmp_name']; // Temporary file path on the server
    $fileSize = $file['size']; // File size in bytes
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); // File extension (e.g., jpg)

    // Allowed file types
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'svg'];

    // Maximum file size (e.g., 2MB = 2 * 1024 * 1024 bytes)
    $maxFileSize = 2 * 1024 * 1024;

    // Validate file type
    if (!in_array($fileType, $allowedTypes)) {
        return [
            "status" => false,
            "error" => "Error: Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed."
        ];
    }

    // Validate file size
    if ($fileSize > $maxFileSize) {
        return [
            "status" => false,
            "error" => "Error: File size exceeds the maximum limit of 2MB."
        ];
    }

    // Generate a unique file name to avoid overwriting
    $newFileName = uniqid('img_', true) . '.' . $fileType; // e.g., img_123456789.jpg
    $destinationPath = $uploadDirectory . $newFileName;

    // Move the uploaded file to the destination directory
    if (move_uploaded_file($fileTmpPath, $destinationPath)) {
        return [
            "status" => true,
            "data" => $newFileName,
            "full_path" => $destinationPath // Return the full path for reference
        ];
    } else {
        return [
            "status" => false,
            "error" => "Error: Failed to upload the file."
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {

    try {

        if (isset($_POST['invoice_settings_id']) && $_POST['invoice_settings_id'] != "") {
            $invoiceSettingsId = $_POST["invoice_settings_id"];
            $invoicePrefix = $_POST["invoicePrefix"];
            $showHsnCode = !empty($_POST["showHsnCode"]) && $_POST["showHsnCode"] == "on" ? 1 : null;
            $invoiceHeader = $_POST["invoiceHeader"];
            $invoiceFooter = $_POST["invoiceFooter"];

            $invoiceLogo = $_FILES["invoiceLogo"];
            $invoiceTemplate = $_FILES["invoiceTemplate"];

            // Get existing logo/template from DB
            $stmtFetch = $db->prepare("SELECT logo, template FROM invoice_settings WHERE invoice_settings_id = ?");
            $stmtFetch->bind_param("i", $invoiceSettingsId);
            $stmtFetch->execute();
            $result = $stmtFetch->get_result();
            $existing = $result->fetch_assoc();
            $stmtFetch->close();

            $logoUrl = $existing['logo'];
            $templateUrl = $existing['template'];

            // Upload new files if provided
            if ($invoiceLogo['name'] != "") {
                $uploadedLogo = uploadImage($invoiceLogo, $uploadDirectory);
                if ($uploadedLogo['status']) {
                    $logoUrl = $uploadDirectory . $uploadedLogo['data'];
                } else {
                    $_SESSION['error'] = $uploadedLogo['error'];
                    header("Location: invoice-settings.php");
                    exit;
                }
            }

            if ($invoiceTemplate['name'] != "") {
                $uploadedTemplate = uploadImage($invoiceTemplate, $uploadDirectory);
                if ($uploadedTemplate['status']) {
                    $templateUrl = $uploadDirectory . $uploadedTemplate['data'];
                } else {
                    $_SESSION['error'] = $uploadedTemplate['error'];
                    header("Location: invoice-settings.php");
                    exit;
                }
            }

            // Update record
            $stmtUpdate = $db->prepare("UPDATE invoice_settings SET invoice_prefix = ?, logo = ?, header_terms = ?, footer_terms = ?, template = ?, is_show_hsn = ? WHERE invoice_settings_id = ?");
            if ($stmtUpdate === false) {
                $_SESSION['error'] = "Database prepare error: " . $db->error;
                header("Location: invoice-settings.php");
                exit;
            }

            $stmtUpdate->bind_param(
                "ssssssi",
                $invoicePrefix,
                $logoUrl,
                $invoiceHeader,
                $invoiceFooter,
                $templateUrl,
                $showHsnCode,
                $invoiceSettingsId
            );

            if ($stmtUpdate->execute()) {
                $_SESSION['success'] = "Invoice Setting Updated Successfully";
            } else {
                $_SESSION['error'] = "Database execution error: " . $stmtUpdate->error;
            }

            $stmtUpdate->close();
            header("Location: invoice-settings.php");
            exit;


        } else {
            $invoicePrefix = $_POST["invoicePrefix"];
            $showHsnCode = isset($_POST["showHsnCode"]) ? $_POST["showHsnCode"] : null;
            $invoiceHeader = $_POST["invoiceHeader"];
            $invoiceFooter = $_POST["invoiceFooter"];

            $invoiceLogo = $_FILES["invoiceLogo"];
            $invoiceTemplate = $_FILES["invoiceTemplate"];

            $uploadedLogo = uploadImage($invoiceLogo, $uploadDirectory);
            $uploadedTemplate = uploadImage($invoiceTemplate, $uploadDirectory);


            if ($uploadedLogo['status'] && $uploadedTemplate['status']) {
                $logoUrl = $uploadDirectory . $uploadedLogo['data'];
                $templateUrl = $uploadDirectory . $uploadedTemplate['data'];

                // Insert into database
                $stmtInsert = $db->prepare("INSERT INTO invoice_settings (
                invoice_prefix, logo, header_terms, footer_terms,
                template,is_show_hsn
            ) VALUES (?, ?, ?, ?, ?, ?)");


                if ($stmtInsert === false) {
                    $_SESSION['error'] = "Database prepare error: " . $db->error;
                    header("Location: invoice-settings.php");
                    exit;
                }

                $stmtInsert->bind_param(
                    "sssssi",
                    $invoicePrefix,
                    $logoUrl,
                    $invoiceHeader,
                    $invoiceFooter,
                    $templateUrl,
                    $showHsnCode
                );

                if ($stmtInsert->execute()) {
                    $_SESSION['success'] = "Invoice Setting Created Successfully";
                } else {
                    $_SESSION['error'] = "Database execution error: " . $stmtInsert->error;
                }

                $stmtInsert->close();
                header("Location: invoice-settings.php");
                exit;

            } else {
                $_SESSION['error'] = $uploadedLogo['error'];
                header("Location: invoice-settings.php");
                exit;
            }

        }

    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: invoice-settings.php");
        exit;
    }
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <meta name="author" content="" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Invoice Settings</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
        
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />

    <link rel="stylesheet" href="assets/css/animate.css" />

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css" />

    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css" />

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css" />

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css" />

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css" />
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css" />

    <link rel="stylesheet" href="assets/css/style.css" />

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
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
            <div class="content settings-content">
                <div class="page-header settings-pg-header">
                    <div class="add-item d-flex">
                        <div class="page-title">
                            <h4>Settings</h4>
                            <h6>Manage your settings on portal</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i
                                    data-feather="rotate-ccw" class="feather-rotate-ccw"></i></a>
                        </li>
                        <li>
                            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i
                                    data-feather="chevron-up" class="feather-chevron-up"></i></a>
                        </li>
                    </ul>
                </div>
                <div class="row">
                    <div class="col-xl-12">
                        <div class="settings-wrapper d-flex">
                            <div class="sidebars settings-sidebar theiaStickySidebar" id="sidebar2">
                                <div class="sidebar-inner slimscroll">
                                    <div id="sidebar-menu5" class="sidebar-menu">
                                        <ul>
                                            <li class="submenu-open">
                                                <ul>

                                                    <li class="submenu">
                                                        <a href="javascript:void(0);" class="subdrop"><i
                                                                data-feather="airplay"></i><span>System
                                                                Settings</span><span class="menu-arrow"></span></a>
                                                        <ul>
                                                            <li><a href="system-settings.php">System
                                                                    Settings</a></li>
                                                            <li><a href="email-settings.php">Email</a>
                                                            </li>
                                                            <li><a href="company-settings.php">Company Settings</a>
                                                            </li>

                                                        </ul>
                                                        <a href="javascript:void(0);"><i
                                                                data-feather="archive"></i><span>App
                                                                Settings</span><span class="menu-arrow"></span></a>
                                                        <ul>
                                                            <li><a href="invoice-settings.php">Invoice</a></li>
                                                        </ul>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-page-wrap">
                                <form action="" enctype="multipart/form-data" method="post">
                                    <div class="setting-title">
                                        <h4>Invoice Settings</h4>
                                    </div>
                                    <input type="hidden" name="invoice_settings_id"
                                        value="<?= isset($invoiceSettings['invoice_settings_id']) ? $invoiceSettings['invoice_settings_id'] : "" ?>">

                                    <div class="company-info border-0">
                                        <ul class="logo-company">
                                            <li>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="logo-info me-0 mb-3 mb-md-0">
                                                            <h6>Invoice Logo</h6>
                                                            <p>
                                                                Upload Logo of your Company to display in
                                                                Invoice
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="profile-pic-upload mb-0 me-0">
                                                            <div class="new-employee-field">
                                                                <div class="mb-3 mb-md-0">
                                                                    <div class="image-upload mb-0">
                                                                        <input type="file" accept=".png,.jpeg,.jpg,.svg"
                                                                            name="invoiceLogo" />
                                                                        <div class="image-uploads">
                                                                            <h4>
                                                                                <i data-feather="upload"></i>Upload
                                                                            </h4>
                                                                        </div>
                                                                    </div>
                                                                    <span>For better preview recommended size is
                                                                        450px x 450px. Max size 5mb.</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="new-logo ms-auto">
                                                            <a href="#"><img
                                                                    src="<?= isset($invoiceSettings["logo"]) ? $invoiceSettings["logo"] : "assets/img/logo-small.png" ?>"
                                                                    alt="Logo" /></a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="logo-info me-0 mb-3 mb-md-0">
                                                            <h6>Invoice Template</h6>
                                                            <p>
                                                                Upload Template of your Company to display in
                                                                Invoice
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="profile-pic-upload mb-0 me-0">
                                                            <div class="new-employee-field">
                                                                <div class="mb-3 mb-md-0">
                                                                    <div class="image-upload mb-0">
                                                                        <input type="file" accept=".pdf"
                                                                            name="invoiceTemplate" />
                                                                        <div class="image-uploads">
                                                                            <h4>
                                                                                <i data-feather="upload"></i>Upload
                                                                            </h4>
                                                                        </div>
                                                                    </div>
                                                                    <span>Only PDF format is Supported.</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="col-md-2">
                                                        <div class="new-logo ms-auto">
                                                            <a href="#"><img src="assets/img/logo-small.png"
                                                                    alt="Logo" /></a>
                                                        </div>
                                                    </div> -->
                                                </div>
                                            </li>
                                        </ul>

                                        <div class="localization-info">
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Invoice Prefix</h6>
                                                        <p>Add prefix to your invoice</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <input type="text" class="form-control"
                                                            value="<?= isset($invoiceSettings['invoice_prefix']) ? $invoiceSettings['invoice_prefix'] : "VIS" ?>"
                                                            name="invoicePrefix" />
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Invoice Due</h6>
                                                        <p>Select due date to display in Invoice</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div
                                                        class="localization-select d-flex align-items-center fixed-width">
                                                        <select class="select">
                                                            <option>5</option>
                                                            <option>6</option>
                                                            <option>7</option>
                                                        </select>
                                                        <span class="ms-2">Days</span>
                                                    </div>
                                                </div>
                                            </div> -->
                                            <!-- <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Invoice Round Off</h6>
                                                        <p>Value Roundoff in Invoice</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div
                                                        class="localization-select d-flex align-items-center width-custom">
                                                        <div
                                                            class="status-toggle modal-status d-flex justify-content-between align-items-center me-3">
                                                            <input type="checkbox" id="user3" class="check"
                                                                checked="" />
                                                            <label for="user3" class="checktoggle"></label>
                                                        </div>
                                                        <select class="select">
                                                            <option>Round Off Up</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div> -->
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Show HSN Code</h6>
                                                        <p>Show / Hide HSN Code in Invoice</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select d-flex align-items-center">
                                                        <div
                                                            class="status-toggle modal-status d-flex justify-content-between align-items-center me-3">
                                                            <input type="checkbox" id="user4" class="check" checked=""
                                                                name="showHsnCode" />
                                                            <label for="user4" class="checktoggle"></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Invoice Header Terms</h6>
                                                    </div>
                                                </div>
                                                <div class="col-sm-8">
                                                    <div class="mb-3">
                                                        <textarea rows="4" class="form-control" name="invoiceHeader"
                                                            placeholder="Type your message"><?= isset($invoiceSettings['header_terms']) ? $invoiceSettings['header_terms'] : "" ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Invoice Footer Terms</h6>
                                                    </div>
                                                </div>
                                                <div class="col-sm-8">
                                                    <div class="mb-3">
                                                        <textarea rows="4" class="form-control" name="invoiceFooter"
                                                            placeholder="Type your message"><?= isset($invoiceSettings['footer_terms']) ? $invoiceSettings['footer_terms'] : "" ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer-btn">
                                        <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">
                                            Cancel
                                        </button>
                                        <button type="submit" name="submit" class="btn btn-submit">
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="bdc682687eabc865c45f0657-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/js/theme-script.js" type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/js/script.js" type="bdc682687eabc865c45f0657-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="bdc682687eabc865c45f0657-|49" defer=""></script>
</body>

</html>