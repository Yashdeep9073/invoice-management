<div class="header">
    <div class="header-left active">
        <a href="<?= getenv("BASE_URL") . "dashboard" ?>" class="logo logo-normal">
            <img src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo/vis-logo.png" ?>"
                alt="" />
        </a>
        <a href="admin-dashboard.php" class="logo logo-white">
            <img src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo/vis-logo.png" ?>"
                alt="" />
        </a>
        <a href="admin-dashboard.php" class="logo-small">
            <img src="<?= isset($companySettings['company_logo']) ? $companySettings['company_logo'] : "assets/img/logo/vis-logo.png" ?>"
                alt="" />
        </a>
        <a id="toggle_btn" href="javascript:void(0);">
            <i data-feather="chevrons-left" class="feather-16"></i>
        </a>
    </div>

    <a id="mobile_btn" class="mobile_btn" href="#sidebar">
        <span class="bar-icon">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </a>

    <ul class="nav user-menu">
        <li class="nav-item nav-searchinputs">
            <div class="top-nav-search">
                <a href="javascript:void(0);" class="responsive-search">
                    <i class="fa fa-search"></i>
                </a>
            </div>
        </li>

        <li class="nav-item nav-item-box">
            <a href="javascript:void(0);" id="btnFullscreen">
                <i data-feather="maximize"></i>
            </a>
        </li>

        <li class="nav-item dropdown has-arrow main-drop">
            <a href="javascript:void(0);" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown">
                <span class="user-info">
                    <span class="user-detail">
                        <span
                            class="user-name"><?php echo isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : "Vibrantick" ?></span>
                        <span
                            class="user-role"><?php echo isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : "Admin" ?></span>
                    </span>
                </span>
            </a>
            <div class="dropdown-menu menu-drop-user">
                <div class="profilename">
                    <hr class="m-0" />
                    <a class="dropdown-item logout pb-0" href="<?= getenv("BASE_URL") . "logout" ?>"><img
                            src="assets/img/icons/log-out.svg" class="me-2" alt="img" />Logout</a>
                </div>
            </div>
        </li>
    </ul>

    <div class="dropdown mobile-user-menu">
        <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
            aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
        <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="<?= getenv("BASE_URL") . "logout" ?>">Logout</a>
        </div>
    </div>
</div>