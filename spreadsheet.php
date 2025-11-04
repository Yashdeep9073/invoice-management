<?php
session_start();
require "./database/config.php";
require "./utility/encodeDecode.php";
$admin_id = base64_decode($_SESSION["admin_id"]);

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . getenv("BASE_URL"));
    exit();
}

$uploadDir = getenv("BASE_URL");


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD FOLDER
    if ($action === 'add_folder') {
        $name = trim($_POST['name'] ?? '');
        $parent = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        if ($name) {
            try {
                $stmt = $db->prepare("INSERT INTO file_folders (name, type, parent_id, created_by) VALUES (?, ?, ?, ?)");
                $type = 'folder';
                $stmt->bind_param("ssii", $name, $type, $parent, $admin_id);
                $stmt->execute();
                $_SESSION['success'] = "Folder created successfully";
            } catch (Exception $e) {
                $_SESSION['error'] = "Failed to create folder: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Folder name is required";
        }
        header("Location: " . getenv("BASE_URL") . "spreadsheet");
        exit;
    }

    // ADD FILE
    if ($action === 'add_file') {
        $name = trim($_POST['name'] ?? '');
        $parent = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        if ($name) {
            try {
                $luckysheetData = [
                    "name" => $name,
                    "config" => [],
                    "data" => [
                        [
                            "name" => "Sheet1",
                            "color" => "",
                            "status" => "1",
                            "order" => "0",
                            "row" => 84,
                            "column" => 60,
                            "config" => [],
                            "index" => 0,
                            "chart" => [],
                            "celldata" => []
                        ]
                    ]
                ];

                $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) . '_' . time() . '.json';
                $filepath = "public/upload/spreadsheets/" . $filename;
                if (!is_dir('public/upload/spreadsheets'))
                    mkdir('public/upload/spreadsheets', 0755, true);

                file_put_contents($filepath, json_encode($luckysheetData));

                $stmt = $db->prepare("INSERT INTO file_folders (name, type, parent_id, file_path, created_by) VALUES (?, ?, ?, ?, ?)");
                $type = 'file';
                $stmt->bind_param("ssisi", $name, $type, $parent, $filepath, $admin_id);
                $stmt->execute();

                $_SESSION['success'] = "Spreadsheet created successfully";
            } catch (Exception $e) {
                $_SESSION['error'] = "Failed to create spreadsheet: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "File name is required";
        }
        header("Location: " . getenv("BASE_URL") . "spreadsheet");
        exit;
    }

    // SHARE
    if ($action === 'share') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $users = $_POST['users'] ?? [];
        $perm = $_POST['permission'] ?? 'VIEW';

        try {

            $stmt = $db->prepare("SELECT id, name, type, created_by FROM file_folders WHERE id = ?");
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();

            if (!$file) {
                $_SESSION['error'] = "Item not found";
            } else if ($file['created_by'] != $admin_id) {
                $_SESSION['error'] = "You can only share your own items.";
            } else {
                $stmt = $db->prepare("SELECT user_id FROM file_permissions WHERE file_folder_id = ?");
                $stmt->bind_param("i", $fileId);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $currentUsers = array_column($current, 'user_id');
                $submittedUsers = array_map('intval', $users);

                $toAdd = array_diff($submittedUsers, $currentUsers);
                $toRemove = array_diff($currentUsers, $submittedUsers);

                if (!empty($toRemove)) {
                    $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
                    $stmt = $db->prepare("DELETE FROM file_permissions WHERE file_folder_id = ? AND user_id IN ($placeholders)");
                    $params = array_merge([$fileId], $toRemove);
                    $types = str_repeat('i', count($params));
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                }

                if (!empty($toAdd)) {
                    $ins = $db->prepare("INSERT INTO file_permissions (file_folder_id, user_id, permission, granted_by) VALUES (?, ?, ?, ?)");
                    foreach ($toAdd as $uid) {
                        if ($uid > 0 && $uid != $admin_id) {
                            $ins->bind_param("iisi", $fileId, $uid, $perm, $admin_id);
                            $ins->execute();
                        }
                    }
                }

                if (!empty($submittedUsers)) {
                    $upd = $db->prepare("UPDATE file_permissions SET permission = ? WHERE file_folder_id = ? AND user_id = ?");
                    foreach ($submittedUsers as $uid) {
                        if ($uid > 0 && $uid != $admin_id) {
                            $upd->bind_param("sii", $perm, $fileId, $uid);
                            $upd->execute();
                        }
                    }
                }

                if ($file['type'] === 'folder' && (!empty($toAdd) || !empty($toRemove))) {
                    shareFolderContents($fileId, $toAdd, $toRemove, $perm, $admin_id, $db);
                }

                $changes = count($toAdd) + count($toRemove);
                $_SESSION['success'] = $changes > 0
                    ? "Sharing updated for '{$file['name']}'"
                    : "No changes made to sharing.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Share failed: " . $e->getMessage();
        }
        header("Location: " . getenv("BASE_URL") . "spreadsheet");
        exit;
    }

    // DELETE
    if ($action === 'delete') {
        $itemId = (int) ($_POST['item_id'] ?? 0);

        try {
            // Fetch item info and check if it's shared or owned by current admin
            $stmt = $db->prepare("
            SELECT ff.*, COUNT(fp.id) as shared_count
            FROM file_folders ff
            LEFT JOIN file_permissions fp ON ff.id = fp.file_folder_id
            WHERE ff.id = ?
            GROUP BY ff.id
        ");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Item not found."
                ]);
                exit;
            } else if ($item['created_by'] != $admin_id) {

                echo json_encode([
                    "status" => 400,
                    "error" => "You can only delete your own items."
                ]);
                exit;

            } else if ($item['shared_count'] > 0) {
                echo json_encode([
                    "status" => 400,
                    "error" => "Cannot delete shared item. Unshare first."
                ]);
                exit;
            } else {
                // Delete all related files if it's a folder
                if ($item['type'] === 'folder') {
                    // Fetch all files in this folder
                    $stmtFiles = $db->prepare("SELECT id, file_path FROM file_folders WHERE parent_id = ?");
                    $stmtFiles->bind_param("i", $itemId);
                    $stmtFiles->execute();
                    $files = $stmtFiles->get_result();

                    while ($file = $files->fetch_assoc()) {
                        if ($file['file_path'] && file_exists($file['file_path'])) {
                            unlink($file['file_path']); // delete physical file
                        }

                        // delete file record from database
                        $delFileStmt = $db->prepare("DELETE FROM file_folders WHERE id = ?");
                        $delFileStmt->bind_param("i", $file['id']);
                        $delFileStmt->execute();
                    }

                    // Optionally, remove the folder directory itself (if stored on disk)
                    if ($item['file_path'] && is_dir($item['file_path'])) {
                        rmdir($item['file_path']); // remove empty directory
                    }
                } else {
                    // If it's a single file, remove it from filesystem
                    if ($item['file_path'] && file_exists($item['file_path'])) {
                        unlink($item['file_path']);
                    }
                }

                // Delete the folder/file record itself
                $stmt = $db->prepare("DELETE FROM file_folders WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();


                echo json_encode([
                    "status" => 200,
                    "message" => ucfirst($item['type']) . " '{$item['name']}' deleted successfully."
                ]);
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Delete failed: " . $e->getMessage();

            echo json_encode([
                "status" => 500,
                "error" => "Delete failed: " . $e->getMessage()
            ]);
            exit;
        }
    }

}

function shareFolderContents($folderId, $add, $remove, $perm, $adminId, $db)
{
    $stmt = $db->prepare("SELECT id, type FROM file_folders WHERE parent_id = ?");
    $stmt->bind_param("i", $folderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        if (!empty($remove)) {
            $placeholders = implode(',', array_fill(0, count($remove), '?'));
            $del = $db->prepare("DELETE FROM file_permissions WHERE file_folder_id = ? AND user_id IN ($placeholders)");
            $params = array_merge([$item['id']], $remove);
            $types = str_repeat('i', count($params));
            $del->bind_param($types, ...$params);
            $del->execute();
        }
        if (!empty($add)) {
            $ins = $db->prepare("INSERT INTO file_permissions (file_folder_id, user_id, permission, granted_by) VALUES (?, ?, ?, ?)");
            foreach ($add as $uid) {
                if ($uid > 0 && $uid != $adminId) {
                    $ins->bind_param("iisi", $item['id'], $uid, $perm, $adminId);
                    $ins->execute();
                }
            }
        }
        if ($item['type'] === 'folder') {
            shareFolderContents($item['id'], $add, $remove, $perm, $adminId, $db);
        }
    }
}

// Fetch My Items + Shared Users
$myTree = [];
$fileSharedUsers = [];
try {
    $stmt = $db->prepare("
        SELECT ff.id, 
               ff.name,
               ff.type,
               ff.parent_id,
               ff.file_path,
               ff.created_by,
               ff.created_at,
               ff.updated_at,
               ff.related_to,
               GROUP_CONCAT(DISTINCT fp.user_id) AS shared_with,
               GROUP_CONCAT(DISTINCT fp.permission) AS shared_perm,
               COUNT(DISTINCT fp.id) as shared_count
        FROM file_folders ff
        LEFT JOIN file_permissions fp ON ff.id = fp.file_folder_id
        WHERE ff.created_by = ?
        GROUP BY ff.id, ff.name, ff.type, ff.parent_id, ff.file_path, ff.created_by, ff.created_at, ff.updated_at, ff.related_to
        ORDER BY ff.name
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $myItems = $result->fetch_all(MYSQLI_ASSOC);

    $itemMap = [];
    foreach ($myItems as $item) {
        $itemMap[$item['id']] = $item;
        $itemMap[$item['id']]['children'] = [];

        $sharedUsers = !empty($item['shared_with']) ? explode(',', $item['shared_with']) : [];
        $perm = !empty($item['shared_perm']) ? explode(',', $item['shared_perm'])[0] : 'VIEW';
        $fileSharedUsers[$item['id']] = [
            'users' => $sharedUsers,
            'permission' => $perm,
            'count' => $item['shared_count']
        ];
    }

    foreach ($myItems as $item) {
        if (!$item['parent_id']) {
            $myTree[] = &$itemMap[$item['id']];
        } else if (isset($itemMap[$item['parent_id']])) {
            $itemMap[$item['parent_id']]['children'][] = &$itemMap[$item['id']];
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Load error: " . $e->getMessage();
}

// Shared With Me
$sharedItems = [];
try {
    $stmt = $db->prepare("
        SELECT ff.*, a.admin_username AS owner_name, fp.permission
        FROM file_folders ff
        JOIN file_permissions fp ON ff.id = fp.file_folder_id
        JOIN admin a ON ff.created_by = a.admin_id
        WHERE fp.user_id = ? AND ff.created_by != ?
    ");
    $stmt->bind_param("ii", $admin_id, $admin_id);
    $stmt->execute();
    $sharedItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
}

// Build shared items tree structure
$sharedTree = [];
if (!empty($sharedItems)) {
    $sharedItemMap = [];

    // First, get all shared folders to build the hierarchy
    $sharedFolderIds = [];
    foreach ($sharedItems as $item) {
        if ($item['type'] === 'folder') {
            $sharedFolderIds[] = $item['id'];
        }
    }

    if (!empty($sharedFolderIds)) {
        $placeholders = implode(',', array_fill(0, count($sharedFolderIds), '?'));
        $stmt = $db->prepare("
            SELECT ff.*, a.admin_username AS owner_name, fp.permission
            FROM file_folders ff
            JOIN file_permissions fp ON ff.id = fp.file_folder_id
            JOIN admin a ON ff.created_by = a.admin_id
            WHERE ff.parent_id IN ($placeholders) AND fp.user_id = ? AND ff.created_by != ?
        ");
        $params = array_merge($sharedFolderIds, [$admin_id, $admin_id]);
        $types = str_repeat('i', count($sharedFolderIds)) . 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $childItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sharedItems = array_merge($sharedItems, $childItems);
    }

    // Build the tree structure
    foreach ($sharedItems as $item) {
        $sharedItemMap[$item['id']] = $item;
        $sharedItemMap[$item['id']]['children'] = [];
    }

    foreach ($sharedItems as $item) {
        if (!$item['parent_id']) {
            $sharedTree[] = &$sharedItemMap[$item['id']];
        } else if (isset($sharedItemMap[$item['parent_id']])) {
            $sharedItemMap[$item['parent_id']]['children'][] = &$sharedItemMap[$item['id']];
        }
    }
}

// All Admins
$allAdmins = [];
try {
    $stmt = $db->prepare("SELECT admin_id, admin_username FROM admin WHERE admin_id != ? ORDER BY admin_username");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $allAdmins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {

}

// Updated render functions with encoded IDs
function renderMyTree($items, $level = 0)
{
    global $fileSharedUsers;
    foreach ($items as $item):
        $shared = $fileSharedUsers[$item['id']] ?? ['users' => [], 'permission' => 'VIEW', 'count' => 0];
        $hasChildren = !empty($item['children']);
        $isShared = $shared['count'] > 0;
        $encodedId = base64_encode($item['id']);
        ?>
        <?php if ($item['type'] === 'FOLDER'): ?>
            <li class="fm-folder-item">
                <div class="fm-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <?php if ($hasChildren): ?>
                            <span class="fm-folder-toggle collapsed" title="Toggle folder">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        <?php else: ?>
                            <span class="fm-folder-toggle" style="visibility: hidden;">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        <?php endif; ?>
                        <div class="fm-item-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="fm-item-content">
                            <div class="fm-item-name">
                                <?= htmlspecialchars($item['name']) ?>
                                <?php if ($isShared): ?>
                                    <span class="badge bg-success shared-badge"><?= $shared['count'] ?> shared</span>
                                <?php endif; ?>
                            </div>
                            <div class="fm-item-meta">
                                Folder
                            </div>
                        </div>
                    </div>
                    <div class="fm-item-actions">
                        <button data-bs-toggle="modal" data-bs-target="#modalFile" class="btn btn-sm btn-outline-primary add-in-btn"
                            data-id="<?= $item['id'] ?>" title="Add File">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary share-btn" data-id="<?= $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>" data-type="folder"
                            data-shared-users='<?= json_encode($shared['users']) ?>' data-permission="<?= $shared['permission'] ?>">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger deleteButton" data-id="<?= $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>" data-type="folder"
                            data-shared="<?= $isShared ? '1' : '0' ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php if ($hasChildren): ?>
                    <ul class="fm-folder-children" style="display: none;">
                        <?php renderMyTree($item['children'], $level + 1); ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php else: ?>
            <li class="fm-file-item">
                <div class="fm-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <span class="fm-folder-toggle" style="visibility: hidden;">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="fm-item-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="fm-item-content">
                            <div class="fm-item-name">
                                <a href="editor?file=<?= $encodedId ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                                <?php if ($isShared): ?>
                                    <span class="badge bg-success shared-badge"><?= $shared['count'] ?> shared</span>
                                <?php endif; ?>
                            </div>
                            <div class="fm-item-meta">
                                Spreadsheet File
                            </div>
                        </div>
                    </div>
                    <div class="fm-item-actions">
                        <button class="btn btn-sm btn-outline-primary share-btn" data-id="<?= $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>" data-type="file"
                            data-shared-users='<?= json_encode($shared['users']) ?>' data-permission="<?= $shared['permission'] ?>">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <a href="editor?file=<?= $encodedId ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="download-excel?file=<?= $encodedId ?>" class="btn btn-sm btn-outline-info" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger deleteButton" data-id="<?= $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>" data-type="file"
                            data-shared="<?= $isShared ? '1' : '0' ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </li>
        <?php endif; ?>
    <?php endforeach;
}

function renderSharedTree($items, $level = 0)
{
    foreach ($items as $item):
        $hasChildren = !empty($item['children']);
        $encodedId = base64_encode($item['id']);
        ?>
        <?php if ($item['type'] === 'folder'): ?>
            <li class="fm-folder-item">
                <div class="fm-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <?php if ($hasChildren): ?>
                            <span class="fm-folder-toggle collapsed" title="Toggle folder">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        <?php else: ?>
                            <span class="fm-folder-toggle" style="visibility: hidden;">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        <?php endif; ?>
                        <div class="fm-item-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="fm-item-content">
                            <div class="fm-item-name">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>
                            <div class="fm-item-meta">
                                Shared by <?= htmlspecialchars($item['owner_name']) ?>
                                <span class="badge bg-<?= $item['permission'] === 'edit' ? 'success' : 'info' ?> ms-2">
                                    <?= $item['permission'] === 'edit' ? 'Can Edit' : 'View Only' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($hasChildren): ?>
                    <ul class="fm-folder-children" style="display: none;">
                        <?php renderSharedTree($item['children'], $level + 1); ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php else: ?>
            <li class="fm-file-item">
                <div class="fm-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <span class="fm-folder-toggle" style="visibility: hidden;">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                        <div class="fm-item-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="fm-item-content">
                            <div class="fm-item-name">
                                <a href="editor?file=<?= $encodedId ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            </div>
                            <div class="fm-item-meta">
                                Shared by <?= htmlspecialchars($item['owner_name']) ?>
                                <span class="badge bg-<?= $item['permission'] === 'edit' ? 'success' : 'info' ?> ms-2">
                                    <?= $item['permission'] === 'edit' ? 'Can Edit' : 'View Only' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="fm-item-actions">
                        <a href="editor?file=<?= $encodedId ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="download-excel?file=<?= $encodedId ?>" class="btn btn-sm btn-outline-dark" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
            </li>
        <?php endif; ?>
    <?php endforeach;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spreadsheet File Manager</title>
    <link rel="shortcut icon" type="image/x-icon"
        href="<?= isset($companySettings['favicon']) ? $companySettings['favicon'] : "assets/img/fav/vis-favicon.png" ?>">


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

    <!-- toast  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <style>
        :root {
            --primary-color: #086AD8;
            --primary-light: #e8f1fd;
            --border-color: #e0e0e0;
            --hover-bg: #f8f9fa;
        }

        .file-manager-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .fm-header {
            background-color: var(--primary-light);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
        }

        .fm-tabs {
            border-bottom: 1px solid var(--border-color);
        }

        .fm-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
            border: none;
            border-bottom: 2px solid transparent;
        }

        .fm-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: transparent;
        }

        .fm-content {
            padding: 20px;
        }

        .fm-actions {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background: #f8f9fa;
        }

        .fm-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .fm-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }

        .fm-item:hover {
            background-color: var(--hover-bg);
        }

        .fm-item:last-child {
            border-bottom: none;
        }

        .fm-item-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin-right: 12px;
            color: var(--primary-color);
            background-color: var(--primary-light);
        }

        .fm-item-content {
            flex: 1;
        }

        .fm-item-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }

        .fm-item-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .fm-item-actions {
            display: flex;
            gap: 5px;
        }

        .fm-item-actions .btn {
            padding: 5px 8px;
            font-size: 12px;
        }

        .fm-folder-toggle {
            cursor: pointer;
            margin-right: 8px;
            color: #6c757d;
            width: 20px;
            text-align: center;
            transition: transform 0.2s;
        }

        .fm-folder-toggle.collapsed i {
            transform: rotate(-90deg);
        }

        .fm-folder-children {
            margin-left: 30px;
            border-left: 1px dashed var(--border-color);
            padding-left: 15px;
        }

        .fm-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .fm-empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #0759c0;
            border-color: #0759c0;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .badge.bg-primary {
            background-color: var(--primary-color) !important;
        }

        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        /* Shared item styling */
        .shared-badge {
            font-size: 11px;
            margin-left: 8px;
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
            <div class="content">
                <div class="page-header">
                    <div class="add-item d-flex justify-content-between">
                        <div class="page-title">
                            <h4>File Manager</h4>
                            <h6>Manage files & folders</h6>
                        </div>
                    </div>
                </div>

                <div class="file-manager-container">
                    <div class="fm-header">
                        <h5 class="mb-0">Spreadsheet Files</h5>
                    </div>

                    <ul class="nav fm-tabs" id="folderTab">
                        <?php if ($isAdmin || hasPermission('My Folder', $privileges, $roleData['0']['role_name'])): ?>
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#my-folder">My Folder</a>
                            </li>
                        <?php endif; ?>

                        <?php if ($isAdmin || hasPermission('Shared With Me', $privileges, $roleData['0']['role_name'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#shared-folder">Shared With Me</a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="tab-content">

                        <?php if ($isAdmin || hasPermission('My Folder', $privileges, $roleData['0']['role_name'])): ?>
                            <!-- MY FOLDER -->
                            <div class="tab-pane fade show active" id="my-folder">
                                <div class="fm-actions d-flex gap-2">
                                    <?php if ($isAdmin || hasPermission('Create Spread Sheet', $privileges, $roleData['0']['role_name'])): ?>

                                        <button data-bs-toggle="modal" data-bs-target="#modalFile"
                                            class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>
                                            New File</button>

                                    <?php endif; ?>
                                    <?php if ($isAdmin || hasPermission('Create Spread Sheet Folder', $privileges, $roleData['0']['role_name'])): ?>

                                        <button data-bs-toggle="modal" data-bs-target="#modalFolder"
                                            class="btn btn-primary btn-sm"><i class="fas fa-folder-plus me-1"></i> New
                                            Folder</button>
                                    <?php endif; ?>
                                </div>
                                <div class="fm-content">
                                    <?php if (empty($myTree)): ?>
                                        <div class="fm-empty-state">
                                            <i class="fas fa-folder-open"></i>
                                            <p>No items yet. Create your first file or folder!</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="fm-list">
                                            <?php renderMyTree($myTree); ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isAdmin || hasPermission('Shared With Me', $privileges, $roleData['0']['role_name'])): ?>
                            <!-- SHARED WITH ME -->
                            <div class="tab-pane fade" id="shared-folder">
                                <div class="fm-content">
                                    <?php if (empty($sharedTree)): ?>
                                        <div class="fm-empty-state">
                                            <i class="fas fa-share-alt"></i>
                                            <p>No shared items yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="fm-list">
                                            <?php renderSharedTree($sharedTree); ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Folder -->
    <div class="modal fade" id="modalFolder">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>New Folder</h4>
                            </div>

                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form method="POST" class="tax-rate-form">
                                <input type="hidden" name="action" value="add_folder">
                                <input type="hidden" name="parent_id" id="folderParent">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span> *</span></label>
                                            <input type="text" name="name" placeholder="Enter Folder Name"
                                                id="folderName" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Files -->
    <div class="modal fade" id="modalFile">
        <div class="modal-dialog modal-dialog-centered custom-modal-two">
            <div class="modal-content">
                <div class="page-wrapper-new p-0">
                    <div class="content">
                        <div class="modal-header border-0 custom-modal-header">
                            <div class="page-title">
                                <h4>New Spreadsheet</h4>
                            </div>

                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body custom-modal-body">
                            <form method="POST" class="tax-rate-form">
                                <input type="hidden" name="action" value="add_file">
                                <input type="hidden" name="parent_id" id="fileParent">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">File Name <span> *</span></label>
                                            <input type="text" name="name" placeholder="Enter File name" id="fileName"
                                                class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-btn">
                                    <button type="button" class="btn btn-cancel me-2"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="submit" class="btn btn-submit">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Share  -->
    <div class="modal fade" id="modalShare" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="share">
                <input type="hidden" name="file_id" id="shareId">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Users</label>
                        <select name="users[]" class="form-control select2-multiple" multiple id="shareUsersSelect">
                            <?php foreach ($allAdmins as $a): ?>
                                <option value="<?= $a['admin_id'] ?>"><?= htmlspecialchars($a['admin_username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permission</label>
                        <select name="permission" class="form-control" id="sharePermission">
                            <option value="VIEW">View Only</option>
                            <option value="EDIT">Can Edit</option>
                        </select>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tip:</strong> To remove all sharing, deselect all users and click Share.
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fa fa-close me-2"></i>Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-share me-2"></i>Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <!-- Load Mousewheel plugin BEFORE Luckysheet -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-mousewheel@3.1.13/jquery.mousewheel.min.js"></script>

    <!-- Then load Luckysheet -->
    <script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/js/plugin.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/luckysheet.umd.js"></script>

    <!-- Load SweetAlert AFTER Luckysheet to override the conflicting Swal -->
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <!-- This will ensure SweetAlert's Swal takes precedence -->

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="assets/js/feather.min.js"></script>
    <script src="assets/js/jquery.slimscroll.min.js"></script>
    <script src="assets/plugins/select2/js/select2.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/custom-select2.js"></script>
    <script src="assets/js/custom.js"></script>

    <!-- Remove the duplicate SweetAlert loads -->
    <script src="assets/js/script.js"></script>

    <script>

        // Show notifications when DOM is ready
        $(document).ready(function () {

            // Single Notyf instance - create it once
            const notyf = new Notyf({
                duration: 4000,
                position: {
                    x: 'center',
                    y: 'top'
                },
                types: [
                    {
                        type: 'success',
                        background: '#4dc76f',
                        icon: {
                            className: 'fas fa-check',
                            tagName: 'i',
                            text: ''
                        }
                    },
                    {
                        type: 'error',
                        background: '#ff1916',
                        icon: {
                            className: 'fas fa-times',
                            tagName: 'i',
                            text: ''
                        }
                    }
                ]
            });

            function initSelect2() {
                $('.select2-multiple').select2({
                    width: '100%',
                    placeholder: 'Choose users...',
                    allowClear: true
                });
            }

            // Fixed folder toggle functionality
            $(document).on('click', '.fm-folder-toggle', function () {
                const $toggle = $(this);
                const $children = $toggle.closest('.fm-folder-item').find('> .fm-folder-children');

                $toggle.toggleClass('collapsed');
                $children.slideToggle(200);
            });


            $(document).on('click', '.add-in-btn', function () {
                $('#fileParent').val($(this).data('id'));
            });

            $(document).on('click', '.share-btn', function () {
                const data = $(this).data();
                $('#shareId').val(data.id);
                $('#shareModalTitle').text('Share ' + (data.type === 'folder' ? 'Folder' : 'File') + ': ' + data.name);
                $('#sharePermission').val(data.permission);
                $('#folderShareInfo').toggle(data.type === 'folder');
                $('#fileShareInfo').toggle(data.type !== 'folder');

                initSelect2();
                $('#shareUsersSelect').val(data.sharedUsers).trigger('change');
                $('#modalShare').modal('show');
            });

            $('#modalShare').on('hidden.bs.modal', () => {
                $('.select2-multiple').select2('destroy');
            });

            // Handle the click event on the delete button
            $(document).on('click', '.deleteButton', function (event) {
                const d = $(this).data();

                const formData = {
                    item_id: d.id,
                    action: "delete"
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
                        // Send AJAX request to delete the record from the database
                        $.ajax({
                            url: window.location.href, // The PHP file that will handle the deletion
                            type: 'POST',
                            data: formData,
                            success: function (response) {

                                let result = JSON.parse(response);
                                // console.log(result);
                                if (result.status == 200) {
                                    // Show success message and reload the page
                                    Swal.fire(
                                        'Deleted!',
                                        `The ${d.name} has been deleted.`,
                                    ).then(() => {
                                        // Reload the page or remove the deleted row from the UI
                                        location.reload();
                                    });
                                } else if (result.status == 400) {
                                    Swal.fire(
                                        'Error!',
                                        `${result.error}`,
                                        'error'
                                    );
                                } else if (result.status == 500) {
                                    Swal.fire(
                                        'Error!',
                                        `${result.error}`,
                                        'error'
                                    );
                                } else {
                                    Swal.fire(
                                        'Error!',
                                        `There was an error deleting the data.`,
                                        'error'
                                    );
                                }

                            },
                            error: function (xhr, status, error) {
                                // Show error message if the AJAX request fails
                                Swal.fire(
                                    'Error!',
                                    'There was an error deleting the data.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>