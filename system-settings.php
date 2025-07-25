<?php
ob_start();
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("location: index.php");
}
require "./database/config.php";

// Define the upload directory
$uploadDirectory = 'public/upload/auth/images/';

try {
    $stmtFetch = $db->prepare("SELECT * FROM system_settings");
    $stmtFetch->execute();
    $data = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);
    $imageUrl = $data['auth_banner'];
    $isRecaptchaActive = $data['is_recaptcha_active'];

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
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

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

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    // Check if a file was uploaded
    if (isset($_FILES['authBanner']) && $_FILES['authBanner']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImage($_FILES['authBanner'], $uploadDirectory);

        if ($result['status']) {
            // Prepare the full path to store in the database
            $imagePath = $uploadDirectory . $result['data'];

            // Update the database with the image path
            $stmtInsert = $db->prepare("UPDATE system_settings SET auth_banner = ?");
            if (!$stmtInsert) {
                $_SESSION['error'] = "Database error: " . $db->error;
                header("Location: system-settings.php");
                exit;
            }

            $stmtInsert->bind_param('s', $imagePath); // Bind the full path
            if ($stmtInsert->execute()) {
                $_SESSION['success'] = 'Successfully Uploaded Banner';
                header("Location: system-settings.php");
                exit;
            } else {
                $_SESSION['error'] = "Database error: " . $stmtInsert->error;
                header("Location: system-settings.php");
                exit;
            }
        } else {
            $_SESSION['error'] = $result['error'];
            header("Location: system-settings.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Error: No file uploaded or an error occurred during upload.";
        header("Location: system-settings.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['captchaStatus'])) {
    try {

        $status = $_POST['captchaStatus'];

        $stmtUpdate = $db->prepare("UPDATE system_settings SET is_recaptcha_active = ? ");
        $stmtUpdate->bind_param("i", $status);
        $stmtUpdate->execute();

        //throw $th;
        echo json_encode([
            "status" => 200,
            "message" => "Captcha status updated successfully",

        ]);
        exit;

    } catch (\Throwable $th) {
        //throw $th;
        echo json_encode([
            "status" => 500,
            "error" => $th->getMessage(),
            "message" => "Error while updating captcha status",

        ]);
        exit;
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
    <title>System Settings</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

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
                                                <?php
                                                require("./settings-siderbar.php");
                                                ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-page-wrap">
                                <div class="setting-title">
                                    <h4>System Settings</h4>
                                </div>
                                <div class="row">
                                    <div class="col-xxl-4 col-xl-6 col-lg-12 col-md-6 d-flex">
                                        <div class="connected-app-card d-flex w-100">
                                            <ul class="w-100">
                                                <li class="flex-column align-items-start">
                                                    <div
                                                        class="d-flex align-items-center justify-content-between w-100">
                                                        <div class="security-type d-flex align-items-center">
                                                            <span class="system-app-icon">
                                                                <img src="assets/img/icons/app-icon-07.svg" alt="">
                                                            </span>
                                                            <div class="security-title">
                                                                <h5>Google Captcha</h5>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="status-toggle modal-status d-flex justify-content-between align-items-center ms-2">
                                                            <input type="checkbox" id="user1" class="check"
                                                                <?= $isRecaptchaActive ? 'checked' : '' ?>>
                                                            <label for="user1" class="checktoggle"></label>
                                                        </div>
                                                    </div>
                                                    <p>Captcha helps protect you from spam and password decryption</p>
                                                </li>

                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-xxl-4 col-xl-6 col-lg-12 col-md-6 d-flex">
                                        <div class="connected-app-card d-flex w-100">
                                            <ul class="w-100">
                                                <li class="flex-column align-items-start">
                                                    <div
                                                        class="d-flex align-items-center justify-content-between w-100">
                                                        <div class="security-type d-flex align-items-center">
                                                            <span class="system-app-icon">
                                                                <img src="assets/img/icons/drag-drop.svg" alt="">
                                                            </span>
                                                            <div class="security-title">
                                                                <h5>Auth Banner</h5>
                                                            </div>
                                                        </div>
                                                        <!-- <div
                                                            class="status-toggle modal-status d-flex justify-content-between align-items-center ms-2">
                                                            <input type="checkbox" id="user4" class="check" checked="">
                                                            <label for="user4" class="checktoggle"> </label>
                                                        </div> -->
                                                    </div>
                                                    <p>Provides detailed information about geographical regions and
                                                        sites worldwide.</p>
                                                </li>
                                                <li>
                                                    <div class="integration-btn">
                                                        <a href="" data-bs-toggle="modal"
                                                            data-bs-target="#auth-banner"><i data-feather="tool"
                                                                class="me-2"></i>View</a>
                                                    </div>
                                                </li>
                                            </ul>
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


    <div class="modal fade" id="google-captcha">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Configure Google Captcha</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Google Rechaptcha Site Key <span> *</span></label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Google Rechaptcha Secret Key <span>
                                                    *</span></label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-submit">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="auth-banner">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Upload Auth Banner</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post" enctype="multipart/form-data">
                                <div class="row">

                                    <div class="col-lg-12">
                                        <div class="form-group image-upload-down">
                                            <div class="image-upload download">
                                                <input type="file" accept=".png,.gif,.jpg,.jpeg" name="authBanner"
                                                    required />
                                                <div class="image-uploads">
                                                    <img src="assets/img/download-img.png" alt="img" />
                                                    <h4>Drag and drop a <span>file to upload</span></h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accordion-body">
                                            <div class="text-editor add-list add">
                                                <div class="col-lg-12">
                                                    <div class="add-choosen mb-3">
                                                        <div class="phone-img ms-0">
                                                            <img src="<?php echo isset($imageUrl) ? $imageUrl : "assets/img/authentication/auth-login-bg.jpg"; ?>"
                                                                alt="image" />
                                                            <a href="javascript:void(0);"><i data-feather="x"
                                                                    class="x-square-add remove-product"></i></a>
                                                        </div>

                                                    </div>
                                                </div>
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

    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="2d09356b56ee97a9b1de7823-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/js/script.js" type="2d09356b56ee97a9b1de7823-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="2d09356b56ee97a9b1de7823-|49" defer=""></script>

    <script>
        $(document).ready(function () {

            // Initialize Notyf
            const notyf = new Notyf({
                duration: 3000,
                position: { x: "center", y: "top" },
                types: [
                    {
                        type: "success",
                        background: "#4dc76f",
                        textColor: "#FFFFFF",
                        dismissible: false,
                    },
                    {
                        type: "error",
                        background: "#ff1916",
                        textColor: "#FFFFFF",
                        dismissible: false,
                        duration: 3000,
                    },
                ],
            });

            $(document).on("change", "#user1", function () {
                if ($(this).is(":checked")) {
                    console.log("Status: Checked (ON)");

                    $.ajax({
                        url: "system-settings.php",
                        type: "POST",
                        data: { captchaStatus: 1 },
                        success: function (response, xhr) {
                            let result = JSON.parse(response);

                            console.log(result);

                            if (result.status == 200) {
                                notyf.success(result.message || "Status updated successfully");
                            }


                        },
                        error: function (error, xhr) {
                            console.error(error);
                            notyf.error(error || "Error while updating status");
                        }
                    })

                } else {

                    $.ajax({
                        url: "system-settings.php",
                        type: "POST",
                        data: { captchaStatus: 0 },
                        success: function (response, xhr) {
                            let result = JSON.parse(response);

                            console.log(result);

                            if (result.status == 200) {
                                notyf.success(result.message || "Status updated successfully");
                            }


                        },
                        error: function (error, xhr) {
                            console.error(error);
                            notyf.error(error || "Error while updating status");
                        }
                    })
                }
            });
        });
    </script>



</body>

</html>