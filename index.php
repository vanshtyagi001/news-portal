<?php
/**
 * Express News - Homepage
 */
$page_title       = 'Express News - Your Daily News Source';
$page_description = 'Get the latest breaking news and updates on politics, technology, sports, and more.';
require_once 'includes/header.php';
?>

<?php if (isset($_GET['status']) && $_GET['status'] == 'login_success' && isset($_SESSION['user_username'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Welcome back, <?php echo htmlspecialchars($_SESSION['user_username']); ?>!</strong> You have successfully logged in.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- =====================================================
     BREAKING NEWS TICKER
     ===================================================== -->
<?php
$ticker_speed_result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_name = 'ticker_speed'");
$ticker_speed = $ticker_speed_result ? (mysqli_fetch_assoc($ticker_speed_result)['setting_value'] ?? 40) : 40;
?>
<div class="news-ticker-container mb-4">
    <div class="ticker-label">Breaking</div>
    <div class="ticker-wrapper">
        <div class="ticker-content" style="--ticker-duration: <?php echo (int)$ticker_speed; ?>s;">
            <?php
            $ticker_result = mysqli_query($conn, "SELECT title, slug FROM posts ORDER BY created_at DESC LIMIT 10");
            if ($ticker_result && mysqli_num_rows($ticker_result) > 0) {
                while ($tp = mysqli_fetch_assoc($ticker_result)) {
                    echo '<a href="/express-news/news/' . htmlspecialchars($tp['slug']) . '">' . htmlspecialchars($tp['title']) . '</a>';
                    echo '<span class="separator">•</span>';
                }
            } else {
                echo '<span>Welcome to Express News! More headlines coming soon.</span>';
            }
            ?>
        </div>
    </div>
</div>

<!-- =====================================================
     HERO — TOP HEADLINES CAROUSEL + SIDE CARDS
     ===================================================== -->
<?php
$hero_query  = "SELECT title, slug, summary, featured_image, created_at FROM posts ORDER BY created_at DESC LIMIT 5";
$hero_result = mysqli_query($conn, $hero_query);
$hero_posts  = [];
if ($hero_result) {
    while ($r = mysqli_fetch_assoc($hero_result)) $hero_posts[] = $r;
}
$main_post  = $hero_posts[0] ?? null;
$side_posts = array_slice($hero_posts, 1, 4);
?>

<?php if ($main_post): ?>
<section class="hero-section mb-5">
    <div class="row g-3">

        <!-- Main featured story -->
        <div class="col-lg-7">
            <?php $mp = getImagePaths($main_post['featured_image']); ?>
            <a href="/express-news/news/<?php echo htmlspecialchars($main_post['slug']); ?>" class="hero-main-card">
                <picture>
                    <source srcset="<?php echo $mp['webp']; ?>" type="image/webp">
                    <source srcset="<?php echo $mp['jpg']; ?>"  type="image/jpeg">
                    <img src="<?php echo $mp['jpg']; ?>" alt="<?php echo htmlspecialchars($main_post['title']); ?>" class="hero-main-img">
                </picture>
                <div class="hero-main-overlay">
                    <span class="hero-badge">Top Story</span>
                    <h2 class="hero-main-title"><?php echo htmlspecialchars($main_post['title']); ?></h2>
                    <p class="hero-main-summary d-none d-md-block"><?php echo htmlspecialchars($main_post['summary']); ?></p>
                    <span class="hero-main-date">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                        <?php echo date('M d, Y', strtotime($main_post['created_at'])); ?>
                    </span>
                </div>
            </a>
        </div>

        <!-- Side stories grid -->
        <div class="col-lg-5">
            <div class="row g-3 h-100">
                <?php foreach ($side_posts as $sp):
                    $simg = getImagePaths($sp['featured_image']); ?>
                <div class="col-6">
                    <a href="/express-news/news/<?php echo htmlspecialchars($sp['slug']); ?>" class="hero-side-card">
                        <picture>
                            <source srcset="<?php echo $simg['webp']; ?>" type="image/webp">
                            <source srcset="<?php echo $simg['jpg']; ?>"  type="image/jpeg">
                            <img src="<?php echo $simg['jpg']; ?>" alt="<?php echo htmlspecialchars($sp['title']); ?>" class="hero-side-img">
                        </picture>
                        <div class="hero-side-overlay">
                            <h6 class="hero-side-title"><?php echo htmlspecialchars($sp['title']); ?></h6>
                            <span class="hero-side-date"><?php echo date('M d', strtotime($sp['created_at'])); ?></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     CATEGORY NEWS SECTIONS
     ===================================================== -->
<?php
$categories_result = mysqli_query($conn, "SELECT id, name, slug FROM categories ORDER BY name ASC");
if ($categories_result):
    while ($category = mysqli_fetch_assoc($categories_result)):
        $initial_limit = 4;
        $stmt = mysqli_prepare($conn, "SELECT p.id, p.title, p.slug, p.summary, p.featured_image, p.created_at, p.view_count
                                       FROM posts p
                                       JOIN post_categories pc ON p.id = pc.post_id
                                       WHERE pc.category_id = ?
                                       ORDER BY p.created_at DESC
                                       LIMIT ?");
        if (!$stmt) continue;
        mysqli_stmt_bind_param($stmt, "ii", $category['id'], $initial_limit);
        mysqli_stmt_execute($stmt);
        $cat_posts_result = mysqli_stmt_get_result($stmt);
        if (!$cat_posts_result || mysqli_num_rows($cat_posts_result) === 0) {
            mysqli_stmt_close($stmt);
            continue;
        }
        $cat_posts = [];
        while ($r = mysqli_fetch_assoc($cat_posts_result)) $cat_posts[] = $r;
        mysqli_stmt_close($stmt);
?>

<section class="category-section mb-5" id="cat-section-<?php echo $category['id']; ?>">

    <!-- Section header -->
    <div class="section-header mb-4">
        <div class="section-header-left">
            <span class="section-accent-bar"></span>
            <h2 class="section-title">
                <a href="/express-news/category/<?php echo htmlspecialchars($category['slug']); ?>">
                    <?php echo htmlspecialchars($category['name']); ?>
                </a>
            </h2>
        </div>
        <a href="/express-news/category/<?php echo htmlspecialchars($category['slug']); ?>" class="section-view-all">
            View All
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
        </a>
    </div>

    <!-- Cards grid -->
    <div class="news-grid" id="post-container-<?php echo $category['id']; ?>">
        <?php foreach ($cat_posts as $i => $post):
            $img = getImagePaths($post['featured_image']);
            $is_featured = ($i === 0 && count($cat_posts) >= 3);
        ?>

        <?php if ($is_featured): ?>
        <!-- Featured (wide) card -->
        <div class="news-grid-featured">
            <div class="news-card news-card-featured">
                <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>" class="news-card-img-link">
                    <picture>
                        <source srcset="<?php echo $img['webp']; ?>" type="image/webp">
                        <source srcset="<?php echo $img['jpg']; ?>"  type="image/jpeg">
                        <img src="<?php echo $img['jpg']; ?>" class="news-card-img" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    </picture>
                    <span class="news-card-category-badge"><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
                <div class="news-card-body">
                    <span class="news-card-category-label"><?php echo htmlspecialchars($category['name']); ?></span>
                    <h3 class="news-card-title news-card-title-featured">
                        <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h3>
                    <p class="news-card-summary news-card-summary-featured">
                        <?php
                        $summary = !empty($post['summary'])
                            ? $post['summary']
                            : mb_substr(strip_tags($post['title']), 0, 160);
                        echo htmlspecialchars($summary);
                        ?>
                    </p>
                    <div class="news-card-meta">
                        <span class="news-card-date">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                            <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                        </span>
                        <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>" class="news-card-read-more">
                            Read More
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Regular card -->
        <div class="news-grid-item">
            <div class="news-card">
                <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>" class="news-card-img-link">
                    <picture>
                        <source srcset="<?php echo $img['webp']; ?>" type="image/webp">
                        <source srcset="<?php echo $img['jpg']; ?>"  type="image/jpeg">
                        <img src="<?php echo $img['jpg']; ?>" class="news-card-img" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    </picture>
                </a>
                <div class="news-card-body">
                    <h6 class="news-card-title">
                        <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h6>
                    <?php if (!empty($post['summary'])): ?>
                    <p class="news-card-summary">
                        <?php echo htmlspecialchars($post['summary']); ?>
                    </p>
                    <?php endif; ?>
                    <div class="news-card-meta">
                        <span class="news-card-date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                            <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                        </span>
                        <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>" class="news-card-read-more">
                            Read
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
    </div>

    <!-- Load More -->
    <div class="text-center mt-4">
        <button class="btn-load-more load-more-btn"
                data-category="<?php echo $category['id']; ?>"
                data-offset="<?php echo $initial_limit; ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
            Load More
        </button>
    </div>

</section>

<?php
    endwhile;
endif;
?>

<?php require_once 'includes/footer.php'; ?>
