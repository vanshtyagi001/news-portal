<?php
/**
 * Express News - Category Archive Page (v9.2 - Final Image Fix)
 * This page displays a paginated list of all articles within a specific category.
 */

// Include the header. It handles the DB connection, session start, and main navigation.
require_once 'includes/header.php';

// Get and validate the category slug from the URL. The $conn variable is now available.
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    echo "<div class='alert alert-danger'><h1>400 Bad Request</h1><p>No category specified.</p></div>";
    require_once 'includes/footer.php';
    exit();
}

// Fetch category details using the available $conn.
$sql_category = "SELECT * FROM categories WHERE slug = ?";
$stmt_category = mysqli_prepare($conn, $sql_category);
mysqli_stmt_bind_param($stmt_category, "s", $slug);
mysqli_stmt_execute($stmt_category);
$result_category = mysqli_stmt_get_result($stmt_category);
$category = mysqli_fetch_assoc($result_category);
mysqli_stmt_close($stmt_category);

// Handle "Category Not Found".
if (!$category) {
    http_response_code(404);
    echo "<div class='alert alert-danger'><h1>404 Not Found</h1><p>The category '<strong>" . htmlspecialchars($slug) . "</strong>' does not exist.</p></div>";
    require_once 'includes/footer.php';
    exit();
}

// Dynamically update the page title using JavaScript because the header is already loaded.
echo "<script>document.title = 'News in " . addslashes(htmlspecialchars($category['name'])) . " - Express News';</script>";

// --- Setup Pagination ---
$posts_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $posts_per_page;

// Count total posts in this category for pagination.
$count_sql = "SELECT COUNT(p.id) as total FROM posts p JOIN post_categories pc ON p.id = pc.post_id WHERE pc.category_id = ?";
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, "i", $category['id']);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_posts = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_posts / $posts_per_page);
mysqli_stmt_close($stmt_count);

// --- Fetch the posts for the current page ---
$posts_to_display = [];
$post_sql = "SELECT p.id, p.title, p.slug, p.summary, p.featured_image, p.created_at 
             FROM posts p
             JOIN post_categories pc ON p.id = pc.post_id
             WHERE pc.category_id = ? 
             ORDER BY p.created_at DESC 
             LIMIT ? OFFSET ?";
$stmt_posts = mysqli_prepare($conn, $post_sql);
mysqli_stmt_bind_param($stmt_posts, "iii", $category['id'], $posts_per_page, $offset);
mysqli_stmt_execute($stmt_posts);
$result_posts = mysqli_stmt_get_result($stmt_posts);
while ($row = mysqli_fetch_assoc($result_posts)) {
    $posts_to_display[] = $row;
}
mysqli_stmt_close($stmt_posts);
?>

<!-- Main page content -->
<div class="page-header mb-5">
    <div class="section-header">
        <div class="section-header-left">
            <span class="section-accent-bar"></span>
            <h1 class="section-title"><?php echo htmlspecialchars($category['name']); ?></h1>
        </div>
        <span class="text-muted small"><?php echo $total_posts; ?> articles</span>
    </div>
</div>

<div class="row g-4">
    <?php if (!empty($posts_to_display)): ?>
        <?php foreach ($posts_to_display as $post): ?>
            <?php $image_paths = getImagePaths($post['featured_image']); ?>
            <div class="col-md-6 col-lg-4">
                <div class="news-card h-100">
                    <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>" class="news-card-img-link">
                        <picture>
                            <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                            <source srcset="<?php echo $image_paths['jpg']; ?>"  type="image/jpeg">
                            <img src="<?php echo $image_paths['jpg']; ?>" class="news-card-img" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        </picture>
                    </a>
                    <div class="news-card-body">
                        <h5 class="news-card-title">
                            <a href="/express-news/news/<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                        </h5>
                        <?php if (!empty($post['summary'])): ?>
                        <p class="news-card-summary"><?php echo htmlspecialchars($post['summary']); ?></p>
                        <?php endif; ?>
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
        <?php endforeach; ?>
    <?php else: ?>
        <div class='col'><p class='alert alert-warning'>No articles have been published in this category yet.</p></div>
    <?php endif; ?>
</div>

<!-- Pagination Links -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($current_page > 1): ?>
                <li class="page-item"><a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $current_page - 1; ?>">Previous</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>"><a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $current_page + 1; ?>">Next</a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php
// Include the footer
require_once 'includes/footer.php';
?>