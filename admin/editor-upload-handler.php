<?php
// admin/editor-upload-handler.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Ensure only a logged-in admin can use this endpoint.
if (!isset($_SESSION['admin_loggedin'])) {
    header('Content-Type: application/json');
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access Denied. You must be logged in.']);
    exit;
}

// Include your core DB and functions file
require_once 'includes/db.php';

header('Content-Type: application/json');

// --- File Upload Logic ---
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $file = $_FILES['image'];
    
    // Validation (you can make this more robust)
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo json_encode(['status' => 'error', 'message' => 'File is too large (Max 5MB).']);
        exit;
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, WebP are allowed.']);
        exit;
    }

    // Use your existing optimization logic!
    $target_dir = "../uploads/";
    $unique_basename = "editor_img_" . time() . '_' . uniqid();
    $destination_path_no_ext = $target_dir . $unique_basename;

    // Call your powerful optimize_image function
    $optimized_basename = optimize_image($file["tmp_name"], $destination_path_no_ext);

    if ($optimized_basename) {
        // Success! Send back the public URL of the JPG version.
        $public_url = '/raj-news/uploads/' . $optimized_basename . '.jpg';
        echo json_encode(['status' => 'success', 'url' => $public_url]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error: Could not process the image.']);
    }

} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or an upload error occurred.']);
}

exit;
?>