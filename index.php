<?php

ob_start();
session_start();
error_reporting(0);
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';
require './utility/logGenerator.php';
require './utility/otpGenerator.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL") . "dashboard");
    exit();
}

try {

    function logRequestData($db, $requestInfo, $userId, $geoInfo)
    {
        // Prepare the SQL statement
        $stmt = $db->prepare("
        INSERT INTO logs (
            request_type, browser_name, browser_version, platform, is_mobile,
            user_agent, ip_address, request_method, request_uri, query_string,
            headers, content_type, accept_header, referer, xhr_requested,
            request_body, response_status, response_time,user_id, country, state, city
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

        if ($stmt === false) {
            error_log("Failed to prepare statement: " . $db->error);
            return false;
        }

        // Prepare the values
        $request_type = $requestInfo['type'] ?? null;
        $browser_name = $requestInfo['browser']['name'] ?? 'Unknown';
        $browser_version = $requestInfo['browser']['version'] ?? 'Unknown';
        $platform = $requestInfo['browser']['platform'] ?? 'Unknown';
        $is_mobile = $requestInfo['browser']['is_mobile'] ?? 0;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $request_method = $_SERVER['REQUEST_METHOD'] ?? null;
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $query_string = $_SERVER['QUERY_STRING'] ?? null;
        $headers = json_encode(getallheaders());
        $content_type = $_SERVER['CONTENT_TYPE'] ?? null;
        $accept_header = $_SERVER['HTTP_ACCEPT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $xhr_requested = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? 1 : 0;
        $request_body = file_get_contents('php://input');
        $response_status = http_response_code();
        $response_time = null; // Set this if measuring response time
        $country = $geoInfo['country'];
        $state = $geoInfo['state'];
        $city = $geoInfo['city'];

        // Bind parameters
        $stmt->bind_param(
            "ssssissssssssssiiiisss",
            $request_type,
            $browser_name,
            $browser_version,
            $platform,
            $is_mobile,
            $user_agent,
            $ip_address,
            $request_method,
            $request_uri,
            $query_string,
            $headers,
            $content_type,
            $accept_header,
            $referer,
            $xhr_requested,
            $request_body,
            $response_status,
            $response_time,
            $userId,
            $country,
            $state,
            $city
        );

        // Execute the statement
        if ($stmt->execute()) {
            $insert_id = $db->insert_id;
            $stmt->close();
            return $insert_id;
        } else {
            error_log("Failed to log request: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    $stmtFetch = $db->prepare("SELECT * FROM system_settings");
    $stmtFetch->execute();
    $data = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);
    $imageUrl = $data['auth_banner'];
    $isOtpActive = $data['is_otp_active'];

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);
    $domain = $_SERVER['HTTP_HOST'];


    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1  ");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);



} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Retrieve site key and secret key with basic validation
$siteKey = !empty($data['site_key']) ? $data['site_key'] : getenv('GOOGLE_RECAPTCHA_SITE_KEY');
$secretKey = !empty($data['secret_key']) ? $data['secret_key'] : getenv('GOOGLE_RECAPTCHA_SECRET_KEY');


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate user input
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $passwordInput = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $recaptcha = filter_input(INPUT_POST, 'g-recaptcha-response', FILTER_SANITIZE_STRING);

    // Check if reCAPTCHA is active in the database
    if (isset($data['is_recaptcha_active']) && $data['is_recaptcha_active'] == 1 && $data['domain'] == $domain) {
        // If reCAPTCHA is active, g-recaptcha-response must be present
        if (empty($recaptcha)) {
            $_SESSION['error'] = "Please complete the reCAPTCHA verification.";
            header("Location: index.php");
            exit;
        }

        if (!$secretKey) {
            $_SESSION['error'] = "reCAPTCHA configuration error. Please contact the administrator.";
            header("Location: index.php");
            exit;
        }

        // Use cURL for more robust HTTP request handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secretKey,
            'response' => $recaptcha
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $_SESSION['error'] = "reCAPTCHA verification failed due to network error. Please try again.";
            header("Location: index.php");
            exit;
        }

        $response = json_decode($response, true);

        if (!$response || !isset($response['success']) || !$response['success']) {
            $_SESSION['error'] = "reCAPTCHA verification failed. Please try again.";
            header("Location: index.php");
            exit;
        }
    }

    // Validate email and password
    if (!$emailInput || !$passwordInput) {
        $_SESSION['error'] = "Invalid input. Please fill in all fields correctly.";
        header("Location: index.php");
        exit;
    }

    // Database query to fetch admin
    $stmt = $db->prepare("SELECT * FROM admin WHERE admin_email = ? AND is_active = 1");
    $stmt->bind_param("s", $emailInput);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "No user found with this email address.";
        header("Location: index.php");
        exit;
    }

    $row = $result->fetch_assoc();

    // Check if admin is active
    if ($row['is_active'] != 1) {
        $_SESSION['error'] = "You are no longer an active member. Please contact the admin.";
        header("Location: index.php");
        exit;
    }

    // Verify password
    if (!password_verify($passwordInput, $row['admin_password'])) {
        $_SESSION['error'] = "Invalid password.";
        header("Location: index.php");
        exit;
    }

    $roleId = $row['admin_role'];
    // Fetch role details
    $stmtRolesData = $db->prepare("SELECT * FROM roles WHERE role_id = ?");
    $stmtRolesData->bind_param('i', $roleId);
    $stmtRolesData->execute();
    $roleData = $stmtRolesData->get_result()->fetch_all(MYSQLI_ASSOC);
    $adminId = $row['admin_id'];
    $adminName = $row['admin_username'];

    if ($isOtpActive === 1) {
        $otpResponse = otpGenerate($adminId, $db, $localizationSettings);
        $otp = $otpResponse['otp'];
        $otpId = base64_encode($otpResponse['otpId']);

        if (isset($otp)) {
            try {

                $stmtFetch = $db->prepare("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1");
                $stmtFetch->execute();
                $emailSettingData = $stmtFetch->get_result()->fetch_assoc();

                // === Email Settings Fallbacks ===
                $host = $emailSettingData['email_host'] ?? getenv("SMTP_HOST");
                $userName = $emailSettingData['email_address'] ?? getenv('SMTP_USER_NAME');
                $password = $emailSettingData['email_password'] ?? getenv('SMTP_PASSCODE');
                $port = $emailSettingData['email_port'] ?? getenv('SMTP_PORT');
                $fromTitle = $emailSettingData['email_from_title'] ?? "Vibrantick InfoTech Solution";
                $logoUrl = getenv("BASE_URL") . $emailSettingData['logo_url'] ?? 'https://vibrantick.in/assets/images/logo/footer.png ';
                $supportEmail = $emailSettingData['support_email'] ?? 'support@vibrantick.org';
                $phone = $emailSettingData['phone'] ?? '+919870443528';
                $address1 = $emailSettingData['address_line1'] ?? 'Vibrantick InfoTech Solution | D-185, Phase 8B, Sector 74, SAS Nagar';
                $linkedin = $emailSettingData['linkedin_url'] ?? 'https://www.linkedin.com/company/vibrantick-infotech-solutions/posts/?feedView=all';
                $instagram = $emailSettingData['ig_url'] ?? ' https://www.instagram.com/vibrantickinfotech/ ';
                $facebook = $emailSettingData['fb_url'] ?? 'https://www.facebook.com/vibranticksolutions/ ';
                $currentYear = date("Y");

                $stmtFetchEmailTemplates = $db->prepare("SELECT * FROM email_template WHERE is_active = 1 AND type = '2FA' ");
                $stmtFetchEmailTemplates->execute();
                $emailTemplate = $stmtFetchEmailTemplates->get_result()->fetch_array(MYSQLI_ASSOC);

                // === Email Template Fallbacks ===
                $templateTitle = $emailTemplate['email_template_title'] ?? 'Two-Factor Authentication';
                $emailSubject = $emailTemplate['email_template_subject'] ?? 'Your One-Time Password (OTP) for Login';


                $content1 = !empty($emailTemplate['content_1'])
                    ? nl2br(trim($emailTemplate['content_1']))
                    : '<p>We have received a login attempt on your account. For security, please verify your identity using the One-Time Password (OTP) below:</p>';

                $content2 = !empty($emailTemplate['content_2'])
                    ? nl2br(trim($emailTemplate['content_2']))
                    : '<p>If you did not try to log in, please ignore this email or contact our support team immediately.</p>
       <p>Thank you for keeping your account secure.<br>Vibrantick InfoTech Solution Team</p>';


                // Initialize PHPMailer
                $mail = new PHPMailer(true);
                $mail->SMTPDebug = 0; // Set to 2 for debugging
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->SMTPAuth = true;
                $mail->Username = $userName;
                $mail->Password = $password;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
                $mail->Port = $port;
                $mail->setFrom($userName, $fromTitle);
                $mail->isHTML(true);

                $emailBody = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$templateTitle}</title>
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
                        border-radius: 4px;
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
                    <!-- Header -->
                    <div class="header">
                        <img src="{$logoUrl}" alt="Logo" />
                        <h1>{$templateTitle}</h1>
                    </div>

                    <!-- Content -->
                    <div class="content">
                        <p>Dear {$adminName},</p>
                        {$content1}
                        <div style="margin:20px 0; padding:15px; background:#f1f1f1; text-align:center; border-radius:6px; font-size:22px; font-weight:bold; letter-spacing:3px; color:#007bff;">
                            {$otp}
                        </div>
                        {$content2}
                    </div>

                    <!-- Footer -->
                    <div class="footer">
                        <p>&copy; {$currentYear} {$fromTitle}. All rights reserved.</p>
                        <p>{$address1} <a href='mailto:{$supportEmail}'>{$supportEmail}</a></p>
                        <p>
                            <a href='{$linkedin}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/174/174857.png ' alt='LinkedIn'></a>
                            <a href='{$instagram}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/2111/2111463.png ' alt='Instagram'></a>
                            <a href='{$facebook}' target='_blank'><img src='https://cdn-icons-png.flaticon.com/24/733/733547.png ' alt='Facebook'></a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            HTML;

                $mail->clearAddresses();
                $mail->addAddress($emailInput, $adminName);
                $mail->Subject = $emailSubject;
                $mail->Body = $emailBody;

                if ($mail->send()) {
                    header("Location: otp.php");
                    $_SESSION['token'] = $otpId;
                    exit;
                } else {
                    $_SESSION['error'] = 'Unable to Send Mail to ' . $adminName;
                    header("Location: index.php");
                    exit;
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Server error please contact team';
                header("Location: index.php");
                exit;
            }
        }
    } else {
        // Store session values securely
        $_SESSION['admin_id'] = base64_encode($row['admin_id']);
        $_SESSION['admin_name'] = $row['admin_username'];
        $_SESSION['admin_role'] = $roleData[0]['role_name'];

        // Example usage 
        $requestInfo = detectRequestType();
        $geoInfo = $requestInfo['geo'];

        // echo "<pre>";
        // print_r($geoInfo);
        // print_r($requestInfo);
        // exit;
        logRequestData($db, $requestInfo, $adminId, $geoInfo);

        // Redirect to admin dashboard
        header("Location: " . getenv("BASE_URL") . "dashboard");
        exit;
    }

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
    <title>Login</title>
    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/feather.css">
    <link rel="stylesheet" href="assets/css/animate.css">

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
</head>

<body class="account-page">
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

        <div class="account-content">
            <div class="login-wrapper bg-img" <?php if (isset($imageUrl) && !empty($imageUrl)) {
                echo 'style="background-image: url(\'' . htmlspecialchars($imageUrl) . '\');"';
            } ?>>
                <div class="login-content">
                    <form action="" method="POST">
                        <div class="login-userset">
                            <div class="login-logo logo-normal">
                                <img src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo/vis-logo.png" ?>"
                                    alt="Logo">
                            </div>
                            <div class="account-wrapper">
                                <div class="login-userheading">

                                </div>

                                <div class="login-userheading">
                                    <h3>Sign In</h3>
                                    <h4>Access the Smartsheet panel using your email and passcode.</h4>
                                </div>
                                <div class="form-login mb-3">
                                    <label class="form-label">Email Address</label>
                                    <div class="form-addons">
                                        <input type="text" name="email" class="form-control" required>
                                        <img src="assets/img/icons/mail.svg" alt="img">
                                    </div>
                                </div>
                                <div class="form-login mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="pass-group">
                                        <input type="password" name="password" class="pass-input form-control" required>
                                        <span class="fas toggle-password fa-eye-slash"></span>
                                    </div>
                                </div>
                                <div class="form-login authentication-check">
                                    <div class="row">
                                        <div class="col-12 d-flex align-items-center justify-content-between">
                                            <!-- <div class="custom-control custom-checkbox">
                                                <label class="checkboxs ps-4 mb-0 pb-0 line-height-1">
                                                    <input type="checkbox" class="form-control">
                                                    <span class="checkmarks"></span> Remember me
                                                </label>
                                            </div> -->
                                            <!-- <div class="text-end">
                                                <a class="forgot-link" href="forgot-password.php">Forgot Password?</a>
                                            </div> -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-login">
                                    <?php if ($data['is_recaptcha_active'] == 1 && $data['domain'] == $domain): ?>
                                        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                                        <div class="g-recaptcha"
                                            data-sitekey="<?= htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') ?>"
                                            data-callback="enableSubmit" style="border:none;" align="center"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-login">
                                    <button type="submit" name="submit" id="submit" class="btn btn-login" <?php echo ($data['is_recaptcha_active'] == 1 && $data['domain'] == $domain) ? 'disabled' : ''; ?>>
                                        Sign In
                                    </button>
                                </div>

                                <div class="form-sociallink">
                                    <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                                        <p> &copy; 2020 - <?php echo date('Y'); ?> <a
                                                href="<?php echo isset($companySettings['company_website']) ? $companySettings['company_website'] : "https://vibrantick.in/" ?>"
                                                target="_blank"><?php echo isset($companySettings['company_name']) ? $companySettings['company_name'] : "Vibrantick
                                                Infotech Solutions Pvt Ltd." ?>
                                            </a> All rights reserved</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
</body>

<script src="assets/js/jquery-3.7.1.min.js"></script>
<script src="assets/js/feather.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/theme-script.js"></script>
<script src="assets/js/script.js"></script>
<script src="https://unpkg.com/@popperjs/core@2"></script>

<script>
    // JavaScript callback to enable the submit button after reCAPTCHA verification
    function enableSubmit() {
        document.getElementById('submit').disabled = false;
    }

</script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    $(document).ready(function () {


        // Block right-click
        $(document).on('contextmenu', function (e) {
            e.preventDefault();
            return false;
        });

        // Block specific keys (F12, Ctrl+Shift+I, etc.)
        $(document).on('keydown', function (e) {
            // Block F12 (developer tools)
            if (e.key === 'F12') {
                e.preventDefault();
                return false;
            }

            // Block Ctrl+Shift+I (developer tools)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                return false;
            }

            // Block Ctrl+U (view source)
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                return false;
            }
        });

    });
</script>

</html>