<?php
session_start();
require_once 'includes/db.php';

if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true){
    header("location: index.php");
    exit;
}

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($post_id > 0) {
    // --- PERMISSION CHECK ---
    $sql_author = "SELECT author_id, featured_image FROM posts WHERE id = ?";
    if($stmt_author = mysqli_prepare($conn, $sql_author)){
        mysqli_stmt_bind_param($stmt_author, "i", $post_id);
        mysqli_stmt_execute($stmt_author);
        mysqli_stmt_bind_result($stmt_author, $author_id, $image_path);
        
        // Fetch the result
        if (mysqli_stmt_fetch($stmt_author)) {
            mysqli_stmt_close($stmt_author); // Close the first statement here

            // Allow deletion ONLY if user is super_admin, editor, OR the author of the post.
            if (
                $_SESSION['admin_role'] == 'super_admin' ||
                $_SESSION['admin_role'] == 'editor' ||
                ($author_id == $_SESSION['admin_id'])
            ) {
                // Permission granted, proceed with deletion
                if (!empty($image_path) && file_exists("../" . $image_path)) {
                    unlink("../" . $image_path);
                }
                
                $sql_delete = "DELETE FROM posts WHERE id = ?";
                if ($stmt_delete = mysqli_prepare($conn, $sql_delete)) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $post_id);
                    mysqli_stmt_execute($stmt_delete);
                    mysqli_stmt_close($stmt_delete);
                }
                header("location: manage-news.php?status=deleted");
                exit();

            } else {
                // Permission denied
                header("location: manage-news.php?error=permission_denied");
                exit();
            }
        } else {
            // Post not found
             mysqli_stmt_close($stmt_author);
             header("location: manage-news.php");
             exit();
        }
    }
} else {
    header("location: manage-news.php");
    exit();
}
?>