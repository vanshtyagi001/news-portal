<?php
// Ensure session is started and user is a logged-in admin.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true){
    header("location: index.php");
    exit;
}
require_once 'db.php';

// Get the current page name to set the 'active' class on nav links.
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Raj News</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <!-- Main Admin Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- New Editor Stylesheet -->
    <link rel="stylesheet" href="assets/css/velion-editor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
</head>
<body>
    <!-- Mobile Navigation Toggler (Hamburger Menu) -->
    <button class="mobile-nav-toggler" id="mobileNavToggler">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="admin-wrapper">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">Raj<span>News</span></a>
            </div>
            <div class="admin-info">
                <p><strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></p>
                <p><small><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($_SESSION['admin_role']))); ?></small></p>
            </div>
            <ul class="sidebar-nav">
                <li>
                    <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-tachometer-alt icon"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="manage-news.php" class="<?php echo in_array($current_page, ['manage-news.php', 'add-news.php', 'edit-news.php']) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-newspaper icon"></i> Manage News
                    </a>
                </li>
                
                <!-- Role-Based Menu Items -->
                <?php if (isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['super_admin', 'editor'])): ?>
                    <li>
                        <a href="manage-comments.php" class="<?php echo ($current_page == 'manage-comments.php') ? 'active' : ''; ?>">
                            <i class="fa-solid fa-comments icon"></i> Manage Comments
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'super_admin'): ?>
                    <li>
                        <a href="manage-categories.php" class="<?php echo ($current_page == 'manage-categories.php') ? 'active' : ''; ?>">
                            <i class="fa-solid fa-tags icon"></i> Manage Categories
                        </a>
                    </li>
                    <li>
                        <a href="manage-admins.php" class="<?php echo in_array($current_page, ['manage-admins.php', 'edit-admin.php', 'signup.php']) ? 'active' : ''; ?>">
                            <i class="fa-solid fa-users-cog icon"></i> Manage Admins
                        </a>
                    </li>
                    <li>
        <a href="manage-ads.php" class="<?php echo in_array($current_page, ['manage-ads.php', 'edit-ad.php']) ? 'active' : ''; ?>">
            <i class="fa-solid fa-bullhorn icon"></i> Manage Ads
        </a>
    </li>
                    <li>
                        <a href="settings.php" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                            <i class="fa-solid fa-cog icon"></i> Site Settings
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="mt-auto">
                    <a href="logout.php">
                        <i class="fa-solid fa-right-from-bracket icon"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        <div class="main-content" id="main-content">