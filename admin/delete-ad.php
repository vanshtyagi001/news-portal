<?php
session_start();
require_once 'includes/db.php';

// Role and Login Checks
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ad_id > 0) {
    // Delete the image file from the server if it's an image ad
    $stmt = mysqli_prepare($conn, "SELECT ad_type, ad_content FROM ads WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $ad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($ad = mysqli_fetch_assoc($result)) {
        if ($ad['ad_type'] == 'image' && !empty($ad['ad_content']) && file_exists("../" . $ad['ad_content'])) {
            unlink("../" . $ad['ad_content']);
        }
    }
    mysqli_stmt_close($stmt);
    
    // Delete the ad from the database
    $stmt_delete = mysqli_prepare($conn, "DELETE FROM ads WHERE id = ?");
    mysqli_stmt_bind_param($stmt_delete, "i", $ad_id);
    mysqli_stmt_execute($stmt_delete);
    mysqli_stmt_close($stmt_delete);
}

header("location: manage-ads.php?status=deleted");
exit();
?>