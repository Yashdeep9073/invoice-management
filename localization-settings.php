<?php
ob_start();
session_start();
require './utility/env.php';
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}
require "./database/config.php";


try {
    $stmtFetch = $db->prepare("SELECT auth_banner FROM system_settings");
    $stmtFetch->execute();
    $data = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);
    $imageUrl = $data['auth_banner'];

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);


    $stmtFetchCurrency = $db->prepare("SELECT * FROM currency WHERE is_active = 1");
    $stmtFetchCurrency->execute();
    $currencies = $stmtFetchCurrency->get_result()->fetch_all(MYSQLI_ASSOC);

    $timezones = DateTimeZone::listIdentifiers();

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);

    // echo "<pre>";
    // print_r($localizationSettings);
    // exit;


} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {


        if (isset($_POST['localization_id']) && $_POST['localization_id'] != "") {

            $localizationId = $_POST['localization_id'];
            $language = $_POST['language'];
            $timeZone = $_POST['timezone'];
            $dateFormat = $_POST['date_format'];
            $timeFormat = $_POST['time_format'];
            $currency = $_POST['currency'];

            $stmtInsert = $db->prepare("UPDATE localization_settings SET 
                language = ?,
                timezone = ?,
                date_format = ?,
                time_format = ?,
                currency_id = ?
                WHERE id = ?
                 ");
            $stmtInsert->bind_param(
                "ssssii",
                $language,
                $timeZone,
                $dateFormat,
                $timeFormat,
                $currency,
                $localizationId
            );

            $stmtInsert->execute();

            $_SESSION['success'] = "Currency is Updated successfully";
            header("Location: localization-settings.php");
            exit;

        } else {

            // echo "<pre>";
            // print_r($_POST);
            $language = $_POST['language'];
            $timeZone = $_POST['timezone'];
            $dateFormat = $_POST['date_format'];
            $timeFormat = $_POST['time_format'];
            $currency = $_POST['currency'];

            $stmtInsert = $db->prepare("INSERT INTO localization_settings 
        (language,timezone,date_format,time_format,currency_id) 
        VALUES (?,?,?,?,?)");
            $stmtInsert->bind_param(
                "ssssi",
                $language,
                $timeZone,
                $dateFormat,
                $timeFormat,
                $currency
            );

            $stmtInsert->execute();

            $_SESSION['success'] = "Currency is added successfully";
            header("Location: localization-settings.php");
            exit;
        }

    } catch (\Throwable $th) {
        $_SESSION['error'] = $th->getMessage();
        header("Location: localization-settings.php");
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
    <title>Localization Settings</title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />

    <link rel="stylesheet" href="assets/css/animate.css" />

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css" />

    <link rel="stylesheet" href="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css" />

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
                                                <?php
                                                require("./settings-siderbar.php");
                                                ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-page-wrap">
                                <form action="" method="post" accept-charset="UTF-8">
                                    <input type="hidden" name="localization_id"
                                        value="<?= isset($localizationSettings['id']) ? $localizationSettings['id'] : "" ?>">

                                    <div class="setting-title">
                                        <h4>Localization</h4>
                                    </div>
                                    <div class="company-info company-images">
                                        <div class="card-title-head">
                                            <h6><span><i data-feather="list"></i></span>Basic Information</h6>
                                        </div>
                                        <div class="localization-info">
                                            <!-- Language Selection -->
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Language</h6>
                                                        <p>Select Language of the Website</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <div id="google_translate_element"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Timezone Selection -->
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Timezone</h6>
                                                        <p>Select Timezone for the Website</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <select class="select" name="timezone">
                                                            <?php foreach ($timezones as $timezone) {

                                                                $tz = new DateTimeZone($timezone);
                                                                $now = new DateTime("now", $tz);
                                                                $offset = $tz->getOffset($now);
                                                                $hours = floor($offset / 3600);
                                                                $minutes = floor(abs($offset % 3600) / 60);
                                                                $gmtOffset = sprintf("GMT%+d:%02d", $hours, $minutes);
                                                                ?>
                                                                <option value="<?= htmlspecialchars($timezone) ?>"
                                                                    <?= $localizationSettings['timezone'] == $timezone ? "selected" : "" ?>>
                                                                    <?= htmlspecialchars($timezone) ?> (<?= $gmtOffset ?>)
                                                                </option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Date Format Selection -->
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Date Format</h6>
                                                        <p>Select Date Format to Display</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <select class="select" name="date_format">
                                                            <!-- 24-hour format options -->
                                                            <option value="d M Y H:i" <?php echo $localizationSettings['date_format'] == "d M Y H:i" ? "selected" : "" ?>>22 Jul 2023 14:30</option>
                                                            <option value="M d Y H:i" <?php echo $localizationSettings['date_format'] == "M d Y H:i" ? "selected" : "" ?>>Jul 22 2023 14:30</option>
                                                            <option value="Y-m-d H:i" <?php echo $localizationSettings['date_format'] == "Y-m-d H:i" ? "selected" : "" ?>>2023-07-22 14:30</option>
                                                            <option value="d/m/Y H:i" <?php echo $localizationSettings['date_format'] == "d/m/Y H:i" ? "selected" : "" ?>>22/07/2023 14:30</option>
                                                            <option value="Y年m月d日 H:i" <?php echo $localizationSettings['date_format'] == "Y年m月d日 H:i" ? "selected" : "" ?>>2023年07月22日 14:30</option>

                                                            <!-- 12-hour format options -->
                                                            <option value="d M Y h:i A" <?php echo $localizationSettings['date_format'] == "d M Y h:i A" ? "selected" : "" ?>>22 Jul 2023 02:30 PM</option>
                                                            <option value="M d Y h:i A" <?php echo $localizationSettings['date_format'] == "M d Y h:i A" ? "selected" : "" ?>>Jul 22 2023 02:30 PM</option>
                                                            <option value="Y-m-d h:i A" <?php echo $localizationSettings['date_format'] == "Y-m-d h:i A" ? "selected" : "" ?>>2023-07-22 02:30 PM</option>
                                                            <option value="d/m/Y h:i A" <?php echo $localizationSettings['date_format'] == "d/m/Y h:i A" ? "selected" : "" ?>>22/07/2023 02:30 PM</option>
                                                            <option value="Y年m月d日 h:i A" <?php echo $localizationSettings['date_format'] == "Y年m月d日 h:i A" ? "selected" : "" ?>>2023年07月22日 02:30 PM</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Time Format Selection -->
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Time Format</h6>
                                                        <p>Select Time Format to Display</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <select class="select" name="time_format">
                                                            <option value="12" <?php echo $localizationSettings['time_format'] == "12" ? "selected" : "" ?>>12 Hours (e.g., 2:30 PM)</option>
                                                            <option value="24" <?php echo $localizationSettings['time_format'] == "24" ? "selected" : "" ?>>24 Hours (e.g., 14:30)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="company-info company-images">
                                        <div class="card-title-head">
                                            <h6><span><i data-feather="credit-card"></i></span>Currency Settings</h6>
                                        </div>
                                        <div class="localization-info">
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <div class="setting-info">
                                                        <h6>Currency</h6>
                                                        <p>Select Currency</p>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="localization-select">
                                                        <select class="select" name="currency">
                                                            <?php
                                                            foreach ($currencies as $currency) { ?>
                                                                <option value='<?= $currency['currency_id'] ?>' <?php echo $localizationSettings['currency_id'] == $currency['currency_id'] ? "selected" : "" ?>>
                                                                    <?= $currency['currency_name'] . " (" . $currency['currency_symbol'] . ")" ?>
                                                                </option>";
                                                            <?php } ?>
                                                        </select>
                                                    </div>
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

    <script src="assets/js/feather.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/js/jquery.slimscroll.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/js/jquery.dataTables.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/js/bootstrap.bundle.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/js/moment.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/js/bootstrap-datetimepicker.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js"
        type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/plugins/select2/js/select2.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/plugins/theia-sticky-sidebar/ResizeSensor.js"
        type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js"
        type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>

    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"
        type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/js/script.js" type="dbd761c2ff900fd50c30f6a7-text/javascript"></script>
    <script src="assets/js/rocket-loader-min.js" data-cf-settings="dbd761c2ff900fd50c30f6a7-|49" defer=""></script>

    <!-- <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,fr,es,de,it,hi,ja', // Add more languages if required
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            document.body.appendChild(script);
        });
    </script> -->

</body>

</html>