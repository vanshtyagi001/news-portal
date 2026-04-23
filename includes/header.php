<?php
/**
 * Express News - Main Frontend Header (v16 - Complete Theme System)
 * This file includes all core dependencies and renders the final, professional
 * single-bar navigation header, with a unified structure for all devices.
 * Theme (color + font) is fully admin-controlled via the database.
 */

// --- SMART INCLUDE ---
// This ensures that the database connection is available, whether this header is included
// by a simple page (like index.php) or a complex one that already connected (like news.php).
if (!isset($conn)) {
    require_once __DIR__ . '/../admin/includes/db.php';
    require_once __DIR__ . '/ads.php';
}

// Start session if not already started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Fetch all site settings for branding and functionality ---
$site_settings = [];
if (isset($conn)) {
    $settings_result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
    if ($settings_result) {
        while($row = mysqli_fetch_assoc($settings_result)){
            $site_settings[$row['setting_name']] = $row['setting_value'];
        }
    }
}

// Set default values in case settings are missing from the database
$site_name = $site_settings['site_name'] ?? 'Express News';
$site_tagline = $site_settings['site_tagline'] ?? 'Your Daily News Source';
$site_logo = $site_settings['site_logo'] ?? 'assets/images/logo.png';
$site_favicon = $site_settings['site_favicon'] ?? 'assets/images/favicon.ico';
$site_theme = $site_settings['site_theme'] ?? 'default';
$site_font = $site_settings['site_font'] ?? 'default';

// Construct the final page title using the dynamic site name
$final_page_title = isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars($site_name) : htmlspecialchars($site_name) . ' - ' . htmlspecialchars($site_tagline);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $final_page_title; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? htmlspecialchars($page_description) : 'The latest updates on politics, technology, sports, and more from around the world, brought to you by Express News.'; ?>">
    
    <!-- Dynamic Favicon -->
    <link rel="icon" href="/express-news/<?php echo htmlspecialchars($site_favicon); ?>" type="image/x-icon">
    
    <!-- Professional SEO & Social Tags -->
    <?php if (isset($canonical_url)): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>" />
    <?php endif; ?>
    <?php if (isset($og_data) && is_array($og_data)): ?>
        <meta property="og:type" content="<?php echo htmlspecialchars($og_data['type']); ?>" />
        <meta property="og:title" content="<?php echo htmlspecialchars($og_data['title']); ?>" />
        <meta property="og:description" content="<?php echo htmlspecialchars($og_data['description']); ?>" />
        <meta property="og:url" content="<?php echo htmlspecialchars($og_data['url']); ?>" />
        <meta property="og:site_name" content="<?php echo htmlspecialchars($site_name); ?>" />
        <meta property="og:image" content="<?php echo htmlspecialchars($og_data['image']); ?>" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:url" content="<?php echo htmlspecialchars($og_data['url']); ?>" />
        <meta name="twitter:title" content="<?php echo htmlspecialchars($og_data['title']); ?>" />
        <meta name="twitter:description" content="<?php echo htmlspecialchars($og_data['description']); ?>" />
        <meta name="twitter:image" content="<?php echo htmlspecialchars($og_data['image']); ?>" />
    <?php endif; ?>
    <?php if (isset($json_ld_data)): ?>
        <script type="application/ld+json"><?php echo json_encode($json_ld_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?></script>
    <?php endif; ?>
    
    <!-- Google Fonts (all font themes — loaded once, used by CSS variables) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Lato:ital,wght@0,300;0,400;0,700;1,400&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&family=Montserrat:wght@400;500;600;700;800&family=Nunito:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&family=Orbitron:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,700&family=Poppins:wght@300;400;500;600;700;800&family=Roboto+Slab:wght@300;400;500;600;700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- External Libraries & Custom Stylesheet -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="/express-news/assets/css/style.css">
</head>
<?php
    // Themes with dark backgrounds — add dark-mode class for legacy CSS compatibility
    $dark_themes = ['dark', 'tech', 'gradient'];
    $body_classes = [];
    if (in_array($site_theme, $dark_themes)) {
        $body_classes[] = 'dark-mode';
    }
    $body_class_str = !empty($body_classes) ? ' class="' . implode(' ', $body_classes) . '"' : '';
?>
<body data-theme="<?php echo htmlspecialchars($site_theme); ?>" data-font="<?php echo htmlspecialchars($site_font); ?>"<?php echo $body_class_str; ?> style="transition: background 0.35s ease, color 0.35s ease;">
    <header class="main-header" id="main-header">
        <nav class="navbar navbar-expand-lg ">
            <div class="container header-container">
                
                <!-- 1. Site Branding (Logo and Tagline) - CORRECTED -->
                <div class="site-branding">
                    <a class="navbar-brand" href="/express-news/">
                        <?php if(!empty($site_logo) && file_exists(__DIR__ . '/../' . $site_logo)): ?>
                            <img src="/express-news/<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?> Logo">
                        <?php else: ?>
                            <span class="h4 mb-0"><?php echo htmlspecialchars($site_name); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="tagline d-none d-lg-block">
                        <?php echo htmlspecialchars($site_tagline); ?>
                    </div>
                </div>


                <?php
                // Fetch categories once to be used by both desktop and mobile menus
                $all_categories = [];
                if (isset($conn)) {
                    $cat_result = mysqli_query($conn, "SELECT name, slug FROM categories ORDER BY name ASC");
                    if ($cat_result) { while ($row = mysqli_fetch_assoc($cat_result)) { $all_categories[] = $row; } }
                }
                ?>

                <!-- 2. Main Navigation (Desktop Only) -->
                <div class="main-navigation d-none d-lg-flex">
                    <ul class="navbar-nav main-navigation-items">
                        <?php
                        $visible_limit = 8;
                        $visible_categories = array_slice($all_categories, 0, $visible_limit);
                        $more_categories = array_slice($all_categories, $visible_limit);
                        
                        echo "<li class='nav-item'><a class='nav-link' href='/express-news/'>Home</a></li>";
                        foreach ($visible_categories as $category) {
                            echo "<li class='nav-item'><a class='nav-link' href='/express-news/category/" . $category['slug'] . "'>" . htmlspecialchars($category['name']) . "</a></li>";
                        }
                        if (!empty($more_categories)) {
                            echo '<li class="nav-item dropdown">';
                            echo '<a class="nav-link dropdown-toggle" href="#" id="moreDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">More</a>';
                            echo '<ul class="dropdown-menu" aria-labelledby="moreDropdown">';
                            foreach ($more_categories as $category) {
                                echo '<li><a class="dropdown-item" href="/express-news/category/' . $category['slug'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                            }
                            echo '</ul></li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- 3. Header Controls (Search, Profile, Hamburger) -->
                <div class="header-controls">
                    
                    <!-- Search and Profile are always visible -->
                    <button class="btn search-icon-btn" id="search-icon" type="button" title="Search"><i class="bi bi-search"></i></button>
                    <div class="profile-dropdown-container">
                        <button class="btn profile-icon-btn" id="profile-icon" title="My Account"><i class="fa-solid fa-user"></i></button>
                        <div class="profile-dropdown" id="profile-dropdown">
                             <?php if (isset($_SESSION['user_loggedin'])): ?>
                                <div class="dropdown-header">Hello, <?php echo htmlspecialchars($_SESSION['user_username']); ?>!</div>
                                <a href="/express-news/user/profile.php" class="dropdown-item">My Profile</a>
                                <a href="/express-news/user/edit-profile.php" class="dropdown-item">Settings</a>
                                <div class="dropdown-divider"></div>
                                <a href="/express-news/user/logout.php" class="dropdown-item">Logout</a>
                            <?php else: ?>
                                <a href="/express-news/user/login.php" class="dropdown-item">Login</a>
                                <a href="/express-news/user/register.php" class="dropdown-item">Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Hamburger Toggler (Mobile Only) -->
                    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNavbarContent" aria-controls="mobileNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>

            </div> <!-- /.container -->
        </nav>
        
        <!-- Collapsible Content for Mobile -->
        <div class="collapse navbar-collapse d-lg-none" id="mobileNavbarContent">
            <ul class="navbar-nav mobile-nav-items">
                <?php
                // We re-run the category loop for the mobile menu
                echo "<li class='nav-item'><a class='nav-link' href='/express-news/'>Home</a></li>";
                foreach ($all_categories as $category) { // Use the full list here
                    echo "<li class='nav-item'><a class='nav-link' href='/express-news/category/" . $category['slug'] . "'>" . htmlspecialchars($category['name']) . "</a></li>";
                }
                ?>
            </ul>
        </div>
    </header>
    
    <!-- Search Bar (Toggled by JS) -->
    <div class="search-bar-container" id="search-bar-container">
        <div class="container">
            <form class="d-flex p-2" action="/express-news/search.php" method="GET">
                <input class="form-control me-2" type="search" name="query" placeholder="Type to search..." required>
                <button class="btn btn-primary" type="submit">Search</button>
            </form>
        </div>
    </div>
    
    <!-- Header Ad Hook -->
    <div class="header-ad-hook-container container my-3 text-center">
        <?php if(isset($conn)) { display_ads_for_hook($conn, 'header_top'); } ?>
    </div>
    
    <!-- Main content starts here, and is closed by footer.php -->
    <main class="container my-4">