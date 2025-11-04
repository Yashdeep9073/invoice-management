<?php
session_start();

require "./database/config.php";
require "./utility/env.php";
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

// Check if user has access to this file
try {
    $stmt = $db->prepare("
        SELECT ff.* 
        FROM file_folders ff 
        WHERE ff.id = ? AND (ff.created_by = ? OR ff.id IN (
            SELECT file_folder_id FROM file_permissions WHERE user_id = ?
        ))
    ");
    $stmt->bind_param("iii", $file_id, $admin_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();



    if (!$file || $file['type'] !== 'FILE') {
        $_SESSION['error'] = "File not found or you don't have access";
        header("Location: " . getenv("BASE_URL") . "spreadsheet");
        exit;
    }

    if (!$file['file_path'] || !file_exists($file['file_path'])) {
        $_SESSION['error'] = "File not found on server";
        header("Location: " . getenv("BASE_URL") . "spreadsheet");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Failed to download file: " . $e->getMessage();
    header("Location: " . getenv("BASE_URL") . "spreadsheet");
    exit;
}

// Convert JSON to CSV for download
function jsonToCsv($jsonData)
{
    $output = '';

    if (isset($jsonData['data']) && is_array($jsonData['data'])) {
        foreach ($jsonData['data'] as $sheetIndex => $sheet) {
            if ($sheetIndex > 0) {
                $output .= "\n\n--- Sheet: " . ($sheet['name'] ?? "Sheet" . ($sheetIndex + 1)) . " ---\n\n";
            }

            // Get cell data from celldata or data array
            $cellData = [];
            if (isset($sheet['celldata']) && is_array($sheet['celldata'])) {
                $cellData = $sheet['celldata'];
            } elseif (isset($sheet['data']) && is_array($sheet['data'])) {
                // Alternative data structure
                foreach ($sheet['data'] as $rowIndex => $row) {
                    if (is_array($row)) {
                        foreach ($row as $colIndex => $cell) {
                            if ($cell !== null && $cell !== '') {
                                $cellData[] = [
                                    'r' => $rowIndex,
                                    'c' => $colIndex,
                                    'v' => $cell
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($cellData)) {
                // Create a simple grid representation
                $maxRow = 0;
                $maxCol = 0;
                $cells = [];

                foreach ($cellData as $cell) {
                    if (isset($cell['r']) && isset($cell['c']) && isset($cell['v'])) {
                        $row = $cell['r'];
                        $col = $cell['c'];
                        $value = $cell['v'];

                        // Handle different value types
                        if (is_array($value)) {
                            // If value is an array, try to get the actual value
                            if (isset($value['v'])) {
                                $value = $value['v'];
                            } elseif (isset($value['m'])) {
                                $value = $value['m'];
                            } else {
                                $value = ''; // Default to empty if we can't extract value
                            }
                        }

                        // Convert to string and handle special cases
                        $value = (string) $value;

                        $cells[$row][$col] = $value;
                        $maxRow = max($maxRow, $row);
                        $maxCol = max($maxCol, $col);
                    }
                }

                // Create CSV content
                for ($row = 0; $row <= $maxRow; $row++) {
                    $rowData = [];
                    for ($col = 0; $col <= $maxCol; $col++) {
                        $value = isset($cells[$row][$col]) ? $cells[$row][$col] : '';

                        // Ensure value is a string
                        $value = (string) $value;

                        // Escape CSV special characters
                        if ($value !== '' && (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false)) {
                            $value = '"' . str_replace('"', '""', $value) . '"';
                        }
                        $rowData[] = $value;
                    }
                    $output .= implode(',', $rowData) . "\n";
                }
            } else {
                // If no cell data, create empty sheet representation
                $output .= "Empty Sheet\n";
            }
        }
    } else {
        $output = "No data available";
    }

    return $output;
}

// Handle download
$fileContent = file_get_contents($file['file_path']);
$spreadsheetData = json_decode($fileContent, true);

// Check if JSON decoding was successful
if ($spreadsheetData === null) {
    $_SESSION['error'] = "Invalid file format";
    header("Location: " . getenv("BASE_URL") . "spreadsheet");
    exit;
}

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $file['name']) . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV content
echo jsonToCsv($spreadsheetData);
exit;
?>