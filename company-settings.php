<?php
ob_start();
session_start();
require './utility/env.php';
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}
require "./database/config.php";
require "./utility/env.php";

// Define the upload directory
$uploadDirectory = 'public/upload/company/images/';

try {
    $stmtFetch = $db->prepare("SELECT * FROM company_settings");
    $stmtFetch->execute();
    $companySettings = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);

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
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'ico'];

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

    try {

        if (isset($_POST['company_id']) && $_POST['company_id'] != "") {
            // UPDATE LOGIC
            $companyId = $_POST["company_id"];
            $companyName = $_POST["company_name"];
            $companyEmail = $_POST["company_email"];
            $companyPhone = $_POST["company_phone"];
            $companyWebsite = $_POST["company_website"];
            $companyAddress = $_POST["address"];
            $companyCounty = $_POST["country"];
            $companyState = $_POST["state"];
            $companyCity = $_POST["city"];
            $companyPinCode = $_POST["postal_code"];

            $logoUrl = null;
            $faviconUrl = null;

            // Check if logo file is uploaded
            if (isset($_FILES["company_logo"]) && $_FILES["company_logo"]["error"] === 0) {
                $uploadedLogo = uploadImage($_FILES["company_logo"], $uploadDirectory);
                if ($uploadedLogo['status']) {
                    $logoUrl = $uploadDirectory . $uploadedLogo['data'];
                } else {
                    $_SESSION['error'] = $uploadedLogo['error'];
                    header("Location: company-settings.php");
                    exit;
                }
            }

            // Check if favicon file is uploaded
            if (isset($_FILES["favicon"]) && $_FILES["favicon"]["error"] === 0) {
                $uploadedFavicon = uploadImage($_FILES["favicon"], $uploadDirectory);
                if ($uploadedFavicon['status']) {
                    $faviconUrl = $uploadDirectory . $uploadedFavicon['data'];
                } else {
                    $_SESSION['error'] = $uploadedFavicon['error'];
                    header("Location: company-settings.php");
                    exit;
                }
            }

            // Prepare the update query with conditional logo/favicon
            $query = "UPDATE company_settings SET 
                company_name = ?, 
                company_email = ?, 
                company_phone = ?, 
                company_website = ?, 
                address = ?, 
                country = ?, 
                state = ?, 
                city = ?, 
                postal_code = ?";

            $params = [
                $companyName,
                $companyEmail,
                $companyPhone,
                $companyWebsite,
                $companyAddress,
                $companyCounty,
                $companyState,
                $companyCity,
                $companyPinCode
            ];

            if ($logoUrl) {
                $query .= ", company_logo = ?";
                $params[] = $logoUrl;
            }

            if ($faviconUrl) {
                $query .= ", favicon = ?";
                $params[] = $faviconUrl;
            }

            $query .= " WHERE company_id = ?";
            $params[] = $companyId;

            $stmtUpdate = $db->prepare($query);

            if (!$stmtUpdate) {
                $_SESSION['error'] = "Database prepare error: " . $db->error;
                header("Location: company-settings.php");
                exit;
            }

            // Dynamically bind parameters
            $types = str_repeat('s', count($params) - 1) . 'i'; // last is id (int)
            $stmtUpdate->bind_param($types, ...$params);

            if ($stmtUpdate->execute()) {
                $_SESSION['success'] = "Company Setting Updated Successfully";
            } else {
                $_SESSION['error'] = "Database execution error: " . $stmtUpdate->error;
            }

            $stmtUpdate->close();
            header("Location: company-settings.php");
            exit;

        } else {
            //code...
            $companyName = $_POST["company_name"];
            $companyEmail = $_POST["company_email"];
            $companyPhone = $_POST["company_phone"];
            $companyWebsite = $_POST["company_website"];
            $companyAddress = $_POST["address"];
            $companyCounty = $_POST["country"];
            $companyState = $_POST["state"];
            $companyCity = $_POST["city"];
            $companyPinCode = $_POST["postal_code"];
            $companyLogo = $_FILES["company_logo"];
            $companyFavicon = $_FILES["favicon"];


            $uploadedLogo = uploadImage($companyLogo, $uploadDirectory);
            $uploadedFavicon = uploadImage($companyFavicon, $uploadDirectory);


            // exit;

            // Proceed only if logo upload is successful
            if ($uploadedLogo['status'] && $uploadedFavicon['status']) {
                $logoUrl = $uploadDirectory . $uploadedLogo['data'];
                $faviconUrl = $uploadDirectory . $uploadedFavicon['data'];

                // Insert into database
                $stmtInsert = $db->prepare("INSERT INTO company_settings (
                company_name, company_email, company_phone, company_website,
                address, country, state, city, postal_code,
                company_logo, favicon
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if ($stmtInsert === false) {
                    $_SESSION['error'] = "Database prepare error: " . $db->error;
                    header("Location: company-settings.php");
                    exit;
                }

                $stmtInsert->bind_param(
                    "sssssssssss",
                    $companyName,
                    $companyEmail,
                    $companyPhone,
                    $companyWebsite,
                    $companyAddress,
                    $companyCounty,
                    $companyState,
                    $companyCity,
                    $companyPinCode,
                    $logoUrl,
                    $faviconUrl
                );

                if ($stmtInsert->execute()) {
                    $_SESSION['success'] = "Company Setting Created Successfully";
                } else {
                    $_SESSION['error'] = "Database execution error: " . $stmtInsert->error;
                }

                $stmtInsert->close();
                header("Location: company-settings.php");
                exit;
            } else {
                $_SESSION['error'] = $uploadedLogo['error'];
                header("Location: company-settings.php");
                exit;
            }
        }


    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: company-settings.php");
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
    <title>Company Settings </title>

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
    <!-- <div id="global-loader">
        <div class="whirly-loader"> </div>
    </div> -->

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
                                <form action="" method="post" enctype="multipart/form-data">
                                    <div class="setting-title">
                                        <h4>Company Settings</h4>
                                    </div>
                                    <input type="hidden" name="company_id"
                                        value="<?= isset($companySettings['company_id']) ? $companySettings['company_id'] : "" ?>">

                                    <div class="company-info">
                                        <div class="card-title-head">
                                            <h6><span><i data-feather="zap"></i></span>Company Information</h6>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-4 col-lg-6 col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Company Name</label>
                                                    <input type="text" class="form-control" name="company_name"
                                                        value="<?= isset($companySettings['company_name']) ? $companySettings['company_name'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-4 col-lg-6 col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Company Email Address</label>
                                                    <input type="email" class="form-control" name="company_email"
                                                        value="<?= isset($companySettings['company_email']) ? $companySettings['company_email'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control" name="company_phone"
                                                        value="<?= isset($companySettings['company_phone']) ? $companySettings['company_phone'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Website</label>
                                                    <input type="text" class="form-control" name="company_website"
                                                        value="<?= isset($companySettings['company_website']) ? $companySettings['company_website'] : "" ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="company-info company-images">
                                        <div class="card-title-head">
                                            <h6><span><i data-feather="image"></i></span>Company Images</h6>
                                        </div>
                                        <ul class="logo-company">
                                            <li class="d-flex align-items-center">
                                                <div class="logo-info">
                                                    <h6>Company Logo</h6>
                                                    <p>Upload Logo of your Company to display in website</p>
                                                </div>
                                                <div class="profile-pic-upload mb-0">
                                                    <div class="new-employee-field">
                                                        <div class="mb-0">
                                                            <div class="image-upload mb-0">
                                                                <input type="file" name="company_logo"
                                                                    accept=".png,.jpg,.jpeg,.svg">
                                                                <div class="image-uploads">
                                                                    <h4><i data-feather="upload"></i>Upload Photo</h4>
                                                                </div>
                                                            </div>
                                                            <span>Recommended size is 450px x 450px. Max size
                                                                5MB.</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="new-logo ms-auto">
                                                    <a href="#"><img
                                                            src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo-small.png" ?>"
                                                            alt="Logo"></a>
                                                </div>
                                            </li>

                                            <li class="d-flex align-items-center">
                                                <div class="logo-info">
                                                    <h6>Favicon</h6>
                                                    <p>Upload Favicon of your Company to display in website</p>
                                                </div>
                                                <div class="profile-pic-upload mb-0">
                                                    <div class="new-employee-field">
                                                        <div class="mb-0">
                                                            <div class="image-upload mb-0">
                                                                <input type="file" name="favicon"
                                                                    accept=".ico,.png,.svg">

                                                                <div class="image-uploads">
                                                                    <h4><i data-feather="upload"></i>Upload Photo</h4>
                                                                </div>
                                                            </div>
                                                            <span>Recommended size is 450px x 450px. Max size
                                                                5MB.</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="new-logo ms-auto">
                                                    <a href="#"><img
                                                            src="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/logo-small.png" ?>"
                                                            alt="Logo"></a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="company-address">
                                        <div class="card-title-head">
                                            <h6><span><i data-feather="map-pin"></i></span>Address</h6>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="address"
                                                        value="<?= isset($companySettings['address']) ? $companySettings['address'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-3 col-lg-4 col-md-3">
                                                <div class="mb-3">
                                                    <label class="form-label">Country</label>
                                                    <input type="text" class="form-control" name="country"
                                                        value="<?= isset($companySettings['country']) ? $companySettings['country'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-3 col-lg-4 col-md-3">
                                                <div class="mb-3">
                                                    <label class="form-label">State / Province</label>
                                                    <input type="text" class="form-control" name="state"
                                                        value="<?= isset($companySettings['state']) ? $companySettings['state'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-3 col-lg-4 col-md-3">
                                                <div class="mb-3">
                                                    <label class="form-label">City</label>
                                                    <input type="text" class="form-control" name="city"
                                                        value="<?= isset($companySettings['city']) ? $companySettings['city'] : "" ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-3 col-lg-4 col-md-3">
                                                <div class="mb-3">
                                                    <label class="form-label">Postal Code</label>
                                                    <input type="text" class="form-control" name="postal_code"
                                                        value="<?= isset($companySettings['postal_code']) ? $companySettings['postal_code'] : "" ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal-footer-btn">
                                        <button type="button" class="btn btn-cancel me-2"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="submit" class="btn btn-submit">Save Changes</button>
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

    <script src="assets/js/feather.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="288f8b537e3532d20fab8dd4-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="288f8b537e3532d20fab8dd4-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>

    <script src="assets/js/script.js" type="288f8b537e3532d20fab8dd4-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="288f8b537e3532d20fab8dd4-|49" defer=""></script>
</body>

</html>