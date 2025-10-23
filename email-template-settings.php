<?php
ob_start();
session_start();
require './utility/env.php';
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}
require "./database/config.php";

// Define the upload directory
$uploadDirectory = 'public/upload/invoice/images/';

try {

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtFetch = $db->prepare("SELECT * FROM invoice_settings");
    $stmtFetch->execute();
    $invoiceSettings = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchEmailTemplate = $db->prepare("SELECT *  FROM email_template");
    $stmtFetchEmailTemplate->execute();
    $emailTemplates = $stmtFetchEmailTemplate->get_result()->fetch_all(MYSQLI_ASSOC);

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
        $name = $_POST['name'];
        $subject = $_POST['subject'];
        $type = $_POST['type'];
        $content1 = $_POST['content1'];
        $content2 = $_POST['content2'];

        $db->begin_transaction();

        $stmtInsert = $db->prepare("INSERT INTO email_template 
        (email_template_title,email_template_subject,type,content_1,content_2)
        VALUES(?,?,?,?,?)
        ");

        $stmtInsert->bind_param(
            "sssss",
            $name,
            $subject,
            $type,
            $content1,
            $content2,
        );

        $stmtInsert->execute();
        $db->commit();

        $_SESSION['success'] = "Template Added Successfully";
        header("Location: email-template-settings.php");
        exit;

    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-template-settings.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['templateId'])) {

    $templateId = intval($_POST['templateId']);

    if ($templateId <= 0) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid template ID.'
        ]);
        exit;
    }

    try {
        $stmtDelete = $db->prepare("DELETE FROM email_template WHERE idemail_template = ?");
        $stmtDelete->bind_param("i", $templateId);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected template deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => $stmt->error
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }

    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {

    try {
        // print_r($_POST);
        // exit;

        // Retrieve POST data
        $templateId = $_POST['editTemplateId'] ?? null;
        $title = $_POST['editTemplateTitle'] ?? null;
        $subject = $_POST['editTemplateSubject'] ?? null;
        $type = $_POST['editTemplateType'] ?? null;
        $content1 = $_POST['editTemplateContent1'] ?? null;
        $content2 = $_POST['editTemplateContent2'] ?? null;


        if ($templateId && $title && $subject) {
            // Prepare SQL update query
            $stmtUpdate = $db->prepare("UPDATE email_template 
                    SET email_template_title = ?, 
                        email_template_subject = ?, 
                        type = ?, 
                        content_1 = ?, 
                        content_2 = ?
                    WHERE idemail_template = ?");

            $stmtUpdate->bind_param(
                "sssssi",
                $title,
                $subject,
                $type,
                $content1,
                $content2,
                $templateId
            );

            $stmtUpdate->execute();
            $_SESSION['success'] = "Template Updated Successfully";
            header("Location: email-template-settings.php");
            exit;

        } else {
            throw new Exception("Missing required fields.");
        }

    } catch (\Throwable $th) {
        //throw $th;
        $_SESSION['error'] = $th->getMessage();
        header("Location: email-template-settings.php");
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
    <title>Email Template</title>

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

    <!-- Include stylesheet -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />

    <!-- Include the Quill library -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
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
                                    <h4>Email Template</h4>
                                </div>
                                <div class="page-header bank-settings justify-content-end">
                                    <div class="page-btn">
                                        <a href="#" class="btn btn-added" data-bs-toggle="modal"
                                            data-bs-target="#add-tax"><i data-feather="plus-circle" class="me-2"></i>Add
                                            New
                                            Template</a>
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
                                                                <th>Name</th>
                                                                <th>Subject</th>
                                                                <th>Status</th>
                                                                <th>Created On</th>
                                                                <th class="no-sort text-end">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($emailTemplates as $template): ?>
                                                                <tr>
                                                                    <td>
                                                                        <label class="checkboxs">
                                                                            <input type="checkbox" />
                                                                            <span class="checkmarks"></span>
                                                                        </label>
                                                                    </td>
                                                                    <td><?= $template['email_template_title'] ?></td>
                                                                    <td><?= $template['email_template_subject'] ?></td>
                                                                    <td>
                                                                        <?php if ($template['is_active'] == 1) { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-success">Active</span>
                                                                        <?php } else { ?>
                                                                            <span
                                                                                class="badge badge-lg bg-danger">Inactive</span>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td><?php $date = new DateTime($template['created_at']);
                                                                    echo $date->format(isset($localizationSettings["date_format"]) ? $localizationSettings["date_format"] : "d M Y") ?>
                                                                    </td>
                                                                    <td class="action-table-data justify-content-end">
                                                                        <div class="edit-delete-action">
                                                                            <a class="editButton me-2 p-2" href="#"
                                                                                data-template-id="<?= $template['idemail_template'] ?>"
                                                                                data-template-title="<?= $template['email_template_title'] ?>"
                                                                                data-template-subject="<?= $template['email_template_subject'] ?>"
                                                                                data-template-type="<?= $template['type'] ?>"
                                                                                data-template-content1="<?= base64_encode($template['content_1']) ?>"
                                                                                data-template-content2="<?= base64_encode($template['content_2']) ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#edit-tax">
                                                                                <i data-feather="edit"
                                                                                    class="feather-edit"></i>
                                                                            </a>
                                                                            <a class="deleteButton p-2"
                                                                                data-template-id="<?= $template['idemail_template'] ?>"
                                                                                href="javascript:void(0);">
                                                                                <i data-feather="trash-2"
                                                                                    class="feather-trash-2"></i>
                                                                            </a>
                                                                        </div>
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

    <div class="modal fade" id="add-tax">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Add Template</h4>
                            </div>
                            <div
                                class="status-toggle modal-status d-flex justify-content-between align-items-center ms-auto me-2">
                                <input type="checkbox" id="user1" class="check" checked="" />
                                <label for="user1" class="checktoggle"> </label>
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
                                            <label class="form-label">Name <span> *</span></label>
                                            <input type="text" class="form-control" name="name" />
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Subject <span> *</span></label>
                                            <input type="text" class="form-control" name="subject" />
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Type <span> *</span></label>
                                            <select class="form-select" name="type">
                                                <option>Select</option>
                                                <option value="ISSUED">Issued</option>
                                                <option value="REMINDER">Reminder</option>
                                                <option value="2FA">2FA</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <label>Content 1</label>
                                        <div class="input-blocks summer-description-box">
                                            <div id="editor-content1">
                                                <p>We hope this message finds you well. The following invoice(s) are
                                                    overdue. Kindly make the payment at
                                                    your earliest convenience to avoid any service interruptions.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <label>Content 2</label>
                                        <div class="input-blocks summer-description-box">
                                            <div id="editor-content2">
                                                <p>Please settle the outstanding amount at your earliest convenience.
                                                    For any questions or assistance,
                                                    contact our support team at <a
                                                        href="mailto:support@vibrantick.org">support@vibrantick.org</a>
                                                    or call
                                                    <a href="tel:+919870443528">+91-9870443528</a>.
                                                </p>
                                                <p>Thank you for your prompt attention to this matter.</p>
                                                <p>Best regards,<br>Vibrantick InfoTech Solution Team.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" name="content1" id="content1">
                                    <input type="hidden" name="content2" id="content2">

                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="submit" name="submit" class="btn btn-submit"
                                        id="saveBtn">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit-tax">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>Edit Tax Rates</h4>
                            </div>
                            <div
                                class="status-toggle modal-status d-flex justify-content-between align-items-center ms-auto me-2">
                                <input type="checkbox" id="user4" class="check" checked="" />
                                <label for="user4" class="checktoggle"> </label>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form action="" method="post">
                                <input type="hidden" name="editTemplateId" id="editTemplateId">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span> *</span></label>
                                            <input type="text" class="form-control" name="editTemplateTitle"
                                                id="editTemplateTitle" />
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Subject <span> *</span></label>
                                            <input type="text" class="form-control" name="editTemplateSubject"
                                                id="editTemplateSubject" />
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <div class="mb-0">
                                            <label class="form-label">Type <span> *</span></label>
                                            <select class="form-select" name="editTemplateType" id="editTemplateType">
                                                <option>Select</option>
                                                <option value="ISSUED">Issued</option>
                                                <option value="REMINDER">Reminder</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <label>Content 1</label>
                                        <div class="input-blocks summer-description-box">
                                            <div id="edit-editor-content1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <label>Content 2</label>
                                        <div class="input-blocks summer-description-box">
                                            <div id="edit-editor-content2">
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" name="editTemplateContent1" id="editTemplateContent1">
                                    <input type="hidden" name="editTemplateContent2" id="editTemplateContent2">

                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="submit" name="edit" class="btn btn-submit" id="editBtn">Saves
                                        Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/script.js" type="e4d57c54f4cedf5e1ec30f61-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="e4d57c54f4cedf5e1ec30f61-|49" defer=""></script>

    <script>
        $(document).ready(function () {
            const quill1 = new Quill('#editor-content1', {
                theme: 'snow'
            });

            const quill2 = new Quill('#editor-content2', {
                theme: 'snow'
            });

            $('#saveBtn').click(function () {
                const htmlContent1 = quill1.root.innerHTML;
                const htmlContent2 = quill2.root.innerHTML;

                $('#content1').val(htmlContent1);
                $('#content2').val(htmlContent2);
            });

            const editQuill1 = new Quill('#edit-editor-content1', {
                theme: 'snow'
            });

            const editQuill2 = new Quill('#edit-editor-content2', {
                theme: 'snow'
            });



            $(document).on('click', '.deleteButton', function (event) {
                let templateId = $(this).data('template-id');

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
                            url: window.location.href,
                            type: 'POST',
                            data: { templateId: templateId },
                            success: function (response) {
                                let result;
                                try {
                                    result = JSON.parse(response);
                                } catch (e) {
                                    Swal.fire('Error!', 'Invalid server response.', 'error');
                                    return;
                                }

                                if (result.status === 200) {
                                    Swal.fire(
                                        'Deleted!',
                                        'The template has been deleted.',
                                        'success'
                                    ).then(() => {
                                        location.reload(); // Reload page after confirmation
                                    });
                                } else {
                                    Swal.fire('Error!', result.message || 'Deletion failed.', 'error');
                                }
                            },
                            error: function () {
                                Swal.fire(
                                    'Error!',
                                    'There was an error contacting the server.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });


            $(document).on('click', '.editButton', function () {

                let templateId = $(this).data('template-id');
                let templateTitle = $(this).data('template-title');
                let templateSubject = $(this).data('template-subject');
                let templateType = $(this).data('template-type');
                let templateContent1 = $(this).data('template-content1');
                let templateContent2 = $(this).data('template-content2');

                $('#editTemplateId').val(templateId);
                $('#editTemplateTitle').val(templateTitle);
                $('#editTemplateSubject').val(templateSubject);
                $('#editTemplateType').val(templateType);

                // Set content in Quill editors
                editQuill1.root.innerHTML = atob(templateContent1);
                editQuill2.root.innerHTML = atob(templateContent2);

                // // Optionally, also update hidden inputs if needed
                $('#editTemplateContent1').val(atob(templateContent1));
                $('#editTemplateContent2').val(atob(templateContent2));
            });

            $('#editBtn').click(function (e) {
                // Get latest HTML from Quill editors
                const updatedContent1 = editQuill1.root.innerHTML;
                const updatedContent2 = editQuill2.root.innerHTML;

                // // Optionally encode in base64 (if you stored it like that in DB)
                $('#editTemplateContent1').val(updatedContent1);
                $('#editTemplateContent2').val(updatedContent2);

            });

        });
    </script>


    <script>

    </script>

</body>

</html>