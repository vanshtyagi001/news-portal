<?php
// article-redirect.php

require_once 'admin/includes/db.php';

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($post_id > 0) {
    // Find the slug for the given post ID
    $stmt = mysqli_prepare($conn, "SELECT slug FROM posts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($post = mysqli_fetch_assoc($result)) {
        $slug = $post['slug'];
        // Construct the clean, final URL
        $new_url = '/raj-news/news/' . $slug;
        
        // Perform a 301 Permanent Redirect
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $new_url);
        exit();
    }
}

// If post not found or no ID given, redirect to the homepage
header("HTTP/1.1 404 Not Found");
header("Location: /raj-news/");
exit();
?>