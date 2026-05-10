<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: index.php");
    exit;
}
require_once 'db.php';

// Fetch site name + logo
$admin_site_logo = '';
$admin_site_name = 'Express News';
$settings_res = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('site_logo','site_name')");
if ($settings_res) {
    while ($sr = mysqli_fetch_assoc($settings_res)) {
        if ($sr['setting_name'] === 'site_logo') $admin_site_logo = trim($sr['setting_value']);
        if ($sr['setting_name'] === 'site_name')  $admin_site_name  = trim($sr['setting_value']);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$role         = $_SESSION['admin_role'] ?? '';
$is_super     = ($role === 'super_admin');
$is_editor    = in_array($role, ['super_admin', 'editor']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?php echo htmlspecialchars($admin_site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/velion-editor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
</head>
<body>

    <!-- Mobile hamburger -->
    <button class="mobile-nav-toggler" id="mobileNavToggler" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="admin-wrapper">

        <!-- ══ SIDEBAR ══════════════════════════════════════ -->
        <aside class="sidebar" id="sidebar">

            <!-- Logo -->
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">
                    <?php if (!empty($admin_site_logo) && file_exists(__DIR__ . '/../../' . ltrim($admin_site_logo, '/\\'))): ?>
                        <img src="../<?php echo htmlspecialchars($admin_site_logo); ?>"
                             alt="<?php echo htmlspecialchars($admin_site_name); ?>"
                             class="admin-site-logo">
                    <?php else: ?>
                        <?php echo htmlspecialchars($admin_site_name); ?>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Admin info -->
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
                </div>
                <p><strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></p>
                <p><small><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($role))); ?></small></p>
            </div>

            <!-- Scrollable nav -->
            <ul class="sidebar-nav" id="sidebarNav">

                <li>
                    <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-gauge-high icon"></i> Dashboard
                    </a>
                </li>

                <li>
                    <a href="manage-news.php" class="<?php echo in_array($current_page, ['manage-news.php','add-news.php','edit-news.php']) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-newspaper icon"></i> Manage News
                    </a>
                </li>

                <li>
                    <a href="manage-media.php" class="<?php echo $current_page === 'manage-media.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-photo-film icon"></i> Media Library
                    </a>
                </li>

                <?php if ($is_editor): ?>
                <li>
                    <a href="manage-comments.php" class="<?php echo $current_page === 'manage-comments.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-comments icon"></i> Comments
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($is_super): ?>

                <li class="sidebar-section-label">Content</li>

                <li>
                    <a href="manage-categories.php" class="<?php echo $current_page === 'manage-categories.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-tags icon"></i> Categories
                    </a>
                </li>

                <li class="sidebar-section-label">People</li>

                <li>
                    <a href="manage-admins.php" class="<?php echo in_array($current_page, ['manage-admins.php','edit-admin.php','signup.php']) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users-cog icon"></i> Manage Admins
                    </a>
                </li>

                <li class="sidebar-section-label">Monetization</li>

                <li>
                    <a href="manage-ads.php" class="<?php echo in_array($current_page, ['manage-ads.php','edit-ad.php']) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-bullhorn icon"></i> Manage Ads
                    </a>
                </li>

                <li class="sidebar-section-label">Appearance</li>

                <li>
                    <a href="theme-settings.php" class="<?php echo $current_page === 'theme-settings.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-palette icon"></i> Theme
                    </a>
                </li>

                <li class="sidebar-section-label">System</li>

                <li>
                    <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-sliders icon"></i> Site Settings
                    </a>
                </li>

                <?php endif; ?>

                <!-- Logout pinned at bottom -->
                <li class="sidebar-logout">
                    <a href="logout.php">
                        <i class="fa-solid fa-right-from-bracket icon"></i> Logout
                    </a>
                </li>

            </ul>

        </aside>
        <!-- ══ END SIDEBAR ══════════════════════════════════ -->

        <div class="main-content" id="main-content">
