<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php';

// Security Checks
if (!isset($_SESSION['admin_loggedin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

header('Content-Type: application/json');

// Use the request method to determine the action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $media_id = isset($data['id']) ? (int)$data['id'] : 0;

    // Validation for actions requiring a single, valid ID
    if (in_array($action, ['update_details', 'delete_media']) && $media_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Media ID.']);
        exit;
    }

    switch ($action) {
        case 'update_details':
            $alt_text = $data['alt_text'] ?? '';
            $title = $data['title'] ?? '';
            $caption = $data['caption'] ?? '';
            $description = $data['description'] ?? '';

            $sql = "UPDATE media SET alt_text = ?, title = ?, caption = ?, description = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $alt_text, $title, $caption, $description, $media_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Details updated successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update details.']);
            }
            mysqli_stmt_close($stmt);
            break;

        case 'delete_media':
            // First, get the filename to delete it from the server
            $stmt = mysqli_prepare($conn, "SELECT filename, is_image_optimized FROM media WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $media_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $media_item = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($media_item) {
                $upload_dir = '../uploads/';
                $file_to_delete = $upload_dir . $media_item['filename'];

                // Delete the file(s) from the server
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
                // If it was an optimized image, also delete its .webp companion
                if ($media_item['is_image_optimized']) {
                    $webp_file = $upload_dir . pathinfo($media_item['filename'], PATHINFO_FILENAME) . '.webp';
                    if (file_exists($webp_file)) {
                        unlink($webp_file);
                    }
                }

                // Now, delete the record from the database
                $stmt_delete = mysqli_prepare($conn, "DELETE FROM media WHERE id = ?");
                mysqli_stmt_bind_param($stmt_delete, "i", $media_id);
                if (mysqli_stmt_execute($stmt_delete)) {
                    echo json_encode(['success' => true, 'message' => 'Media deleted successfully.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete media from database.']);
                }
                mysqli_stmt_close($stmt_delete);

            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Media item not found.']);
            }
            break;

        // --- NEW CASE FOR BULK DELETE (MERGED FROM CODE 2) ---
        case 'bulk_delete':
            $media_ids = $data['ids'] ?? [];
            if (empty($media_ids) || !is_array($media_ids)) {
                http_response_code(400);
                echo json_encode(['error' => 'No media IDs provided for bulk deletion.']);
                exit;
            }

            // Sanitize all IDs to be integers
            $sanitized_ids = array_map('intval', $media_ids);
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
            
            // Begin transaction for safety
            mysqli_begin_transaction($conn);
            try {
                // First, fetch all filenames to delete from the server
                $sql_fetch = "SELECT filename, is_image_optimized FROM media WHERE id IN ($placeholders)";
                $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
                $types = str_repeat('i', count($sanitized_ids));
                mysqli_stmt_bind_param($stmt_fetch, $types, ...$sanitized_ids);
                mysqli_stmt_execute($stmt_fetch);
                $result = mysqli_stmt_get_result($stmt_fetch);
                
                $files_to_delete_from_server = mysqli_fetch_all($result, MYSQLI_ASSOC);
                mysqli_stmt_close($stmt_fetch);

                foreach ($files_to_delete_from_server as $media_item) {
                    $upload_dir = '../uploads/';
                    $file_to_delete = $upload_dir . $media_item['filename'];
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    if ($media_item['is_image_optimized']) {
                        $webp_file = $upload_dir . pathinfo($media_item['filename'], PATHINFO_FILENAME) . '.webp';
                        if (file_exists($webp_file)) {
                            unlink($webp_file);
                        }
                    }
                }

                // Now, delete all records from the database in one query
                $sql_delete = "DELETE FROM media WHERE id IN ($placeholders)";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                mysqli_stmt_bind_param($stmt_delete, $types, ...$sanitized_ids);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
                
                // If everything succeeded, commit the transaction
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Selected media deleted successfully.']);

            } catch (Exception $e) {
                // If any part failed, roll back all changes
                mysqli_rollback($conn);
                http_response_code(500);
                // For security, don't echo $e->getMessage() to the user directly
                error_log("Bulk delete failed: " . $e->getMessage()); 
                echo json_encode(['error' => 'An error occurred during bulk deletion. The operation was cancelled.']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid POST action.']);
            break;
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'get_all_media':
            $items_per_page = 24;
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($current_page < 1) { $current_page = 1; }
            $offset = ($current_page - 1) * $items_per_page;

            $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM media");
            $total_items = (int) mysqli_fetch_assoc($count_result)['total'];
            $total_pages = $total_items > 0 ? (int) ceil($total_items / $items_per_page) : 1;

            $sql = "SELECT m.*, a.username as uploader_name 
                    FROM media m 
                    LEFT JOIN admins a ON m.uploader_id = a.id 
                    ORDER BY m.created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $items_per_page, $offset);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $media_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
            
            echo json_encode([
                'media' => $media_items,
                'pagination' => [
                    'currentPage' => $current_page,
                    'totalPages' => $total_pages,
                    'totalItems' => $total_items
                ]
            ]);
            mysqli_stmt_close($stmt);
            break;

        case 'get_media_details':
            $media_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($media_id > 0) {
                $sql = "SELECT m.*, a.username as uploader_name FROM media m LEFT JOIN admins a ON m.uploader_id = a.id WHERE m.id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $media_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $media_item = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                if ($media_item) {
                    echo json_encode($media_item);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Media not found.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid ID.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid GET action.']);
            break;
    }
}

mysqli_close($conn);
exit;
?>