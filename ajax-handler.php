<?php
/**
 * Express News - AJAX Handler
 * Handles like and bookmark toggles.
 * Always returns JSON.
 */

// JSON header must come first — before any output
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/admin/includes/db.php';

// Helper: send JSON and exit
function json_out(array $data): void {
    echo json_encode($data);
    exit;
}

// Must be logged in
if (!isset($_SESSION['user_loggedin'])) {
    json_out(['status' => 'error', 'message' => 'You must be logged in to perform this action.']);
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

if ($user_id === 0 || $post_id === 0) {
    json_out(['status' => 'error', 'message' => 'Invalid request parameters.']);
}

// ─── LIKE / UNLIKE ───────────────────────────────────────────
if ($action === 'toggle_like') {

    // Check existing like
    $stmt = mysqli_prepare($conn, "SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($existing) {
        // Remove like
        $del = mysqli_prepare($conn, "DELETE FROM post_likes WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $existing['id']);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        // Decrement — never go below 0
        mysqli_query($conn, "UPDATE posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = $post_id");
        $status = 'unliked';

    } else {
        // Add like — INSERT IGNORE handles race conditions / duplicate clicks
        $ins = mysqli_prepare($conn, "INSERT IGNORE INTO post_likes (user_id, post_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, "ii", $user_id, $post_id);
        mysqli_stmt_execute($ins);
        $affected = mysqli_stmt_affected_rows($ins);
        mysqli_stmt_close($ins);

        if ($affected > 0) {
            mysqli_query($conn, "UPDATE posts SET likes_count = likes_count + 1 WHERE id = $post_id");
        }
        $status = 'liked';
    }

    // Fetch fresh count
    $cnt_res   = mysqli_query($conn, "SELECT likes_count FROM posts WHERE id = $post_id");
    $new_count = $cnt_res ? (int)mysqli_fetch_assoc($cnt_res)['likes_count'] : 0;

    json_out(['status' => $status, 'new_count' => $new_count]);
}

// ─── BOOKMARK / UNBOOKMARK ───────────────────────────────────
if ($action === 'toggle_bookmark') {

    $stmt = mysqli_prepare($conn, "SELECT id FROM user_bookmarks WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($stmt);
    $result   = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($existing) {
        $del = mysqli_prepare($conn, "DELETE FROM user_bookmarks WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $existing['id']);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        json_out(['status' => 'unbookmarked']);
    } else {
        $ins = mysqli_prepare($conn, "INSERT IGNORE INTO user_bookmarks (user_id, post_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, "ii", $user_id, $post_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        json_out(['status' => 'bookmarked']);
    }
}

// Unknown action
json_out(['status' => 'error', 'message' => 'Unknown action.']);
