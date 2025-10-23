<?php
ob_start();
session_start();
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// Define the upload directory
$uploadDirectory = 'public/upload/emailSetting/images/';

try {
    $stmtFetch = $db->prepare("SELECT * FROM email_settings");
    $stmtFetch->execute();
    $emailSettingData = $stmtFetch->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

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


} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}


// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {

    try {
        // echo "<pre>";
        // print_r($_POST);
        // exit;

        $title = trim($_POST['title']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $host = trim($_POST['host']);
        $port = trim($_POST['port']);



        $stmtInsert = $db->prepare("INSERT INTO email_settings (email_from_title,email_address,email_password,email_host,email_port) 
        VALUES(?,?,?,?,?)");
        $stmtInsert->bind_param("ssssi", $title, $email, $password, $host, $port);

        if ($stmtInsert->execute()) {
            $_SESSION['success'] = "Email Settings created successfully";
            header("Location: email-settings.php");
            exit;
        }
    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-settings.php");
        exit;
    }

}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    try {
        $id = trim($_POST['editEmailSettingId']);
        $title = trim($_POST['editEmailSettingTitle']);
        $email = trim($_POST['editEmailSettingEmail']);
        $password = trim($_POST['editEmailSettingPassword']);
        $host = trim($_POST['editEmailSettingHost']);
        $port = trim($_POST['editEmailSettingPort']);
        $status = trim($_POST['editEmailSettingStatus']);

        $stmtUpdate = $db->prepare("UPDATE email_settings SET email_from_title = ?,email_address = ?, email_password = ?, email_host = ?, email_port = ?,is_active=? WHERE email_settings_id = ?");
        $stmtUpdate->bind_param("ssssiii", $title, $email, $password, $host, $port, $status, $id);

        if ($stmtUpdate->execute()) {
            $_SESSION['success'] = "Email Settings updated successfully";
            header("Location: email-settings.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to update settings.";
            header("Location: email-settings.php");
            exit;
        }
    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-settings.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['emailSettingId'])) {
    try {

        $id = trim($_POST['emailSettingId']);

        $stmtDelete = $db->prepare("DELETE FROM email_settings WHERE email_settings_id = ?");
        $stmtDelete->bind_param("i", $id);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => true,
                'message' => 'Email setting deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => 'Failed to delete email setting.'
            ]);
        }
        exit;

    } catch (\Throwable $th) {
        echo json_encode([
            'status' => false,
            'message' => $th->getMessage()
        ]);
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send-mail'])) {
    try {


        $email = $_POST['email'];

        $host = !empty($emailSettingData[0]['email_host']) ? $emailSettingData[0]['email_host'] : getenv("SMTP_HOST");
        $userName = !empty($emailSettingData[0]['email_address']) ? $emailSettingData[0]['email_address'] : getenv('SMTP_USER_NAME');
        $password = !empty($emailSettingData[0]['email_password']) ? $emailSettingData[0]['email_password'] : getenv('SMTP_PASSCODE');
        $port = !empty($emailSettingData[0]['email_port']) ? $emailSettingData[0]['email_port'] : getenv('SMTP_PORT');


        // echo "<pre>";
        // print_r($host);
        // print_r($userName);
        // print_r($password);
        // print_r($port);
        // exit;

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $userName;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = $port;
        $mail->setFrom($userName, 'Mailer Bot');
        $mail->isHTML(true);

        // Set recipient and subject
        $mail->clearAddresses();
        $mail->addAddress($email, 'Test Recipient');
        $mail->Subject = '✅ Test Email from Mail Settings';
        $mail->Body = '<h3>This is a test email to verify your SMTP settings.</h3><p>If you received this email, your configuration is correct.</p>';

        // Send mail
        if ($mail->send()) {
            $_SESSION['success'] = "Test email sent successfully to {$email}.";
        } else {
            $_SESSION['error'] = "Failed to send test email: " . $mail->ErrorInfo;
        }


        header("Location: email-settings.php");
        exit;


    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-settings.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addAddress'])) {

    try {
        //code...


        if ($_POST['type'] === "insert") {
            $addressEmailSettingId = $_POST['addressEmailSettingId'];
            $emailAddress = $_POST['emailAddress'];
            $phone = $_POST['phone'];
            $addressLine = $_POST['addressLine'];
            $linkedinUrl = $_POST['linkedinUrl'];
            $instagramUrl = $_POST['instagramUrl'];
            $facebookUrl = $_POST['facebookUrl'];
            $mailLogo = $_FILES['mailLogo'];

            if ($mailLogo['name'] != "") {
                $uploadedLogo = uploadImage($mailLogo, $uploadDirectory);
                if ($uploadedLogo['status']) {
                    $logoUrl = $uploadedLogo['full_path'];
                } else {
                    $_SESSION['error'] = $uploadedLogo['error'];
                    header("Location: email-settings.php");
                    exit;
                }
            }

            $db->begin_transaction();

            $stmtAddAddress = $db->prepare("UPDATE email_settings SET 
             logo_url = ?,
             support_email = ?,
             phone = ?,
             address_line1 = ?,
             ig_url = ?,
             fb_url = ?,
             linkedin_url = ?
             WHERE email_settings_id = ?");

            $stmtAddAddress->bind_param(
                "sssssssi",
                $logoUrl,
                $emailAddress,
                $phone,
                $addressLine,
                $instagramUrl,
                $facebookUrl,
                $linkedinUrl,
                $addressEmailSettingId
            );
            $stmtAddAddress->execute();
            $db->commit(); // Commit transaction

            $_SESSION['success'] = "Address Update Successfully";
            header("Location: email-settings.php");
        } else if ($_POST['type'] === "update") {
            $addressEmailSettingId = $_POST['addressEmailSettingId'];
            $emailAddress = $_POST['emailAddress'];
            $phone = $_POST['phone'];
            $addressLine = $_POST['addressLine'];
            $linkedinUrl = $_POST['linkedinUrl'];
            $instagramUrl = $_POST['instagramUrl'];
            $facebookUrl = $_POST['facebookUrl'];
            $mailLogo = $_FILES['mailLogo'];

            // exiting 
            $logoUrl = $emailSettingData[0]["logo_url"];

            if ($mailLogo['name'] != "") {
                $uploadedLogo = uploadImage($mailLogo, $uploadDirectory);
                if ($uploadedLogo['status']) {
                    $logoUrl = $uploadedLogo['full_path'];
                } else {
                    $_SESSION['error'] = $uploadedLogo['error'];
                    header("Location: email-settings.php");
                    exit;
                }
            }

            $db->begin_transaction();

            $stmtAddAddress = $db->prepare("UPDATE email_settings SET 
             logo_url = ?,
             support_email = ?,
             phone = ?,
             address_line1 = ?,
             ig_url = ?,
             fb_url = ?,
             linkedin_url = ?
             WHERE email_settings_id = ?");

            $stmtAddAddress->bind_param(
                "sssssssi",
                $logoUrl,
                $emailAddress,
                $phone,
                $addressLine,
                $instagramUrl,
                $facebookUrl,
                $linkedinUrl,
                $addressEmailSettingId
            );
            $stmtAddAddress->execute();
            $db->commit(); // Commit transaction

            $_SESSION['success'] = "Address Update Successfully";
            header("Location: email-settings.php");



        }
        exit;
    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-settings.php");
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
    <title>Email Settings</title>

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
                    duration: 3000,
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
                    duration: 3000,
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

                            <div class="settings-page-wrap w-50">
                                <div class="setting-title">
                                    <h4>Email Settings</h4>
                                </div>
                                <div class="page-header bank-settings justify-content-end">
                                    <div class="page-btn">
                                        <a href="#" class="btn btn-added" data-bs-toggle="modal"
                                            data-bs-target="#smtp-mail"><i data-feather="tool"
                                                class="me-2"></i>Connect</a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="card table-list-card">
                                            <div class="card-body">
                                                <div class="table-top">
                                                    <div class="search-set">
                                                        <div class="search-input">
                                                            <a href="" class="btn btn-searchset"><i
                                                                    data-feather="search"
                                                                    class="feather-search"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table datanew">
                                                        <thead>
                                                            <tr>
                                                                <th class="no-sort">
                                                                    <label class="checkboxs">
                                                                        <input type="checkbox" id="select-all" />
                                                                        <span class="checkmarks"></span>
                                                                    </label>
                                                                </th>
                                                                <th>Title</th>
                                                                <th>Email</th>
                                                                <th>Password</th>
                                                                <th>Host</th>
                                                                <th>Port</th>
                                                                <th>Status</th>
                                                                <th>Created At</th>
                                                                <th class="no-sort text-end">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($emailSettingData as $data): ?>
                                                                <tr>
                                                                    <td>
                                                                        <label class="checkboxs">
                                                                            <input type="checkbox" name="emailSettingsIds"
                                                                                value="<?php echo $data['email_settings_id'] ?>">
                                                                            <span class="checkmarks"></span>
                                                                        </label>
                                                                    </td>

                                                                    <td class="">
                                                                        <div class="productimgname">
                                                                            <a href="javascript:void(0);"
                                                                                class="product-img stock-img">
                                                                                <img src="<?= !empty($emailSettingData[0]["logo_url"]) ? $emailSettingData[0]["logo_url"] : "assets/img/products/stock-img-01.png" ?>"
                                                                                    alt="product">
                                                                            </a>
                                                                            <a href="javascript:void(0);"><?php echo $data['email_from_title'] ?>
                                                                            </a>
                                                                        </div>
                                                                    </td>
                                                                    <td class="ref-number">
                                                                        <?php echo $data['email_address'] ?>
                                                                    </td>
                                                                    <td><?php echo $data['email_password'] ?></td>
                                                                    <td><?php echo $data['email_host'] ?></td>
                                                                    <td><?php echo $data['email_port'] ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($data['is_active'] == 1) { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-success">Active</span>
                                                                        <?php } else { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-danger">Inactive</span>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td><?php $date = new DateTime($data['created_at']);
                                                                    echo $date->format('d M Y') ?>
                                                                    <td class="text-center">
                                                                        <a class="action-set" href="javascript:void(0);"
                                                                            data-bs-toggle="dropdown" aria-expanded="true">
                                                                            <i class="fa fa-ellipsis-v"
                                                                                aria-hidden="true"></i>
                                                                        </a>
                                                                        <ul class="dropdown-menu">

                                                                            <li>
                                                                                <a href="javascript:void(0);"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#edit-smtp-mail"
                                                                                    data-emailsetting-id="<?php echo $data['email_settings_id'] ?>"
                                                                                    data-emailsetting-title="<?php echo $data['email_from_title'] ?>"
                                                                                    data-emailsetting-email="<?php echo $data['email_address'] ?>"
                                                                                    data-emailsetting-password="<?php echo $data['email_password'] ?>"
                                                                                    data-emailsetting-host="<?php echo $data['email_host'] ?>"
                                                                                    data-emailsetting-port="<?php echo $data['email_port'] ?>"
                                                                                    data-emailsetting-status="<?php echo $data['is_active'] ?>"
                                                                                    class="editButton dropdown-item"><i
                                                                                        data-feather="edit"
                                                                                        class="info-img"></i>Edit
                                                                                </a>
                                                                            </li>

                                                                            <li>
                                                                                <a href="javascript:void(0);"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#add-mail"
                                                                                    data-emailSetting-id="<?php echo $data['email_settings_id'] ?>"
                                                                                    class="dropdown-item addAddressButton mb-0"><i
                                                                                        data-feather="plus-circle"
                                                                                        class="info-img"></i>Add Address
                                                                                </a>
                                                                            </li>

                                                                            <li>
                                                                                <a href="javascript:void(0);"
                                                                                    data-emailSetting-id="<?php echo $data['email_settings_id'] ?>"
                                                                                    class="dropdown-item deleteButton mb-0"><i
                                                                                        data-feather="trash-2"
                                                                                        class="info-img"></i>Delete </a>
                                                                            </li>

                                                                            <li>
                                                                                <a href="javascript:void(0);"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#test-mail"
                                                                                    class="dropdown-item sendMail mb-0"><i
                                                                                        data-feather="send"
                                                                                        class="info-img"></i>Send Mail </a>
                                                                            </li>
                                                                        </ul>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>

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
                </div>
            </div>
        </div>
    </div>



    <div class="modal fade" id="smtp-mail">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>SMTP</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">From Title <span> *</span></label>
                                            <input type="text" class="form-control" name="title"
                                                placeholder="From Title" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">From Email Address <span> *</span></label>
                                            <input type="email" class="form-control" name="email"
                                                placeholder="From Email Address" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Email Password <span> *</span></label>
                                            <input type="password" class="form-control" placeholder="*******"
                                                name="password" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label"> Email Host <span> *</span></label>
                                            <input type="text" class="form-control" name="host" placeholder="Email Host"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Port <span> *</span></label>
                                            <input type="number" min="0" class="form-control" name="port"
                                                placeholder="Port" required>
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

    <div class="modal fade" id="edit-smtp-mail">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>SMTP</h4>
                            </div>
                            <div
                                class="status-toggle modal-status d-flex justify-content-between align-items-center ms-auto me-2">
                                <input type="checkbox" id="user5" class="check" checked="">
                                <label for="user5" class="checktoggle"> </label>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <input type="hidden" id="editEmailSettingId" name="editEmailSettingId">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">From Title <span> *</span></label>
                                            <input type="text" class="form-control" name="editEmailSettingTitle"
                                                id="editEmailSettingTitle" placeholder="From Title" required>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">From Email Address <span> *</span></label>
                                            <input type="email" class="form-control" id="editEmailSettingEmail"
                                                name="editEmailSettingEmail" required>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <div class="input-blocks">
                                            <label>Password</label>
                                            <div class="pass-group">
                                                <input type="password" class="pass-input" id="editEmailSettingPassword"
                                                    name="editEmailSettingPassword" required />
                                                <span class="fas toggle-password fa-eye-slash"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label"> Email Host <span> *</span></label>
                                            <input type="text" class="form-control" id="editEmailSettingHost"
                                                name="editEmailSettingHost" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Port <span> *</span></label>
                                            <input type="number" class="form-control" id="editEmailSettingPort"
                                                name="editEmailSettingPort" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Status</label>
                                            <select class="form-control" name="editEmailSettingStatus"
                                                id="editEmailSettingStatus">
                                                <option>Select</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
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

    <div class="modal fade" id="add-mail">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Add Address</h4>
                            </div>
                            <div
                                class="status-toggle modal-status d-flex justify-content-between align-items-center ms-auto me-2">
                                <input type="checkbox" id="user5" class="check" checked="">
                                <label for="user5" class="checktoggle"> </label>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post" enctype="multipart/form-data">
                                <input type="hidden" id="addressEmailSettingId" name="addressEmailSettingId">
                                <input type="hidden" name="type"
                                    value="<?= !empty($emailSettingData[0]['logo_url']) ? "update" : "insert" ?>">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Logo <span> *</span></label>
                                            <input type="file" class="form-control" name="mailLogo"
                                                placeholder="From Title" accept=".png,.jpeg,.jpg,.webp,.svg">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address <span> *</span></label>
                                            <input type="email" class="form-control" name="emailAddress"
                                                value="<?= !empty($emailSettingData[0]['support_email']) ? $emailSettingData[0]['support_email'] : "" ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label">Phone <span> *</span></label>
                                            <input type="tel" class="form-control" name="phone"
                                                value="<?= !empty($emailSettingData[0]['phone']) ? $emailSettingData[0]['phone'] : "" ?>"
                                                required>
                                        </div>
                                    </div>


                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label"> Address Line <span> *</span></label>
                                            <input type="text" class="form-control" name="addressLine"
                                                value="<?= !empty($emailSettingData[0]['address_line1']) ? $emailSettingData[0]['address_line1'] : "" ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Linkedin Url <span> *</span></label>
                                            <input type="url" class="form-control" name="linkedinUrl"
                                                value="<?= !empty($emailSettingData[0]['linkedin_url']) ? $emailSettingData[0]['linkedin_url'] : "" ?>"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Instagram Url <span> *</span></label>
                                            <input type="url" class="form-control" name="instagramUrl"
                                                value="<?= !empty($emailSettingData[0]['ig_url']) ? $emailSettingData[0]['ig_url'] : "" ?>"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label"> Facebook Url <span> *</span></label>
                                            <input type="url" class="form-control" name="facebookUrl"
                                                value="<?= !empty($emailSettingData[0]['fb_url']) ? $emailSettingData[0]['fb_url'] : "" ?>"
                                                required>
                                        </div>
                                    </div>

                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="addAddress" class="btn btn-submit">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="test-mail">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Test Mail</h4>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Enter Email Address <span> *</span></label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="send-mail" class="btn btn-submit">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="f91900af2b42b9590ae5c88c-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="f91900af2b42b9590ae5c88c-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>

    <script src="assets/js/script.js" type="f91900af2b42b9590ae5c88c-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="f91900af2b42b9590ae5c88c-|49" defer=""></script>

    <script>
        $(document).ready(function (e) {

            $(document).on('click', '.editButton', function () {

                let editEmailSettingId = $(this).data("emailsetting-id");
                let editEmailSettingTitle = $(this).data("emailsetting-title");
                let editEmailSettingEmail = $(this).data("emailsetting-email");
                let editEmailSettingPassword = $(this).data("emailsetting-password");
                let editEmailSettingHost = $(this).data("emailsetting-host");
                let editEmailSettingPort = $(this).data("emailsetting-port");
                let editEmailSettingStatus = $(this).data("emailsetting-status");

                $('#editEmailSettingId').val(editEmailSettingId);
                $('#editEmailSettingTitle').val(editEmailSettingTitle);
                $('#editEmailSettingEmail').val(editEmailSettingEmail);
                $('#editEmailSettingPassword').val(editEmailSettingPassword);
                $('#editEmailSettingHost').val(editEmailSettingHost);
                $('#editEmailSettingPort').val(editEmailSettingPort);
                $('#editEmailSettingStatus').val(editEmailSettingStatus);
            });

            $(document).on('click', '.addAddressButton', function () {
                let editEmailSettingId = $(this).data("emailsetting-id");
                $('#addressEmailSettingId').val(editEmailSettingId);
            });


            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let editEmailSettingId = $(this).data("emailsetting-id");

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!",

                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'email-settings.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { emailSettingId: editEmailSettingId },
                            success: function (response) {

                                let result = JSON.parse(response);
                                console.log(result);

                                // Show success message and reload the page
                                Swal.fire(
                                    'Deleted!',
                                    'The Email Setting delete has been deleted.',
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

        })
    </script>

</body>

</html>