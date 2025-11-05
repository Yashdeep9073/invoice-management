<?php
session_start();

require "./database/config.php";
if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}

$admin_id = base64_decode($_SESSION["admin_id"]);
$encoded_file_id = $_GET['file'] ?? '';
$file_id = 0;

// Decode the file ID
if (!empty($encoded_file_id)) {
    $decoded = base64_decode($encoded_file_id);
    if (is_numeric($decoded)) {
        $file_id = (int) $decoded;
    }
}

// Check if user has access to this file and get permission level
try {
    $stmt = $db->prepare("
        SELECT ff.*, 
               CASE 
                   WHEN ff.created_by = ? THEN 'edit'
                   ELSE fp.permission 
               END as user_permission
        FROM file_folders ff 
        LEFT JOIN file_permissions fp ON ff.id = fp.file_folder_id AND fp.user_id = ?
        WHERE ff.id = ? AND (ff.created_by = ? OR fp.user_id = ?)
    ");
    $stmt->bind_param("iiiii", $admin_id, $admin_id, $file_id, $admin_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    if (!$file) {
        $_SESSION['error'] = "❌ File not found or you don't have access";
        header("Location: file-manager.php");
        exit;
    }

    $user_permission = $file['user_permission'] ?? 'view';

    // Load spreadsheet data
    $spreadsheetData = [];
    if ($file['file_path'] && file_exists($file['file_path'])) {
        $fileContent = file_get_contents($file['file_path']);
        $spreadsheetData = json_decode($fileContent, true);
    }

} catch (Exception $e) {
    $_SESSION['error'] = "❌ Failed to load file: " . $e->getMessage();
    header("Location: file-manager.php");
    exit;
}

// Handle auto-save with permission check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json');

    // Check if user has edit permission
    if ($user_permission !== 'edit') {
        echo json_encode(['success' => false, 'error' => 'You only have view permission for this file']);
        exit;
    }

    $data = $_POST['data'] ?? '';

    if ($data && $file['file_path']) {
        try {
            // Decode the data to ensure it's valid JSON
            $decodedData = json_decode($data, true);
            if ($decodedData === null) {
                throw new Exception('Invalid JSON data');
            }

            // Save the data
            file_put_contents($file['file_path'], $data);
            echo json_encode(['success' => true, 'message' => 'Saved successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No data received or file path missing']);
    }
    exit;
}

// Handle share action from editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share') {
    $fileId = (int) ($_POST['file_id'] ?? 0);
    $users = $_POST['users'] ?? [];
    $perm = $_POST['permission'] ?? 'view';

    try {
        // Verify ownership - check if current user is the creator
        $stmt = $db->prepare("SELECT id, name, created_by FROM file_folders WHERE id = ?");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();

        if (!$file) {
            $_SESSION['error'] = "❌ File not found (ID: $fileId)";
        } else {
            // Check if current user is the owner
            if ($file['created_by'] == $admin_id) {
                // Get current permissions to compare
                $stmt = $db->prepare("SELECT user_id FROM file_permissions WHERE file_folder_id = ?");
                $stmt->bind_param("i", $fileId);
                $stmt->execute();
                $currentPermissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $currentUsers = array_column($currentPermissions, 'user_id');

                // Convert submitted users to integers
                $submittedUsers = array_map('intval', $users);

                // Users to add (in submitted but not in current)
                $usersToAdd = array_diff($submittedUsers, $currentUsers);

                // Users to remove (in current but not in submitted)
                $usersToRemove = array_diff($currentUsers, $submittedUsers);

                // Remove users that are no longer selected
                if (!empty($usersToRemove)) {
                    $placeholders = implode(',', array_fill(0, count($usersToRemove), '?'));
                    $stmt = $db->prepare("DELETE FROM file_permissions WHERE file_folder_id = ? AND user_id IN ($placeholders)");
                    $types = str_repeat('i', count($usersToRemove) + 1);
                    $params = array_merge([$fileId], array_values($usersToRemove));
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                }

                // Add new users
                $addedCount = 0;
                if (!empty($usersToAdd)) {
                    $ins = $db->prepare("INSERT INTO file_permissions (file_folder_id, user_id, permission, granted_by) VALUES (?, ?, ?, ?)");
                    foreach ($usersToAdd as $userId) {
                        if ($userId > 0 && $userId != $admin_id) {
                            $ins->bind_param("iisi", $fileId, $userId, $perm, $admin_id);
                            if ($ins->execute()) {
                                $addedCount++;
                            }
                        }
                    }
                    $ins->close();
                }

                // Update permission for existing users if changed
                if (!empty($submittedUsers)) {
                    $upd = $db->prepare("UPDATE file_permissions SET permission = ? WHERE file_folder_id = ? AND user_id = ?");
                    foreach ($submittedUsers as $userId) {
                        if ($userId > 0 && $userId != $admin_id) {
                            $upd->bind_param("sii", $perm, $fileId, $userId);
                            $upd->execute();
                        }
                    }
                    $upd->close();
                }

                $totalChanges = count($usersToAdd) + count($usersToRemove);
                if ($totalChanges > 0) {
                    $message = "✅ '" . htmlspecialchars($file['name']) . "' sharing updated successfully";
                    if (count($usersToAdd) > 0) {
                        $message .= " - Added: " . count($usersToAdd);
                    }
                    if (count($usersToRemove) > 0) {
                        $message .= " - Removed: " . count($usersToRemove);
                    }
                    $_SESSION['success'] = $message;
                } else {
                    $_SESSION['success'] = "✅ Sharing permissions updated for '" . htmlspecialchars($file['name']) . "'";
                }
            } else {
                $_SESSION['error'] = "❌ You don't have permission to share '" . htmlspecialchars($file['name']) . "'. Only the owner can share files.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Failed to share: " . $e->getMessage();
        error_log("Share error: " . $e->getMessage());
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?file=" . $encoded_file_id);
    exit;
}

// Get all admins for sharing modal and current shares
$allAdmins = [];
$currentShares = [];
$currentPermission = 'view';
$fileSharedUsers = [];

try {

    $stmtFetchCompanySettings = $db->prepare("SELECT * FROM company_settings");
    $stmtFetchCompanySettings->execute();
    $companySettings = $stmtFetchCompanySettings->get_result()->fetch_array(MYSQLI_ASSOC);

    // Get all admins except current user
    $stmt = $db->prepare("SELECT admin_id, admin_username FROM admin WHERE admin_id != ? ORDER BY admin_username");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $allAdmins = $result->fetch_all(MYSQLI_ASSOC);

    // Get current shares for this file (only if user is owner)
    if ($file['created_by'] == $admin_id) {
        $stmt = $db->prepare("SELECT user_id, permission FROM file_permissions WHERE file_folder_id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentShares = $result->fetch_all(MYSQLI_ASSOC);

        // Get shared user IDs and determine current permission
        $sharedUserIds = [];
        foreach ($currentShares as $share) {
            $sharedUserIds[] = $share['user_id'];
            if ($share['permission'] === 'edit') {
                $currentPermission = 'edit';
            }
        }
        $fileSharedUsers = [
            'users' => $sharedUserIds,
            'permission' => $currentPermission
        ];
    }
} catch (Exception $e) {
    $allAdmins = [];
    $currentShares = [];
    $fileSharedUsers = ['users' => [], 'permission' => 'view'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Edit Spreadsheet - <?= htmlspecialchars($file['name']) ?></title>

    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">

    <!-- Luckysheet CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/css/pluginsCss.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/plugins.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet/dist/css/luckysheet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet/dist/assets/iconfont/iconfont.css" />

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">

    <link rel="stylesheet" href="assets/css/animate.css">

    <link rel="stylesheet" href="assets/css/feather.css">

    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">

    <link rel="stylesheet" href="assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css">

    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        #luckysheet {
            width: 100%;
            height: calc(100vh - 80px);
            margin: 0 auto;
        }

        .editor-header {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            color: #495057;
        }

        .save-status {
            font-size: 12px;
            color: #28a745;
            margin-right: 10px;
        }

        .view-only-badge {
            font-size: 12px;
            padding: 4px 8px;
        }

        /* Select2 Custom Styling */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            min-height: 38px;
            padding: 2px;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff;
            border: 1px solid #007bff;
            color: white;
            border-radius: 0.25rem;
            padding: 2px 8px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #ffcccb;
            background: transparent;
        }

        .select2-container .select2-search--inline .select2-search__field {
            margin-top: 8px;
            border: none !important;
            box-shadow: none !important;
            font-family: inherit;
        }

        .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>

<body>


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
            <div class="content container-fluid p-0">
                <div class="editor-header">
                    <div class="file-info">
                        <h5 class="mb-0 "><?= htmlspecialchars($file['name']) ?></h5>
                        <?php if ($user_permission !== 'edit'): ?>
                            <span class="badge bg-warning view-only-badge">View Only</span>
                        <?php endif; ?>
                    </div>
                    <div class="editor-actions d-flex align-items-center">
                        <a href="spreadsheet" class=" btn btn-dark btn-sm me-2 ">
                            <i class="fas fa-close me-1"></i>
                            Close
                        </a>

                        <span id="saveStatus" class="save-status" style="display: none;">
                            <i class="fas fa-check me-1"></i>Saved
                        </span>

                        <button id="saveBtn" class="btn btn-success btn-sm me-2" <?= $user_permission !== 'edit' ? 'disabled' : '' ?>>
                            <i class="fas fa-save me-1"></i>
                            <?= $user_permission === 'edit' ? 'Save' : 'View Only' ?>
                        </button>
                        <a href="download-excel?file=<?= $encoded_file_id ?>" class="btn btn-danger btn-sm me-2">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <?php if ($file['created_by'] == $admin_id): ?>
                            <button id="shareBtn" class="btn btn-info btn-sm" data-file-id="<?= $file_id ?>"
                                data-shared-users='<?= json_encode($fileSharedUsers['users']) ?>'
                                data-permission="<?= $fileSharedUsers['permission'] ?>">
                                <i class="fas fa-share-alt me-1"></i> Share
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="luckysheet"></div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <?php if ($file['created_by'] == $admin_id): ?>
        <div class="modal fade" id="shareModal">
            <div class="modal-dialog modal-md">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="share">
                        <input type="hidden" name="file_id" id="shareFileId" value="<?= $file_id ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Share Spreadsheet: <?= htmlspecialchars($file['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Users to Share With</label>
                                <select name="users[]" class="form-control select2-multiple" multiple="multiple"
                                    style="width: 100%;" data-placeholder="Choose users..." id="shareUsersSelect">
                                    <?php foreach ($allAdmins as $admin): ?>
                                        <option value="<?= $admin['admin_id'] ?>">
                                            <?= htmlspecialchars($admin['admin_username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> Select multiple users by clicking or using Ctrl/Cmd
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Permission Level</label>
                                <select name="permission" class="form-control" required id="sharePermission">
                                    <option value="view">View Only</option>
                                    <option value="edit">View & Edit</option>
                                </select>

                            </div>

                            <div class="alert alert-info mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> To remove all sharing, deselect all users and click Share.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-share-alt me-1"></i> Update Sharing
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- IMPORTANT: Load jQuery FIRST -->
    <script src="assets/js/jquery-3.7.1.min.js"></script>

    <!-- Load Mousewheel plugin BEFORE Luckysheet -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-mousewheel@3.1.13/jquery.mousewheel.min.js"></script>

    <!-- Then load Luckysheet -->
    <script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/js/plugin.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/luckysheet.umd.js"></script>

    <!-- Then load other scripts -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="assets/js/feather.min.js"></script>
    <script src="assets/js/jquery.slimscroll.min.js"></script>
    <script src="assets/plugins/select2/js/select2.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/custom-select2.js"></script>
    <script src="assets/js/custom.js"></script>

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
            ],
            types: [{
                type: 'error',
                background: '#ff1916',
                textColor: '#FFFFFF',
                dismissible: true
            }],
        });



        // Initialize Select2
        function initSelect2() {
            $('.select2-multiple').select2({
                width: '100%',
                placeholder: 'Choose users...',
                allowClear: true,
                closeOnSelect: false
            });
        }

        $(function () {
            // Check user permission
            const userPermission = '<?= $user_permission ?>';
            const canEdit = userPermission === 'edit';

            // Initialize Luckysheet with the saved data
            const initialData = <?= json_encode($spreadsheetData) ?>;

            const options = {
                container: 'luckysheet',
                lang: 'en',
                showinfobar: false,
                data: initialData.data || [],
                title: "<?= $file['name'] ?>",
                userInfo: '<?= $_SESSION['admin_username'] ?? 'User' ?>',
                myFolderUrl: 'file-manager.php'
            };

            // Disable editing if user only has view permission
            if (!canEdit) {
                options.allowEdit = false;
                options.showToolbar = false;
                options.showSheetBar = false;

                // Style the save button for view-only mode
                $('#saveBtn').removeClass('btn-success').addClass('btn-secondary');
            }

            // Initialize Luckysheet
            luckysheet.create(options);

            // Save functionality - only if user can edit
            function saveSpreadsheet(showAlert = false) {
                if (!canEdit) {
                    notyf.error('You only have view permission for this file');
                    return;
                }

                const allSheetData = luckysheet.getAllSheets();
                const saveData = {
                    name: "<?= $file['name'] ?>",
                    data: allSheetData,
                    config: {},
                    lastModified: new Date().toISOString()
                };

                $.ajax({
                    url: 'editor.php?file=<?= $encoded_file_id ?>',
                    type: 'POST',
                    data: {
                        action: 'save',
                        data: JSON.stringify(saveData)
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#saveStatus').fadeIn().delay(2000).fadeOut();
                            if (showAlert) {
                                notyf.success('Saved successfully!');
                            }
                        } else {
                            notyf.error('Save failed: ' + response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        notyf.error('Save error: ' + error);
                    }
                });
            }

            // Manual save button
            $('#saveBtn').click(function () {
                saveSpreadsheet(true);
            });

            // Share button with Select2 initialization and pre-selecting current shared users
            <?php if ($file['created_by'] == $admin_id): ?>
                $('#shareBtn').click(function () {
                    const sharedUsers = $(this).data('shared-users') || [];
                    const permission = $(this).data('permission') || 'view';

                    // Initialize Select2
                    initSelect2();

                    // Pre-select currently shared users
                    $('#shareUsersSelect').val(sharedUsers).trigger('change');
                    $('#sharePermission').val(permission);

                    $('#shareModal').modal('show');
                });

                // Close Select2 dropdown when modal is hidden
                $('#shareModal').on('hidden.bs.modal', function () {
                    $('.select2-multiple').select2('destroy');
                });
            <?php endif; ?>

            // Only set up auto-save if user can edit
            if (canEdit) {
                // Auto-save on changes (debounced)
                let saveTimeout;
                function scheduleAutoSave() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        saveSpreadsheet(false);
                    }, 3000);
                }

                // Set up auto-save on changes
                luckysheet.bind('cellUpdate', scheduleAutoSave);
                luckysheet.bind('configUpdate', scheduleAutoSave);

                // Keyboard shortcut for save (Ctrl+S)
                $(document).on('keydown', function (e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        saveSpreadsheet(true);
                    }
                });
            } else {
                // Show view-only message
                luckysheet.setSheetAddStatus(false);
            }
        });
    </script>
</body>

</html>