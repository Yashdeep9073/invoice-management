<?php

/**
 * Uploads a single file after validating its type and size.
 *
 * @param array $file The $_FILES array element for the specific file (e.g., $_FILES['single_file'] or $_FILES['multi_file']['name'][0]).
 * @param string $uploadDir The directory where the file should be uploaded.
 * @param array $allowedTypes An array of allowed file extensions (lowercase).
 * @param int $maxSize The maximum allowed file size in bytes.
 * @return array An associative array containing 'filename' on success or 'error' on failure.
 */
function uploadSingleFile($file, $uploadDir, $allowedTypes, $maxSize)
{
    $errorMsg = "";

    // Check if a file was actually uploaded
    if (empty($file["name"]) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return ["error" => "No file uploaded."];
    }

    // Check for standard upload errors
    if ($file["error"] !== UPLOAD_ERR_OK) {
        switch ($file["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ["error" => "File size exceeds the upload limit."];
            case UPLOAD_ERR_PARTIAL:
                return ["error" => "File was only partially uploaded."];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ["error" => "Missing a temporary folder."];
            case UPLOAD_ERR_CANT_WRITE:
                return ["error" => "Failed to write file to disk."];
            case UPLOAD_ERR_EXTENSION:
                return ["error" => "A PHP extension stopped the file upload."];
            default:
                return ["error" => "An unknown error occurred during upload."];
        }
    }

    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) { // Changed permissions from 0777 for security
            return ["error" => "Failed to create upload directory: " . $uploadDir];
        }
    }

    // Get file details
    $originalName = basename($file["name"]);
    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $fileSize = $file["size"];
    $tempPath = $file["tmp_name"];

    // Validate file type against allowed list
    if (!in_array($fileExt, $allowedTypes)) {
        return ["error" => "Invalid file format. Only the following types are allowed: " . implode(', ', $allowedTypes)];
    }

    // Validate file size
    if ($fileSize > $maxSize) {
        $maxSizeMB = $maxSize / (1024 * 1024);
        return ["error" => "File size exceeds {$maxSizeMB}MB limit."];
    }

    // Sanitize original filename (remove potentially dangerous characters)
    $safeFilename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", pathinfo($originalName, PATHINFO_FILENAME)); // Sanitize name part only
    $uniqueFilename = $safeFilename . '_' . uniqid() . '.' . $fileExt; // Append unique ID to sanitized name
    $targetFilePath = $uploadDir . $uniqueFilename;

    // Check if generated target file path already exists (unlikely with uniqid, but good practice)
    if (file_exists($targetFilePath)) {
        return ["error" => "Generated filename already exists (rare collision)."];
    }

    // Move the uploaded file to the target location
    if (move_uploaded_file($tempPath, $targetFilePath)) {
        return ["filename" => $targetFilePath]; // Return the path of the successfully uploaded file
    } else {
        return ["error" => "Error uploading file to final location."];
    }
}

/**
 * Handles uploading of multiple files (or a single file from a multi-file structure).
 *
 * @param array $files The $_FILES array or a specific part of it for multiple files (e.g., $_FILES['multi_file']).
 * @param string $uploadDir The directory where the files should be uploaded.
 * @param array $allowedTypes An array of allowed file extensions (lowercase).
 * @param int $maxSize The maximum allowed file size in bytes.
 * @return array An array of results for each file attempt (success or error).
 */
function uploadMultipleFiles($files, $uploadDir, $allowedTypes, $maxSize)
{
    $results = [];

    // Check if $files is structured for multiple files (has 'name', 'type', etc. as arrays)
    if (isset($files['name']) && is_array($files['name'])) {
        // Iterate through each file in the multi-file structure
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            // Construct the individual file array for this iteration
            $individualFile = [
                'name' => $files['name'][$i],
                'full_path' => $files['full_path'][$i] ?? $files['name'][$i], // 'full_path' might not exist in all cases
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            // Process this individual file
            $result = uploadSingleFile($individualFile, $uploadDir, $allowedTypes, $maxSize);
            $results[] = $result; // Add the result for this file to the overall results array
        }
    } else {
        // If the structure isn't for multiple files, treat it as a single file upload attempt
        // This shouldn't happen if called correctly, but handle it gracefully
        $results[] = ["error" => "Invalid file structure for multiple upload handler."];
    }

    return $results;
}

/**
 * Main function to determine if it's a single or multiple file upload and handle accordingly.
 *
 * @param array $files The $_FILES array or a specific entry from it.
 * @param string $uploadDir The directory where the files should be uploaded.
 * @param array $allowedTypes An array of allowed file extensions (lowercase).
 * @param int $maxSize The maximum allowed file size in bytes.
 * @return array An array of results for each file attempt (success or error).
 */
function uploadMedia($files, $uploadDir, $allowedTypes, $maxSize)
{
    if (isset($files['name']) && is_array($files['name'])) {
        return uploadMultipleFiles($files, $uploadDir, $allowedTypes, $maxSize);
    }

    return uploadSingleFile($files, $uploadDir, $allowedTypes, $maxSize);
}


// Example usage:

// --- Single File Upload ---
// Assuming you have a form input: <input type="file" name="single_file" />
// $singleUploadResult = uploadMedia($_FILES['single_file'], '/path/to/uploads/', ['jpg', 'png', 'pdf'], 2 * 1024 * 1024);
// foreach ($singleUploadResult as $result) {
//     if (isset($result['error'])) {
//         echo "Upload Error: " . $result['error'] . "\n";
//     } else {
//         echo "File uploaded successfully to: " . $result['filename'] . "\n";
//     }
// }

// --- Multiple File Upload ---
// Assuming you have a form input: <input type="file" name="multi_file[]" multiple />
// $multiUploadResult = uploadMedia($_FILES['multi_file'], '/path/to/uploads/', ['jpg', 'png', 'pdf', 'xlsx'], 5 * 1024 * 1024);
// foreach ($multiUploadResult as $result) {
//     if (isset($result['error'])) {
//         echo "Upload Error: " . $result['error'] . "\n";
//     } else {
//         echo "File uploaded successfully to: " . $result['filename'] . "\n";
//     }
// }

// --- Handling Mixed $_FILES Array (like your example) ---
// $allResults = [];
// foreach ($_FILES as $key => $fileData) {
//     // Process each key in the $_FILES array
//     $results = uploadMedia($fileData, '/path/to/uploads/', ['jpg', 'png', 'pdf', 'xlsx'], 5 * 1024 * 1024);
//     $allResults[$key] = $results; // Store results under the original key
// }

// // Example of processing the mixed results
// foreach ($allResults as $inputName => $results) {
//     echo "Results for input '{$inputName}':\n";
//     foreach ($results as $result) {
//         if (isset($result['error'])) {
//             echo "  - Error: " . $result['error'] . "\n";
//         } else {
//             echo "  - Success: " . $result['filename'] . "\n";
//         }
//     }
// }

?>