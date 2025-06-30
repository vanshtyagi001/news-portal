<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Best practice for AJAX endpoints: ensure clean JSON output.
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';

// Security Checks
if (!isset($_SESSION['admin_loggedin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

header('Content-Type: application/json');
$response = ['success' => false, 'error' => 'An unknown error occurred.'];

if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
    $file = $_FILES['file'];
    $uploader_id = $_SESSION['admin_id'];
    $upload_dir = '../uploads/';

    $original_name = basename($file["name"]);
    $safe_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);

    $is_image_optimized = 0;
    
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($file['type'], $image_types)) {
        $image_base_name = pathinfo($safe_filename, PATHINFO_FILENAME);
        $optimized_base = optimize_image($file["tmp_name"], $upload_dir . $image_base_name);

        if ($optimized_base) {
            $safe_filename = $optimized_base . '.jpg';
            $is_image_optimized = 1;
            $upload_success = true;
        } else {
            $upload_success = false;
            $response['error'] = 'Image optimization failed.';
        }
    } else {
        $target_file = $upload_dir . $safe_filename;
        $upload_success = move_uploaded_file($file["tmp_name"], $target_file);
    }

    if ($upload_success) {
        // Use the original filename (without extension) as a sensible default for title and alt_text
        $default_title = pathinfo($original_name, PATHINFO_FILENAME);
        
        // The SQL now correctly matches the updated table structure
        $sql = "INSERT INTO media (filename, file_type, file_size, title, alt_text, uploader_id, is_image_optimized) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssisssi", 
                $safe_filename, 
                $file['type'], 
                $file['size'],
                $default_title, 
                $default_title,
                $uploader_id,
                $is_image_optimized
            );

            if(mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'File uploaded successfully!'];
            } else {
                $response['error'] = 'Database insert failed.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['error'] = 'Database statement could not be prepared.';
        }
    } else if (!isset($response['error'])) {
        $response['error'] = 'Failed to move uploaded file.';
    }

} else {
    $error_code = $_FILES['file']['error'] ?? 'No file data';
    $response['error'] = 'Upload Error Code: ' . $error_code;
}

echo json_encode($response);
exit;
?>