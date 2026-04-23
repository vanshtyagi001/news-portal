<?php
/**
 * Express News - Single Article Page (v11.0 - Pro Sharing & URLs)
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
    header("Location: /express-news/");
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
$base_url = $protocol . $domain_name . '/express-news/';

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
        'name' => 'Express News',
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

    // Use get_result() for reliable row detection
    $like_check_stmt = mysqli_prepare($conn, "SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($like_check_stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($like_check_stmt);
    $like_result = mysqli_stmt_get_result($like_check_stmt);
    if ($like_result && mysqli_num_rows($like_result) > 0) $user_has_liked = true;
    mysqli_stmt_close($like_check_stmt);

    $bookmark_check_stmt = mysqli_prepare($conn, "SELECT id FROM user_bookmarks WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($bookmark_check_stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($bookmark_check_stmt);
    $bookmark_result = mysqli_stmt_get_result($bookmark_check_stmt);
    if ($bookmark_result && mysqli_num_rows($bookmark_result) > 0) $user_has_bookmarked = true;
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
                    $post_url = "/express-news/news/" . htmlspecialchars($ticker_post['slug']);
                    // Output the link for the article
                    echo '<a href="' . $post_url . '">' . htmlspecialchars($ticker_post['title']) . '</a>';
                    // Add a separator for visual distinction between headlines
                    echo '<span class="separator">•</span>';
                }
            } else {
                // Display a fallback message if there are no news articles yet
                echo '<span>Welcome to Express News! More headlines coming soon.</span>';
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
                        <a class="badge bg-primary text-decoration-none link-light me-1" href="/express-news/category/<?php echo $p_cat['slug']; ?>">
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
        <div class="tags-section mb-4 p-3 rounded">
            <strong class="tags-label"><i class="fa-solid fa-tags me-2"></i>Tags:</strong>
            <?php foreach($post_tags as $p_tag): ?>
                <a href="/express-news/tag/<?php echo $p_tag['slug']; ?>" class="tag-badge text-decoration-none me-1">
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
        
        <?php 
            $short_share_url = $base_url . "article/" . $post['id'];
            $page_url_encoded = urlencode($short_share_url); 
            $title_encoded    = urlencode($post['title']);
        ?>
        <div class="social-share mb-4 p-3 rounded">
            <h5 class="social-share-title mb-3">
                <i class="fa-solid fa-share-nodes me-2"></i>Share this article
            </h5>
            <div class="social-share-buttons">

                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $page_url_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-facebook" title="Share on Facebook">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
                    </svg>
                    <span>Facebook</span>
                </a>

                <!-- X / Twitter -->
                <a href="https://twitter.com/intent/tweet?url=<?php echo $page_url_encoded; ?>&text=<?php echo $title_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-twitter" title="Share on X (Twitter)">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    <span>Twitter</span>
                </a>

                <!-- WhatsApp -->
                <a href="https://api.whatsapp.com/send?text=<?php echo $title_encoded . '%20' . $page_url_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-whatsapp" title="Share on WhatsApp">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span>WhatsApp</span>
                </a>

                <!-- LinkedIn -->
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $page_url_encoded; ?>&title=<?php echo $title_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-linkedin" title="Share on LinkedIn">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                    <span>LinkedIn</span>
                </a>

                <!-- Telegram -->
                <a href="https://t.me/share/url?url=<?php echo $page_url_encoded; ?>&text=<?php echo $title_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-telegram" title="Share on Telegram">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    <span>Telegram</span>
                </a>

                <!-- Reddit -->
                <a href="https://www.reddit.com/submit?url=<?php echo $page_url_encoded; ?>&title=<?php echo $title_encoded; ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn-reddit" title="Share on Reddit">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                    </svg>
                    <span>Reddit</span>
                </a>

                <!-- Email -->
                <a href="mailto:?subject=<?php echo $title_encoded; ?>&body=<?php echo urlencode("I thought you'd find this interesting: " . $short_share_url); ?>"
                   class="share-btn share-btn-email" title="Share via Email">
                    <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span>Email</span>
                </a>

                <!-- Copy Link -->
                <button type="button" class="share-btn share-btn-copy" id="copyLinkBtn"
                        data-url="<?php echo htmlspecialchars($short_share_url); ?>" title="Copy link to clipboard">
                    <svg class="share-icon" id="copyIcon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                    <svg class="share-icon" id="checkIcon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    <span id="copyBtnText">Copy Link</span>
                </button>

            </div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('copyLinkBtn');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var url = btn.getAttribute('data-url');
                navigator.clipboard.writeText(url).then(function() {
                    document.getElementById('copyIcon').style.display  = 'none';
                    document.getElementById('checkIcon').style.display = '';
                    document.getElementById('copyBtnText').textContent = 'Copied!';
                    btn.classList.add('share-btn-copied');
                    setTimeout(function() {
                        document.getElementById('copyIcon').style.display  = '';
                        document.getElementById('checkIcon').style.display = 'none';
                        document.getElementById('copyBtnText').textContent = 'Copy Link';
                        btn.classList.remove('share-btn-copied');
                    }, 2500);
                }).catch(function() {
                    /* fallback for older browsers */
                    var ta = document.createElement('textarea');
                    ta.value = url;
                    ta.style.position = 'fixed';
                    ta.style.opacity  = '0';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    document.getElementById('copyBtnText').textContent = 'Copied!';
                    setTimeout(function() {
                        document.getElementById('copyBtnText').textContent = 'Copy Link';
                    }, 2500);
                });
            });
        })();
        </script>

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
                                $avatar_path = '/express-news/' . htmlspecialchars($comment['avatar']);
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
                            <a href="/express-news/news/<?php echo $trending_post['slug']; ?>" class="trending-title"><?php echo htmlspecialchars($trending_post['title']); ?></a>
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
                <a href="/express-news/news/<?php echo $related_post['slug']; ?>" class="related-article-card mb-3">
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
require_once 'includes/footer.php';
mysqli_close($conn);
?>