<?php

ob_start();
session_start();
error_reporting(0);
require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';



$siteKey = getenv('GOOGLE_RECAPTCHA_SITE_KEY');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate user input
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $passwordInput = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $recaptcha = filter_input(INPUT_POST, 'g-recaptcha-response', FILTER_SANITIZE_STRING);

    // Check if reCAPTCHA is active in the database
    if (isset($data['is_recaptcha_active']) && $data['is_recaptcha_active'] == 1) {
        // If reCAPTCHA is active, g-recaptcha-response must be present
        if (empty($recaptcha)) {
            $_SESSION['error'] = "Please complete the reCAPTCHA verification.";
            header("Location: index.php");
            exit;
        }

        // Google reCAPTCHA verification
        $secret_key = getenv('GOOGLE_RECAPTCHA_SECRET_KEY');
        if (!$secret_key) {
            $_SESSION['error'] = "reCAPTCHA configuration error. Please contact the administrator.";
            header("Location: index.php");
            exit;
        }

        // Use cURL for more robust HTTP request handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret_key,
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
    $stmt = $db->prepare("SELECT * FROM admin WHERE admin_email = ?");
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

    // Store session values securely
    $_SESSION['admin_id'] = base64_encode($row['admin_id']);
    $_SESSION['admin_name'] = $row['admin_username'];

    $roleId = $row['admin_role'];
    // Fetch role details
    $stmtRolesData = $db->prepare("SELECT * FROM roles WHERE role_id = ?");
    $stmtRolesData->bind_param('i', $roleId);
    $stmtRolesData->execute();
    $roleData = $stmtRolesData->get_result()->fetch_all(MYSQLI_ASSOC);

    $_SESSION['admin_role'] = $roleData[0]['role_name'];

    // Redirect to admin dashboard
    header("Location: admin-dashboard.php");
    exit;
}

try {
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
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

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
                                            <div class="custom-control custom-checkbox">
                                                <label class="checkboxs ps-4 mb-0 pb-0 line-height-1">
                                                    <input type="checkbox" class="form-control">
                                                    <span class="checkmarks"></span> Remember me
                                                </label>
                                            </div>
                                            <div class="text-end">
                                                <a class="forgot-link" href="forgot-password.php">Forgot Password?</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($data['is_recaptcha_active'] == 1): ?>
                                    <div class="form-login">
                                        <div class="g-recaptcha"
                                            data-sitekey="<?= htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') ?>"
                                            data-callback="enableSubmit" style="border:none;" align="center"></div>
                                    </div>
                                <?php endif; ?>

                                <div class="form-login">
                                    <button type="submit" name="submit" id="submit" class="btn btn-login" <?php
                                    echo isset($data['is_recaptcha_active']) && $data['is_recaptcha_active'] == 1 ? 'disabled' : ''; ?>>
                                        Sign In
                                    </button>
                                </div>

                                <div class="form-sociallink">
                                    <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                                        <p>Copyright &copy; <?php echo date('Y'); ?> <a href="https://vibrantick.in/"
                                                target="_blank">Vibrantick Infotech
                                                Solutions Pvt Ltd. All rights reserved</a></p>
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
    function enableSubmit(token) {
        document.getElementById('submit').removeAttribute('disabled');
    }

    // Prevent form submission if reCAPTCHA is active but not completed
    document.querySelector('form').addEventListener('submit', function (e) {
        <?php if (isset($data['is_recaptcha_active']) && $data['is_recaptcha_active'] == 1): ?>
            if (!grecaptcha.getResponse()) {
                e.preventDefault();
                alert('Please complete the reCAPTCHA verification.');
            }
        <?php endif; ?>
    });
</script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>

</html>