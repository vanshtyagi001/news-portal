<?php
session_start();
require_once 'includes/db.php';

// Role and Login Checks
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true || $_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied");
    exit;
}

$admin_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Critical security checks
if ($admin_id_to_delete == 0) {
    header("location: manage-admins.php"); // No ID provided
    exit;
}
if ($admin_id_to_delete == $_SESSION['admin_id']) {
    header("location: manage-admins.php?error=self_delete"); // Cannot delete self
    exit;
}
if ($admin_id_to_delete == 1) {
    header("location: manage-admins.php?error=primary_delete"); // Cannot delete primary admin
    exit;
}

// Check if the admin has posts assigned to them
$sql_check = "SELECT COUNT(*) as post_count FROM posts WHERE author_id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $admin_id_to_delete);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$post_count = mysqli_fetch_assoc($result)['post_count'];
mysqli_stmt_close($stmt_check);

if ($post_count > 0) {
    // You cannot delete an admin who has authored posts.
    // You must reassign their posts first. This is a crucial data integrity rule.
    header("location: manage-admins.php?error=reassign_posts&count=$post_count");
    exit();
}

// If all checks pass, proceed with deletion
$sql_delete = "DELETE FROM admins WHERE id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $admin_id_to_delete);
mysqli_stmt_execute($stmt_delete);
mysqli_stmt_close($stmt_delete);

header("location: manage-admins.php?status=deleted");
exit();
?>