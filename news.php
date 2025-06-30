<?php
/**
 * Raj News - Single Article Page (v11.0 - Pro Sharing & URLs)
 * This file displays a single news article with all its related data.
 */

// This page fetches its own data first, then includes the header.
// This allows page-specific variables like the title and meta tags to be set.
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/ads.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }


// The .htaccess file will now pass the slug via $_GET['slug']
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header("Location: /raj-news/");
    exit();
}

// --- FETCH MAIN POST DATA ---
$sql_post = "SELECT p.*, a.full_name as author_name, a.role as author_role 
             FROM posts p 
             JOIN admins a ON p.author_id = a.id 
             WHERE p.slug = ?";
$stmt_post = mysqli_prepare($conn, $sql_post);
mysqli_stmt_bind_param($stmt_post, "s", $slug);
mysqli_stmt_execute($stmt_post);
$result_post = mysqli_stmt_get_result($stmt_post);
$post = mysqli_fetch_assoc($result_post);
mysqli_stmt_close($stmt_post);

if (!$post) { 
    http_response_code(404);
    $page_title = "404 Not Found";
    require_once 'includes/header.php';
    echo "<div class='alert alert-danger'><h1>404 Not Found</h1><p>The article you are looking for does not exist.</p></div>";
    require_once 'includes/footer.php';
    exit();
}
$post_id = $post['id'];

//
// --- NEW: SEO & SOCIAL SHARING DATA PREPARATION ---
//
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain_name . '/raj-news/';

// 1. Canonical URL (the "true", pretty URL for this page)
$canonical_url = $base_url . "news/" . $post['slug'];

// 2. Page Title & Meta Description
$page_title = $post['title'];
$page_description = !empty($post['summary']) ? $post['summary'] : substr(strip_tags($post['content']), 0, 160);

// 3. Open Graph (for Facebook, WhatsApp, etc.) & Twitter Card Data
$og_data = [
    'type'        => 'article',
    'title'       => $page_title,
    'description' => $page_description,
    'url'         => $canonical_url,
    // Construct the absolute URL for the image, crucial for social scrapers
    'image'       => $protocol . $domain_name . getImagePaths($post['featured_image'])['jpg']
];

// 4. JSON-LD Structured Data for Google Rich Results
$json_ld_data = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical_url],
    'headline' => $page_title,
    'image' => [$og_data['image']],
    'datePublished' => date(DATE_ISO8601, strtotime($post['created_at'])),
    'dateModified' => date(DATE_ISO8601, strtotime($post['updated_at'])),
    'author' => ['@type' => 'Person', 'name' => $post['author_name']],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Raj News',
        'logo' => ['@type' => 'ImageObject', 'url' => $base_url . 'assets/images/logo.png'] // Make sure you have this logo image
    ],
    'description' => $page_description
];
// --- END SEO & SOCIAL SHARING DATA ---

// --- INCREMENT VIEW COUNT ---
$view_counted = false;
if (!isset($_SESSION['viewed_posts'][$post_id])) {
    mysqli_query($conn, "UPDATE posts SET view_count = view_count + 1 WHERE id = $post_id");
    $_SESSION['viewed_posts'][$post_id] = time();
    $view_counted = true;
}
if ($view_counted) $post['view_count']++; 


// --- FETCH CATEGORIES & TAGS FOR THIS POST ---
$post_categories = [];
$cat_sql = "SELECT c.id, c.name, c.slug FROM categories c JOIN post_categories pc ON c.id = pc.category_id WHERE pc.post_id = ?";
$cat_stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($cat_stmt, "i", $post_id);
mysqli_stmt_execute($cat_stmt);
$cat_result = mysqli_stmt_get_result($cat_stmt);
while($row = mysqli_fetch_assoc($cat_result)) { $post_categories[] = $row; }
mysqli_stmt_close($cat_stmt);

$post_tags = [];
$tag_sql = "SELECT t.name, t.slug FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?";
$tag_stmt = mysqli_prepare($conn, $tag_sql);
mysqli_stmt_bind_param($tag_stmt, "i", $post_id);
mysqli_stmt_execute($tag_stmt);
$tag_result = mysqli_stmt_get_result($tag_stmt);
while($row = mysqli_fetch_assoc($tag_result)) { $post_tags[] = $row; }
mysqli_stmt_close($tag_stmt);

// --- FETCH ALL SITE SETTINGS ---
$settings_res = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
$settings = array_column(mysqli_fetch_all($settings_res, MYSQLI_ASSOC), 'setting_value', 'setting_name');

// --- CHECK USER-SPECIFIC STATUS (LIKE/BOOKMARK) ---
$user_has_liked = false;
$user_has_bookmarked = false;
if (isset($_SESSION['user_loggedin'])) {
    $user_id = $_SESSION['user_id'];
    $like_check_stmt = mysqli_prepare($conn, "SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($like_check_stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($like_check_stmt);
    if(mysqli_stmt_fetch($like_check_stmt)) $user_has_liked = true;
    mysqli_stmt_close($like_check_stmt);
    
    $bookmark_check_stmt = mysqli_prepare($conn, "SELECT id FROM user_bookmarks WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($bookmark_check_stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($bookmark_check_stmt);
    if(mysqli_stmt_fetch($bookmark_check_stmt)) $user_has_bookmarked = true;
    mysqli_stmt_close($bookmark_check_stmt);
}

// --- COMMENT FORM SUBMISSION HANDLING ---
$comment_success = $comment_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    $name = isset($_SESSION['user_loggedin']) ? $_SESSION['user_username'] : trim($_POST['name']);
    $comment_text = trim($_POST['comment']);
    if (empty($name) || empty($comment_text)) {
        $comment_error = "Both name and comment fields are required.";
    } else {
        $insert_sql = "INSERT INTO comments (post_id, name, comment) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iss", $post_id, $name, $comment_text);
        if (mysqli_stmt_execute($insert_stmt)) {
            $comment_success = "Your comment has been submitted for approval. Thank you!";
        } else {
            $comment_error = "Sorry, something went wrong. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    }
}

// Now, require the header which will use all the variables we've just defined
require_once 'includes/header.php';
?>

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

<div class="row gx-lg-5">
    <div class="col-lg-8">
        <article>
            <header class="mb-4">
                <h1 class="fw-bolder mb-1"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="text-muted fst-italic mb-2">
                    Posted on <?php echo date('F d, Y', strtotime($post['created_at'])); ?> 
                    | By: <strong><?php echo htmlspecialchars($post['author_name']); ?></strong> 
                    <span class="author-role <?php echo str_replace('_', '-', htmlspecialchars($post['author_role'])); ?>">
                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($post['author_role']))); ?>
                    </span>
                    <?php if(isset($settings['show_view_count']) && $settings['show_view_count'] == '1'): ?>
                        <span class="ms-3"><i class="bi bi-eye"></i> <?php echo number_format($post['view_count']); ?> Views</span>
                    <?php endif; ?>
                </div>
                <div class="my-2">
                    <?php foreach($post_categories as $p_cat): ?>
                        <a class="badge bg-primary text-decoration-none link-light me-1" href="/raj-news/category/<?php echo $p_cat['slug']; ?>">
                            <?php echo htmlspecialchars($p_cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </header>
            
            <figure class="mb-4">
                <?php $image_paths = getImagePaths($post['featured_image']); ?>
                <picture>
                    <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                    <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                    <img src="<?php echo $image_paths['jpg']; ?>" class="img-fluid rounded shadow-sm" alt="<?php echo htmlspecialchars($post['title']); ?>">
                </picture>
            </figure>
            
            <section class="mb-5 article-content">
                <?php 
                    $content = $post['content'];
                    $paragraphs = explode('</p>', $content);
                    $insertion_point = 2;
                    if (count($paragraphs) > $insertion_point) {
                        for ($i = 0; $i < count($paragraphs); $i++) {
                            echo $paragraphs[$i] . ( (isset($paragraphs[$i+1]) && trim($paragraphs[$i+1]) != '') ? '</p>' : '' );
                            if ($i === $insertion_point - 1) {
                                echo '<div class="in-article-ad-zone-container my-4 py-3 border-top border-bottom text-center">';
                                display_ads_for_hook($conn, 'middle_of_article');
                                echo '</div>';
                            }
                        }
                    } else { 
                        echo $content;
                    }
                ?>
            </section>
        </article>

        <?php if (!empty($post_tags)): ?>
        <div class="tags-section mb-4 p-3 bg-light border rounded">
            <strong><i class="fa-solid fa-tags me-2"></i>Tags:</strong>
            <?php foreach($post_tags as $p_tag): ?>
                <a href="/raj-news/tag/<?php echo $p_tag['slug']; ?>" class="badge bg-secondary text-decoration-none link-light me-1">
                    <?php echo htmlspecialchars($p_tag['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="action-buttons-container d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded border">
            <button class="btn <?php echo $user_has_liked ? 'btn-danger' : 'btn-outline-danger'; ?> btn-icon" id="like-btn" data-post-id="<?php echo $post['id']; ?>" <?php if(!isset($_SESSION['user_loggedin'])) echo 'disabled title="Login to like"'; ?>>
                <i class="fa-solid fa-heart icon"></i> 
                <span><?php echo $user_has_liked ? 'Liked' : 'Like'; ?></span>
            </button>
            <?php if (isset($settings['show_like_count']) && $settings['show_like_count'] == '1'): ?>
                <span id="like-count" class="fw-bold"><?php echo number_format($post['likes_count']); ?> Likes</span>
            <?php endif; ?>

            <button class="btn <?php echo $user_has_bookmarked ? 'btn-primary' : 'btn-outline-primary'; ?> ms-auto btn-icon" id="bookmark-btn" data-post-id="<?php echo $post['id']; ?>" <?php if(!isset($_SESSION['user_loggedin'])) echo 'disabled title="Login to save"'; ?>>
                <i class="fa-solid fa-bookmark icon"></i> 
                <span><?php echo $user_has_bookmarked ? 'Saved' : 'Save for Later'; ?></span>
            </button>
        </div>
        
        <div class="social-share mb-4 p-3 bg-light rounded border">
            <h5 class="mb-2">Share this article:</h5>
            <?php 
        // Use the short, clean ID-based URL for sharing
        $short_share_url = $base_url . "article/" . $post['id'];
        $page_url_encoded = urlencode($short_share_url); 
        $title_encoded = urlencode($post['title']);
    ?>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $page_url_encoded; ?>" target="_blank" class="btn btn-outline-primary btn-sm">Facebook</a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo $page_url_encoded; ?>&text=<?php echo $title_encoded; ?>" target="_blank" class="btn btn-outline-info btn-sm">Twitter</a>
            <a href="https://api.whatsapp.com/send?text=<?php echo $title_encoded . ' ' . $page_url_encoded; ?>" target="_blank" class="btn btn-outline-success btn-sm">WhatsApp</a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $page_url_encoded; ?>&title=<?php echo $title_encoded; ?>" target="_blank" class="btn btn-outline-primary btn-sm">LinkedIn</a>
        </div>

        <?php if (isset($settings['allow_comments']) && $settings['allow_comments'] == '1'): ?>
        <section class="mb-5 comments-section">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title mb-4">Leave a Comment</h5>
                    <?php if($comment_success): ?><div class="alert alert-success"><?php echo $comment_success; ?></div><?php endif; ?>
                    <?php if($comment_error): ?><div class="alert alert-danger"><?php echo $comment_error; ?></div><?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name</label>
                            <?php if (isset($_SESSION['user_loggedin'])): ?>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['user_username']); ?>" readonly>
                            <?php else: ?>
                                <input type="text" class="form-control" id="name" name="name" required>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Comment</label>
                            <textarea class="form-control" name="comment" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-primary">Submit Comment</button>
                    </form>
                    <hr class="my-4">
                    
                    <?php
                    $comment_sql = "SELECT c.name, c.comment, c.created_at, u.avatar FROM comments c LEFT JOIN users u ON c.name = u.username WHERE c.post_id = ? AND c.is_approved = 1 ORDER BY c.created_at DESC";
                    $comment_stmt = mysqli_prepare($conn, $comment_sql);
                    mysqli_stmt_bind_param($comment_stmt, "i", $post_id);
                    mysqli_stmt_execute($comment_stmt);
                    $comments_result = mysqli_stmt_get_result($comment_stmt);
                    
                    if (mysqli_num_rows($comments_result) > 0) {
                        while($comment = mysqli_fetch_assoc($comments_result)) {
                            $avatar_path = 'https://dummyimage.com/50x50/ced4da/6c757d.jpg';
                            if (!empty($comment['avatar'])) {
                                $avatar_path = '/raj-news/' . htmlspecialchars($comment['avatar']);
                            }
                    ?>
                    <div class="d-flex mb-3 comment">
                        <div class="flex-shrink-0">
                            <img class="rounded-circle comment-avatar" loading="lazy" src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($comment['name']); ?> Avatar" />
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <div class="comment-author"><?php echo htmlspecialchars($comment['name']); ?></div>
                            <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                            <div class="comment-date"><?php echo date('F j, Y, g:i a', strtotime($comment['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php } } else { echo "<p>Be the first to comment!</p>"; }
                    mysqli_stmt_close($comment_stmt); ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="after-article-ad-hook-container my-4 text-center">
            <?php display_ads_for_hook($conn, 'after_article_content'); ?>
        </div>
    </div>

    <!-- ===== SIDEBAR ===== -->
    <div class="col-lg-4">
        <div class="sidebar-ad-hook-container mb-4 text-center">
            <?php display_ads_for_hook($conn, 'sidebar_top'); ?>
        </div>
        
        <div class="card mb-4 trending-widget">
            <div class="card-header"><i class="fa-solid fa-fire-flame-curved icon"></i> Trending This Week</div>
            <div class="card-body">
                <ol class="list-unstyled mb-0">
                    <?php
                    $trending_sql = "SELECT title, slug, view_count FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY view_count DESC, created_at DESC LIMIT 5";
                    $trending_result = mysqli_query($conn, $trending_sql);
                    if($trending_result && mysqli_num_rows($trending_result) > 0) {
                        $rank = 1;
                        while($trending_post = mysqli_fetch_assoc($trending_result)) {
                    ?>
                    <li class="d-flex align-items-start mb-3">
                        <span class="trending-rank"><?php echo $rank++; ?></span>
                        <div>
                            <a href="/raj-news/news/<?php echo $trending_post['slug']; ?>" class="trending-title"><?php echo htmlspecialchars($trending_post['title']); ?></a>
                            <?php if(isset($settings['show_view_count']) && $settings['show_view_count'] == '1'): ?>
                                <div class="trending-views text-muted small"><?php echo number_format($trending_post['view_count']); ?> Views</div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php } } else { echo "<li><p class='text-muted'>No trending articles this week.</p></li>"; } ?>
                </ol>
            </div>
        </div>
        
        <div class="card mb-4 related-articles">
            <div class="card-header"><i class="fa-solid fa-layer-group icon"></i> Related Articles</div>
            <div class="card-body">
                <?php
                $related_posts = [];
                if (!empty($post_categories)) {
                    $category_ids_for_query = array_column($post_categories, 'id');
                    $placeholders = implode(',', array_fill(0, count($category_ids_for_query), '?'));
                    
                    $related_query = "SELECT DISTINCT p.title, p.slug, p.summary, p.featured_image
                                      FROM posts p
                                      JOIN post_categories pc ON p.id = pc.post_id
                                      WHERE pc.category_id IN ($placeholders) AND p.id != ?
                                      ORDER BY RAND() 
                                      LIMIT 3";
                    
                    $related_stmt = mysqli_prepare($conn, $related_query);
                    $types = str_repeat('i', count($category_ids_for_query)) . 'i';
                    $bind_params = array_merge($category_ids_for_query, [$post_id]);
                    mysqli_stmt_bind_param($related_stmt, $types, ...$bind_params);
                    mysqli_stmt_execute($related_stmt);
                    $related_result = mysqli_stmt_get_result($related_stmt);
                    while($row = mysqli_fetch_assoc($related_result)) {
                        $related_posts[] = $row;
                    }
                    mysqli_stmt_close($related_stmt);
                }

                if (!empty($related_posts)) {
                    foreach($related_posts as $related_post) {
                        $related_image_paths = getImagePaths($related_post['featured_image']);
                ?>
                <a href="/raj-news/news/<?php echo $related_post['slug']; ?>" class="related-article-card mb-3">
                    <picture>
                        <source srcset="<?php echo $related_image_paths['webp']; ?>" type="image/webp">
                        <source srcset="<?php echo $related_image_paths['jpg']; ?>" type="image/jpeg">
                        <img src="<?php echo $related_image_paths['jpg']; ?>" class="related-article-img" loading="lazy" alt="<?php echo htmlspecialchars($related_post['title']); ?>">
                    </picture>
                    <div class="card-body">
                        <h6 class="card-title"><?php echo htmlspecialchars($related_post['title']); ?></h6>
                        <p class="card-text"><?php echo htmlspecialchars($related_post['summary']); ?></p>
                    </div>
                </a>
                <?php
                    }
                } else {
                    echo "<p class='text-muted'>No related articles found.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once 'includes/footer.php'; 
?>