<?php
$adminId = base64_decode($_SESSION['admin_id']) ?? null; // Use null coalescing operator for safety

try {
    $roleId = null; // Initialize role ID


    if (isset($adminId)) {
        // Fetch admin role
        $stmtAdmin = $db->prepare("SELECT admin_role FROM admin WHERE admin_id = ?");
        $stmtAdmin->bind_param('i', $adminId);
        $stmtAdmin->execute();
        $adminResponse = $stmtAdmin->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($adminResponse) {
            $roleId = $adminResponse['0']['admin_role']; // Set role ID for admin
        } else {
            $_SESSION['error'] = "Admin not found for ID: " . $adminId;
            header('Location: index.php');
            exit();
        }
    } else {
        // No session ID found, redirect to login
        $_SESSION['error'] = "You must be logged in to access this page.";
        header('Location: index.php');
        exit();
    }


    // Fetch role details
    $stmtRolesData = $db->prepare("SELECT * FROM roles WHERE role_id = ?");
    $stmtRolesData->bind_param('i', $roleId);
    $stmtRolesData->execute();
    $roleData = $stmtRolesData->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$roleData) {
        $_SESSION['error'] = "Role not found for ID: " . $roleId;
        header('Location: index.php');
        exit();
    }

    // Fetch permissions for the role
    $stmtPrivileges = $db->prepare("
        SELECT p.permission_name 
        FROM permissions p
        JOIN role_permissions rp ON p.permission_id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmtPrivileges->bind_param('i', $roleId);
    $stmtPrivileges->execute();
    $privileges = $stmtPrivileges->get_result()->fetch_all(MYSQLI_ASSOC);
    $privileges = array_column($privileges, 'permission_name');

} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: index.php');
    exit();
}

// Function to check if admin has a specific permission
function hasPermission($privilege, $privileges, $roleName)
{
    // If role is admin, grant all permissions
    if (strtolower($roleName) === 'admin') {
        return true;
    }
    // For other roles, check specific permissions
    return in_array($privilege, $privileges);
}

// Check if user is admin
$isAdmin = strtolower($roleData['0']['role_name']) === 'admin';
?>


<div class="sidebar-inner slimscroll">
    <div id="sidebar-menu" class="sidebar-menu">
        <ul>

            <?php if ($isAdmin || hasPermission('Dashboard', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Dashboard</h6>
                    <ul>
                        <li>
                            <a href="<?= getenv("BASE_URL") . "dashboard" ?>"><i
                                    data-feather="grid"></i><span>Dashboard</span></a>
                        </li>
                        <!-- <li class="submenu">
                            <?php if ($isAdmin || hasPermission('Application', $privileges, $roleData['0']['role_name'])): ?>
                                <a href="javascript:void(0);" class=""><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" class="feather feather-smartphone">
                                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                                        <line x1="12" y1="18" x2="12.01" y2="18"></line>
                                    </svg><span>Digital Sheet</span><span class="menu-arrow"></span></a>
                            <?php endif; ?>
                            <ul style="display: none;">
                                <?php if ($isAdmin || hasPermission('Spread Sheet', $privileges, $roleData['0']['role_name'])): ?>
                                    <li><a href="spreadsheet">Spread Sheet</a></li>
                                <?php endif; ?>
                            </ul>
                        </li> -->
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || hasPermission('Invoice', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Invoice</h6>
                    <ul>

                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="file-text"></i><span>Invoice</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <?php if ($isAdmin || hasPermission('Manage Invoice', $privileges, $roleData['0']['role_name'])): ?>
                                    <li><a href="<?= getenv("BASE_URL") . "manage-invoice" ?>">Manage Invoice</a></li>
                                <?php endif; ?>

                                <?php if ($isAdmin || hasPermission('Recycle Bin', $privileges, $roleData['0']['role_name'])): ?>
                                    <li>
                                        <a href="<?= getenv("BASE_URL") . "recycle-bin" ?>">Recycle-bin</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>

                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || hasPermission('GST', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">GST</h6>
                    <ul>

                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="file-text"></i><span>GST Slab</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <?php if ($isAdmin || hasPermission('Manage GST', $privileges, $roleData['0']['role_name'])): ?>
                                    <li><a href="<?= getenv("BASE_URL") . "manage-gst" ?>">Manage GST</a></li>
                                <?php endif; ?>

                            </ul>
                        </li>

                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || hasPermission('Payments', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Payments</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="credit-card"></i><span>Payments</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="<?= getenv("BASE_URL") . "all-payments" ?>">All Payments</a></li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "paid-payments" ?>">Paid Payments</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "pending-payments" ?>">Pending Payments</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "cancelled-payments" ?>">Cancelled Payments</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "refunded-payments" ?>">Refunded Payments</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || hasPermission('Utility', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Utility</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="tool"></i><span>Utility</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "customer-details" ?>">Customers</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "services" ?>">Services</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "tax-details" ?>">Tax</a>
                                </li>
                            </ul>
                        </li>

                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($isAdmin || hasPermission('Reports', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Reports</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="file-text"></i><span>Reports</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="<?= getenv("BASE_URL") . "reports" ?>">Invoice Report</a></li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "customer-reports" ?>">Customer Report</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || hasPermission('User Management', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">User Management</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="user-check"></i><span>User Management</span><span
                                    class="menu-arrow"></span></a>
                            <ul>

                                <li>
                                    <a href="<?= getenv("BASE_URL") . "users" ?>">Users</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "permissions" ?>">Permission</a>
                                </li>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "roles" ?>">Role</a>
                                </li>
                            </ul>
                        </li>


                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($isAdmin || hasPermission('Settings', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Settings</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="settings"></i><span>Settings</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "system-settings" ?>">System Settings</a>
                                </li>
                            </ul>
                        </li>


                    </ul>
                </li>
            <?php endif; ?>


            <?php if ($isAdmin || hasPermission('Logs', $privileges, $roleData['0']['role_name'])): ?>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Logs</h6>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);"><i data-feather="book"></i><span>Logs</span><span
                                    class="menu-arrow"></span></a>
                            <ul>
                                <li>
                                    <a href="<?= getenv("BASE_URL") . "logs" ?>">Manage Logs</a>
                                </li>
                            </ul>
                        </li>


                    </ul>
                </li>
            <?php endif; ?>

            <li class="submenu-open">
                <ul>

                    <li>
                        <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span>
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);"><i data-feather="lock"></i><span> v1.1.7</span></a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</div>