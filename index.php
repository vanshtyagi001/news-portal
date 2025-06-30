<?php
/**
 * Raj News - Homepage (v9.2 - Final Image Fix)
 * This is the main landing page for the website.
 */

// Set page-specific variables for SEO and the <title> tag.
$page_title = 'Raj News - Your Daily News Source';
$page_description = 'Get the latest breaking news and updates on politics, technology, sports, and more. Your reliable source for daily news.';

// Include the header. This single line handles the DB connection, session start, and main navigation.
require_once 'includes/header.php';

// The $conn variable is now available for use on this page.
?>

<!-- Welcome message logic for users who have just logged in -->
<?php if (isset($_GET['status']) && $_GET['status'] == 'login_success' && isset($_SESSION['user_username'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Welcome back, <?php echo htmlspecialchars($_SESSION['user_username']); ?>!</strong> You have successfully logged in.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Headlines Carousel -->
<div id="topHeadlines" class="carousel slide mb-5" data-bs-ride="carousel">
    <div class="carousel-inner rounded shadow-sm">
        <?php
        $carousel_query = "SELECT title, slug, summary, featured_image FROM posts ORDER BY created_at DESC LIMIT 3";
        $carousel_result = mysqli_query($conn, $carousel_query);
        if ($carousel_result && mysqli_num_rows($carousel_result) > 0) {
            $active = 'active';
            while ($post = mysqli_fetch_assoc($carousel_result)) {
                // Use our new helper function to get the correct image paths
                $image_paths = getImagePaths($post['featured_image']);
        ?>
        <div class="carousel-item <?php echo $active; ?>">
            <a href="/raj-news/news/<?php echo $post['slug']; ?>">
                <picture>
                    <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                    <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                    <img src="<?php echo $image_paths['jpg']; ?>" class="d-block w-100 carousel-img" alt="<?php echo htmlspecialchars($post['title']); ?>">
                </picture>
            </a>
            <div class="carousel-caption">
                <h5><a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h5>
                <p class="d-none d-sm-block"><?php echo htmlspecialchars($post['summary']); ?></p>
            </div>
        </div>
        <?php 
            $active = ''; 
            } 
        } else { 
            echo '<div class="carousel-inner"><div class="carousel-item active"><div class="alert alert-info m-0">No breaking news to display.</div></div></div>'; 
        } 
        ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#topHeadlines" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
    <button class="carousel-control-next" type="button" data-bs-target="#topHeadlines" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
</div>

<!--
=====================================================
    Breaking News Ticker (with Admin-Controlled Speed)
=====================================================
-->
<?php
// Fetch the ticker speed setting from the database. Default to 40s if not found.
$ticker_speed_result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_name = 'ticker_speed'");
$ticker_speed = $ticker_speed_result ? mysqli_fetch_assoc($ticker_speed_result)['setting_value'] : 40;
?>
<div class="news-ticker-container">
    <div class="ticker-label">
        Latest
    </div>
    <div class="ticker-wrapper">
        <!-- 
            The inline style sets a CSS variable (--ticker-duration) using the value from the database.
            The stylesheet (style.css) will then use this variable for the animation duration.
        -->
        <div class="ticker-content" style="--ticker-duration: <?php echo (int)$ticker_speed; ?>s;">
            <?php
            // Fetch the 10 most recent news titles to display in the ticker
            $ticker_sql = "SELECT title, slug FROM posts ORDER BY created_at DESC LIMIT 10";
            $ticker_result = mysqli_query($conn, $ticker_sql);
            if ($ticker_result && mysqli_num_rows($ticker_result) > 0) {
                while ($ticker_post = mysqli_fetch_assoc($ticker_result)) {
                    // Use the clean, pretty URL format
                    $post_url = "/raj-news/news/" . htmlspecialchars($ticker_post['slug']);
                    // Output the link for the article
                    echo '<a href="' . $post_url . '">' . htmlspecialchars($ticker_post['title']) . '</a>';
                    // Add a separator for visual distinction between headlines
                    echo '<span class="separator">•</span>';
                }
            } else {
                // Display a fallback message if there are no news articles yet
                echo '<span>Welcome to Raj News! More headlines coming soon.</span>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Category-wise News Blocks -->
<?php
$categories_query = "SELECT id, name, slug FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

if ($categories_result) {
    while ($category = mysqli_fetch_assoc($categories_result)) {
        $initial_limit = 4;
        $cat_posts_query = "SELECT p.title, p.slug, p.featured_image FROM posts p JOIN post_categories pc ON p.id = pc.post_id WHERE pc.category_id = ? ORDER BY p.created_at DESC LIMIT ?";
        
        $stmt = mysqli_prepare($conn, $cat_posts_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $category['id'], $initial_limit);
            mysqli_stmt_execute($stmt);
            $cat_posts_result = mysqli_stmt_get_result($stmt);

            if ($cat_posts_result && mysqli_num_rows($cat_posts_result) > 0) {
?>
<div class="category-section mb-5">
    <h2 class="mb-3 border-bottom pb-2"><a href="/raj-news/category.php?slug=<?php echo $category['slug']; ?>"><?php echo htmlspecialchars($category['name']); ?></a></h2>
    <div class="row" id="post-container-<?php echo $category['id']; ?>">
        <?php while ($post = mysqli_fetch_assoc($cat_posts_result)) { 
            // Use our new helper function again here
            $image_paths = getImagePaths($post['featured_image']);
        ?>
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm news-card">
                 <a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>">
                    <picture>
                        <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                        <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                        <img src="<?php echo $image_paths['jpg']; ?>" class="card-img-top" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    </picture>
                </a>
                <div class="card-body d-flex flex-column">
                     <h6 class="card-title mb-auto"><a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h6>
                     <a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>" class="btn btn-sm btn-outline-primary mt-3 align-self-start">Read More</a>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
    
    <div class="text-center">
        <button class="btn btn-primary load-more-btn" data-category="<?php echo $category['id']; ?>" data-offset="<?php echo $initial_limit; ?>">Load More</button>
    </div>
</div>
<?php 
            } 
            mysqli_stmt_close($stmt);
        } 
    } 
} 
?>

<?php
require_once 'includes/footer.php';
?>