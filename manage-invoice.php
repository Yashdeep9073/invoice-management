<?php
session_start();

require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}


try {
    $stmtFetchInvoices = $db->prepare("SELECT * FROM invoice 
    INNER JOIN customer
    ON customer.customer_id = invoice.customer_id
    LEFT JOIN admin
    ON admin.admin_id = invoice.created_by 
    WHERE invoice.is_active = 1
    ");
    if ($stmtFetchInvoices->execute()) {
        $invoices = $stmtFetchInvoices->get_result();
    } else {
        $_SESSION['error'] = 'Error for fetching customers';
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoiceId'])) {

    $invoiceId = intval($_POST['invoiceId']);

    if ($invoiceId <= 0) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid invoice ID.'
        ]);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE invoice SET is_active = 0 WHERE invoice_id = ?");
        $stmt->bind_param("i", $invoiceId);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected invoice deleted successfully.'
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



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIdForMail'])) {

    try {
        $invoiceId = intval($_POST['invoiceIdForMail']);
        $stmtFetchCustomer = $db->prepare("SELECT invoice.*, customer.*, tax.* FROM invoice 
        INNER JOIN customer
        ON customer.customer_id = invoice.customer_id
        INNER JOIN tax
        ON tax.tax_id = invoice.tax
        WHERE invoice.is_active = 1 AND invoice.invoice_id = ? AND invoice.status = 'PENDING'");

        $stmtFetchCustomer->bind_param('i', $invoiceId);

        if ($stmtFetchCustomer->execute()) {
            $invoices = $stmtFetchCustomer->get_result()->fetch_all(MYSQLI_ASSOC);

            // Check if the invoice exists
            if (empty($invoices)) {
                echo json_encode([
                    'status' => 404,
                    'message' => "Invoice Status is Not Pending"
                ]);
                exit; // Stop further execution
            }

        } else {
            echo json_encode([
                'status' => 500,
                'message' => "Database Query Execution Failed"
            ]);
            exit;
        }


        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER_NAME');
        $mail->Password = getenv('SMTP_PASSCODE');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
        $mail->Port = 465;
        $mail->setFrom(getenv('SMTP_USER_NAME'), 'Vibrantick InfoTech Solution');
        $mail->isHTML(true);

        // Prepare statement for updating reminder_count
        $stmtUpdate = $db->prepare('UPDATE invoice SET reminder_count = reminder_count + 1 WHERE invoice_id = ?');

        $emailBody = '
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Reminder</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: #f4f4f4;
                }

                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .header {
                    background-color: #007bff;
                    padding: 20px;
                    text-align: center;
                    color: #ffffff;
                }

                .header img {
                    max-width: 150px;
                    height: auto;
                    background-color: #fff;
                }

                .header h1 {
                    margin: 10px 0;
                    font-size: 24px;
                }

                .content {
                    padding: 20px;
                }

                .content p {
                    line-height: 1.6;
                    color: #333333;
                }

                .invoice-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }

                .invoice-table th,
                .invoice-table td {
                    border: 1px solid #dddddd;
                    padding: 12px;
                    text-align: left;
                }

                .invoice-table th {
                    background-color: #007bff;
                    color: #ffffff;
                    font-weight: bold;
                }

                .invoice-table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }

                .invoice-table tr:hover {
                    background-color: #f1f1f1;
                }

                .footer {
                    background-color: #f4f4f4;
                    padding: 15px;
                    text-align: center;
                    font-size: 12px;
                    color: #666666;
                }

                .footer a {
                    color: #007bff;
                    text-decoration: none;
                    margin: 0 10px;
                }

                .footer img {
                    width: 24px;
                    height: 24px;
                    vertical-align: middle;
                }

                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #007bff;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }

                @media only screen and (max-width: 600px) {
                    .container {
                        width: 100%;
                        margin: 10px;
                    }

                    .header img {
                        max-width: 120px;
                    }

                    .header h1 {
                        font-size: 20px;
                    }

                    .invoice-table th,
                    .invoice-table td {
                        font-size: 14px;
                        padding: 8px;
                    }
                }
            </style>
        </head>

        <body>
            <div class="container">
                <!-- Header with Logo -->
                <div class="header">
                    <img src="https://vibrantick.in/assets/images/logo/footer.png" alt="Vibrantick InfoTech Solution Logo">
                    <h1>Payment Reminder</h1>
                </div>
                <!-- Content -->
                <div class="content">
                    <p>Dear ' . $invoices['0']['customer_name'] . ',</p>
                    <p>We hope this message finds you well. The following invoice(s) are overdue. Kindly make the payment at
                        your earliest convenience to avoid any service interruptions.</p>
                    <!-- Invoice Table -->
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Invoice Number</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Tax</th>
                                <th>Discount</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>' . $invoices['0']['invoice_number'] . '</td>
                                <td>' . $invoices['0']['due_date'] . '</td>
                                <td>' . $invoices['0']['amount'] . '</td>
                                <td>' . $invoices['0']['tax_rate'] . '</td>
                                <td>' . $invoices['0']['discount'] . '</td>
                                <td>' . $invoices['0']['total_amount'] . '</td>
                            </tr>
                        </tbody>
                    </table>
                    <!-- Call to Action -->
                    <p>Please settle the outstanding amount at your earliest convenience. For any questions or assistance,
                        contact our support team at <a href="mailto:support@vibrantick.org">support@vibrantick.org</a> or call
                        <a href="tel:+919870443528">+91-9870443528</a>.</p>
                    <p>Thank you for your prompt attention to this matter.</p>
                    <p>Best regards,<br>Vibrantick InfoTech Solution Team</p>
                </div>
                <!-- Footer -->
                <div class="footer">
                    <p>© 2025 Vibrantick InfoTech Solution. All rights reserved.</p>
                    <p>Vibrantick InfoTech Solution | D-185, Phase 8B, Sector 74, SAS Nagar | <a
                            href="mailto:support@vibrantick.org">support@vibrantick.org</a></p>
                    <p>
                        <a href="https://www.linkedin.com/company/vibrantick-infotech-solutions/posts/?feedView=all"
                            target="_blank">
                            <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn">
                        </a>
                        <a href="https://www.instagram.com/vibrantickinfotech/" target="_blank">
                            <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram">
                        </a>
                        <a href="https://www.facebook.com/vibranticksolutions/" target="_blank">
                            <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook">
                        </a>
                    </p>
                </div>
            </div>
        </body>

        </html>
        ';

        $mail->clearAddresses();
        $mail->addAddress($invoices['0']['customer_email'], $invoices['0']['customer_name']);
        $mail->Subject = 'Payment Reminder: Overdue Invoices';
        $mail->Body = $emailBody;

        if ($mail->send()) {

            $stmtUpdate->bind_param('i', $invoiceId);
            if (!$stmtUpdate->execute()) {
                echo "Failed to update reminder_count for invoice {$invoice['invoice_id']}\n";
            }
            echo json_encode([
                'status' => 200,
                'message' => 'The Mail has been send to ' . $invoices['0']['customer_name'],
                'data' => $invoices['0']['customer_email']
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 403,
                'message' => 'Unable to Send Mail to ' . $invoices['0']['customer_name'],
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

// delete multiple invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoiceIds'])) {


    $invoiceIds = $_POST['invoiceIds'];

    // Validate: Must be an array of integers
    if (!is_array($invoiceIds)) {
        echo json_encode([
            'status' => 400,
            'message' => 'Invalid data format.'
        ]);
        exit;
    }

    try {
        // Prepare the SQL dynamically
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $types = str_repeat('i', count($invoiceIds)); // All integers

        $stmt = $db->prepare("UPDATE invoice SET is_active = 0 WHERE invoice_id IN ($placeholders)");
        $stmt->bind_param($types, ...$invoiceIds);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 200,
                'message' => 'Selected invoices deleted successfully.',
                'deleted_ids' => $invoiceIds
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
    <title>Manage Invoice</title>

    <link rel="shortcut icon" type="image/x-icon" href="assets/img/fav/vis-favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/plugins/daterangepicker/daterangepicker.css">

    <link rel="stylesheet" href="assets/plugins/summernote/summernote-bs4.min.css">

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
                            <h4>Invoice Report </h4>
                            <h6>Manage Your Invoice Report</h6>
                        </div>
                    </div>
                    <ul class="table-top-head">
                        <li>
                            <a data-bs-toggle="tooltip" class="multi-delete-button" data-bs-placement="top"
                                title="Delete"><img src="assets/img/icons/delete.png" alt="img" /></a>
                        </li>
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
                    <div class="page-btn">
                        <?php if ($isAdmin || hasPermission('Add Invoice', $privileges, $roleData['0']['role_name'])): ?>

                            <a href="add-invoice.php" class="btn btn-added"><i data-feather="plus-circle"
                                    class="me-2"></i>Add Invoice
                            </a>
                        <?php endif; ?>
                    </div>
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
                            <table id="myTable" class="table  datanew">
                                <thead>
                                    <tr>
                                        <th class="no-sort">
                                            <label class="checkboxs">
                                                <input type="checkbox" id="select-all">
                                                <span class="checkmarks"></span>
                                            </label>
                                        </th>
                                        <th>Invoice No</th>
                                        <th>Customer</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Created By</th>
                                        <th>Status</th>
                                        <th class="no-sort text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices->fetch_all(MYSQLI_ASSOC) as $invoice) { ?>
                                        <tr>
                                            <td>
                                                <label class="checkboxs">
                                                    <input type="checkbox" name="invoiceIds"
                                                        value="<?php echo $invoice['invoice_id'] ?>">
                                                    <span class="checkmarks"></span>
                                                </label>
                                            </td>
                                            <td class="ref-number"><?php echo $invoice['invoice_number'] ?></td>
                                            <td><?php echo $invoice['customer_name'] ?></td>
                                            <td><?php $date = new DateTime($invoice['due_date']);
                                            echo $date->format('d M Y') ?>
                                            </td>
                                            <td><?php echo $invoice['total_amount'] ?></td>
                                            <td><?php echo $invoice['admin_username'] ?></td>
                                            <td>
                                                <?php if ($invoice['status'] == 'PAID') { ?>
                                                    <span class="badge badge-lg bg-success">Paid</span>
                                                <?php } elseif ($invoice['status'] == 'CANCELLED') { ?>
                                                    <span class="badge badge-lg bg-danger">Cancelled</span>
                                                <?php } elseif ($invoice['status'] == 'PENDING') { ?>
                                                    <span class="badge badge-lg bg-warning">Pending</span>
                                                <?php } elseif ($invoice['status'] == 'REFUNDED') { ?>
                                                    <span class="badge badge-lg bg-primary">Refunded</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-center">
                                                <a class="action-set" href="javascript:void(0);" data-bs-toggle="dropdown"
                                                    aria-expanded="true">
                                                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a target="_blank"
                                                            href="view-invoice.php?id=<?php echo base64_encode($invoice['invoice_id']) ?>"
                                                            class="editStatus dropdown-item" data-admin-id=""><i
                                                                data-feather="eye" class="info-img"></i>Show
                                                            Detail</a>
                                                    </li>
                                                    <?php if ($isAdmin || hasPermission('Edit Invoice', $privileges, $roleData['0']['role_name'])): ?>

                                                        <li>
                                                            <a href="edit-invoice.php?id=<?php echo base64_encode($invoice['invoice_id']) ?>"
                                                                class="editButton dropdown-item"><i data-feather="edit"
                                                                    class="info-img"></i>Edit
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a target="_blank"
                                                            href="download-invoice.php?id=<?php echo base64_encode($invoice['invoice_id']) ?>"
                                                            class="qrCode dropdown-item"><i data-feather="download"
                                                                class="info-img"></i>Download
                                                        </a>
                                                    </li>
                                                    <?php if ($isAdmin || hasPermission('Delete Invoice', $privileges, $roleData['0']['role_name'])): ?>
                                                        <li>
                                                            <a href="javascript:void(0);"
                                                                data-invoice-id="<?php echo $invoice['invoice_id'] ?>"
                                                                class="dropdown-item deleteButton mb-0"><i
                                                                    data-feather="trash-2" class="info-img"></i>Delete </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($isAdmin || hasPermission('Send Mail', $privileges, $roleData['0']['role_name'])): ?>

                                                        <li>
                                                            <a href="javascript:void(0);"
                                                                data-invoice-id="<?php echo $invoice['invoice_id'] ?>"
                                                                class="dropdown-item sendMail mb-0"><i data-feather="send"
                                                                    class="info-img"></i>Send Mail </a>
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



    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/daterangepicker/daterangepicker.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/summernote/summernote-bs4.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/script.js" type="36113e2a9ce2b6f627c18ab9-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="36113e2a9ce2b6f627c18ab9-|49" defer=""></script>

    <script src="assets/js/custom.js"></script>


    <script>
        $(document).ready(function () {
            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                let invoiceId = $(this).data('invoice-id');

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
                            url: 'manage-invoice.php',
                            type: 'POST',
                            data: { invoiceId: invoiceId },
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
                                        'The invoice has been deleted.',
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


            $(document).on('click', '.sendMail', function (e) {
                e.preventDefault();

                let invoiceId = $(this).data('invoice-id');


                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    showCancelButton: true,
                    confirmButtonColor: "#ff9f43",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Send Mail!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: 'manage-invoice.php', // The PHP file that will handle the deletion
                            type: 'POST',
                            data: { invoiceIdForMail: invoiceId },
                            success: function (response) {
                                let result = JSON.parse(response);
                                console.log(result);

                                if (result.status == 200) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Send!',
                                        result.message,
                                        'success' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }
                                if (result.status == 404) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Error!',
                                        result.message,
                                        'error' // Added 'success' to show the success icon
                                    ).then(() => {
                                        // Reload the page
                                        location.reload();
                                    });
                                }

                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error sending the mail.',
                                    'error'
                                );
                            }
                        });
                    }
                });



            });

            $(document).on('click', '.multi-delete-button', function (e) {
                e.preventDefault();

                let invoiceIds = [];
                $('input[name="invoiceIds"]:checked').each(function () {
                    invoiceIds.push(parseInt($(this).val()));
                });

                if (invoiceIds.length == 0) {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Please select invoice!",
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
                            url: "manage-invoice.php",
                            type: "post",
                            data: { invoiceIds: invoiceIds },
                            success: function (response) {

                                Swal.fire(
                                    'Deleted!',
                                    'The Invoice has been deleted.',
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

        });
    </script>

</body>

</html>