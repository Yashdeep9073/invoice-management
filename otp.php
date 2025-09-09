<?php

ob_start();
session_start();
error_reporting(0);
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';
require './utility/logGenerator.php';

if (!isset($_SESSION["token"])) {
    header("Location: index.php");
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

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);


} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp'])) {
    try {
        $otpId = base64_decode($_SESSION["token"]);
        $otp = $_POST['otp'];

        // Validate OTP format (optional but recommended)
        if (!preg_match('/^\d{6}$/', $otp)) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid OTP format. Please enter a 6-digit code.",
            ]);
            exit();
        }

        // Database query to fetch admin OTP
        $stmtOtp = $db->prepare("SELECT * FROM admin_otp WHERE otp_id = ? AND otp_code = ? AND is_used = 0");
        $stmtOtp->bind_param("is", $otpId, $otp);
        $stmtOtp->execute();
        $otpResult = $stmtOtp->get_result();

        // Check if OTP exists and is valid
        if ($otpResult->num_rows === 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid or expired OTP. Please try again.",
                "clientOtp" => $otp
            ]);
            exit();
        }

        $otpData = $otpResult->fetch_assoc();

        // Database query to fetch admin
        $stmtAdminData = $db->prepare("SELECT * FROM admin WHERE admin_id = ? AND is_active = 1");
        $stmtAdminData->bind_param("i", $otpData['admin_id']);
        $stmtAdminData->execute();
        $result = $stmtAdminData->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => 400,
                "message" => "No active user found.",
            ]);
            exit();
        }

        $adminData = $result->fetch_assoc();

        // Store session values securely
        $_SESSION['admin_id'] = base64_encode($adminData['admin_id']);
        $_SESSION['admin_name'] = $adminData['admin_username'];

        $roleId = $adminData['admin_role'];
        // Fetch role details
        $stmtRolesData = $db->prepare("SELECT * FROM roles WHERE role_id = ?");
        $stmtRolesData->bind_param('i', $roleId);
        $stmtRolesData->execute();
        $roleData = $stmtRolesData->get_result()->fetch_assoc();

        if ($roleData) {
            $_SESSION['admin_role'] = $roleData['role_name'];
        }

        // Log request data
        $requestInfo = detectRequestType();
        $geoInfo = $requestInfo['geo'];
        logRequestData($db, $requestInfo, $adminId, $geoInfo);

        // Mark OTP as used
        $stmtOtpUpdate = $db->prepare("UPDATE admin_otp SET is_used = 1 WHERE otp_id = ? AND otp_code = ? AND admin_id = ?");
        $stmtOtpUpdate->bind_param("isi", $otpId, $otp, $adminData['admin_id']);
        $stmtOtpUpdate->execute();

        echo json_encode([
            "status" => 200,
            "message" => "OTP Verified Successfully. Redirecting to dashboard...",
        ]);

        unset($_SESSION["token"]);
        exit();



    } catch (\Throwable $th) {
        error_log("OTP Verification Error: " . $th->getMessage()); // Log the actual error
        echo json_encode([
            "status" => 500,
            "message" => "Server Error. Please try again later.",
        ]);
        exit();
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
    <title>Otp</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">


    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="account-page">
    <div id="global-loader">
        <div class="whirly-loader"></div>
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


    <div class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper bg-img" <?php if (isset($imageUrl) && !empty($imageUrl)) {
                echo 'style="background-image: url(\'' . htmlspecialchars($imageUrl) . '\');"';
            } ?>>
                <div class="login-content">
                    <div class="login-userset">
                        <div class="login-userset">
                            <div class="login-logo logo-normal">
                                <img src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo/vis-logo.png" ?>"
                                    alt="img">
                            </div>
                        </div>

                        <div class="login-userheading">
                            <h3>Login With Your Email Address</h3>
                            <h4 class="verfy-mail-content">We sent a verification code to your email. Enter the code
                                from the email in the field below</h4>
                        </div>
                        <form class="digit-group" onsubmit="return false;">
                            <div class="wallet-add">
                                <div class="otp-box">
                                    <div class="forms-block text-center">
                                        <input type="text" id="digit-1" maxlength="1">
                                        <input type="text" id="digit-2" maxlength="1">
                                        <input type="text" id="digit-3" maxlength="1">
                                        <input type="text" id="digit-4" maxlength="1">
                                        <input type="text" id="digit-5" maxlength="1">
                                        <input type="text" id="digit-6" maxlength="1">
                                    </div>
                                </div>
                            </div>
                            <div class="Otp-expire text-center">
                                <p>Otp will expire in 09 :10</p>
                            </div>
                            <div class="form-login mt-4">
                                <button type="submit" class="btn btn-login">Verify My Account</button>
                            </div>
                        </form>
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
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <script src="assets/js/feather.min.js" type="1ae8763dacfbfa380f0f190f-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="1ae8763dacfbfa380f0f190f-text/javascript"></script>
    <script src="assets/js/script.js" type="1ae8763dacfbfa380f0f190f-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="1ae8763dacfbfa380f0f190f-|49" defer=""></script>

    <script>
        $(document).ready(function () {
            let inputs = $(".digit-group input");

            // Move forward / backward
            inputs.on("keyup", function (e) {
                let $this = $(this);
                let index = inputs.index(this);

                // Check if the input is a valid digit
                let value = $this.val();
                if (value !== '' && !/^\d$/.test(value)) {
                    // Not a valid digit, show error and clear the input
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Input',
                        text: 'Please enter only numbers (0-9)',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    $this.val(''); // Clear the invalid input
                    return;
                }

                if ($this.val().length === this.maxLength) {
                    inputs.eq(index + 1).focus();
                } else if (e.key === "Backspace" && index > 0) {
                    inputs.eq(index - 1).focus();
                }
            });

            // Also prevent non-numeric input on keypress
            inputs.on("keypress", function (e) {
                // Allow: backspace, delete, tab, escape, enter
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                    // Allow: Ctrl+A, Command+A
                    (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl+C, Command+C
                    (e.keyCode === 67 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl+V, Command+V
                    (e.keyCode === 86 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl+X, Command+X
                    (e.keyCode === 88 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: home, end, left, right, down, up
                    (e.keyCode >= 35 && e.keyCode <= 40)) {
                    return; // Allow these keys
                }

                // Ensure that the key is a number
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                    // Show error popup
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Input',
                        text: 'Please enter only numbers (0-9)',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });

            // Handle paste (auto-fill OTP)
            inputs.on("paste", function (e) {
                e.preventDefault(); // Prevent default paste behavior

                let pasteData = (e.originalEvent.clipboardData || window.clipboardData).getData("text");
                if (!/^\d+$/.test(pasteData)) return; // only digits

                let chars = pasteData.split("");
                inputs.each(function (i, input) {
                    $(input).val(chars[i] || "");
                });

                // Focus on the last input after pasting
                inputs.last().focus();
            });


            $(document).on("submit", ".digit-group", async function (e) {
                e.preventDefault();

                e.preventDefault();

                // Get OTP from all input fields
                let otp = '';
                let inputs = $(this).find('input[type="text"]');

                inputs.each(function () {
                    otp += $(this).val();
                });

                // Validate OTP (check if all fields are filled)
                if (otp.length !== 6 || !/^\d{6}$/.test(otp)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid OTP',
                        text: 'Please enter a valid 6-digit OTP code',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    return;
                }
                await $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { otp: otp },
                    success: function (response) {
                        let result;
                        try {
                            result = JSON.parse(response);
                        } catch (e) {
                            Swal.fire('Error!', 'Invalid server response.', 'error');
                            return;
                        }

                        if (result.status === 200) {
                            Swal.fire({
                                title: 'Success!',
                                html: `${result.message}.<br><br><a href="admin-dashboard.php" class="btn btn-primary">Go to Dashboard</a>`,
                                icon: 'success',
                                showConfirmButton: false,
                                showCloseButton: true
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

            });

            // Block browser back/forward navigation
            $(window).on('beforeunload', function (e) {
                // Cancel the event
                e.preventDefault();
                // Chrome requires returnValue to be set
                e.returnValue = '';
                return 'Are you sure you want to leave?';
            });

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

</body>

</html>