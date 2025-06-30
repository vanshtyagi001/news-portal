<?php
// ajax-handler.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/admin/includes/db.php';

// Ensure user is logged in for these actions
if (!isset($_SESSION['user_loggedin'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to perform this action.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

if ($post_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Post ID.']);
    exit;
}

header('Content-Type: application/json');

// --- LIKE/UNLIKE LOGIC ---
if ($action === 'toggle_like') {
    // Check if the user has already liked this post
    $stmt_check = mysqli_prepare($conn, "SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $post_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($result_check) > 0) {
        // User has liked it, so UNLIKE it
        $like_id = mysqli_fetch_assoc($result_check)['id'];
        $stmt_delete = mysqli_prepare($conn, "DELETE FROM post_likes WHERE id = ?");
        mysqli_stmt_bind_param($stmt_delete, "i", $like_id);
        mysqli_stmt_execute($stmt_delete);
        
        // Decrement the likes_count in the posts table
        mysqli_query($conn, "UPDATE posts SET likes_count = likes_count - 1 WHERE id = $post_id");
        
        $new_count_res = mysqli_query($conn, "SELECT likes_count FROM posts WHERE id = $post_id");
        $new_count = mysqli_fetch_assoc($new_count_res)['likes_count'];

        echo json_encode(['status' => 'unliked', 'new_count' => $new_count]);
    } else {
        // User has not liked it, so LIKE it
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "ii", $user_id, $post_id);
        mysqli_stmt_execute($stmt_insert);
        
        // Increment the likes_count in the posts table
        mysqli_query($conn, "UPDATE posts SET likes_count = likes_count + 1 WHERE id = $post_id");
        
        $new_count_res = mysqli_query($conn, "SELECT likes_count FROM posts WHERE id = $post_id");
        $new_count = mysqli_fetch_assoc($new_count_res)['likes_count'];

        echo json_encode(['status' => 'liked', 'new_count' => $new_count]);
    }
    mysqli_stmt_close($stmt_check);
    exit;
}

// --- BOOKMARK/UNBOOKMARK LOGIC ---
if ($action === 'toggle_bookmark') {
    $stmt_check = mysqli_prepare($conn, "SELECT id FROM user_bookmarks WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $post_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($result_check) > 0) {
        // User has bookmarked it, so REMOVE bookmark
        $bookmark_id = mysqli_fetch_assoc($result_check)['id'];
        $stmt_delete = mysqli_prepare($conn, "DELETE FROM user_bookmarks WHERE id = ?");
        mysqli_stmt_bind_param($stmt_delete, "i", $bookmark_id);
        mysqli_stmt_execute($stmt_delete);
        echo json_encode(['status' => 'unbookmarked']);
    } else {
        // User has not bookmarked it, so ADD bookmark
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO user_bookmarks (user_id, post_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "ii", $user_id, $post_id);
        mysqli_stmt_execute($stmt_insert);
        echo json_encode(['status' => 'bookmarked']);
    }
    mysqli_stmt_close($stmt_check);
    exit;
}

mysqli_close($conn);
?>