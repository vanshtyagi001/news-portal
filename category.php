<?php
/**
 * Raj News - Category Archive Page (v9.2 - Final Image Fix)
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
echo "<script>document.title = 'News in " . addslashes(htmlspecialchars($category['name'])) . " - Raj News';</script>";

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
<h1 class="mb-4 border-bottom pb-2">News in: <?php echo htmlspecialchars($category['name']); ?></h1>

<div class="row">
    <?php if (!empty($posts_to_display)): ?>
        <?php foreach ($posts_to_display as $post): ?>
            <?php
                // Use our new helper function to get the correct image paths
                $image_paths = getImagePaths($post['featured_image']);
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm news-card">
                    <a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>">
                        <picture>
                            <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                            <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                            <img src="<?php echo $image_paths['jpg']; ?>" class="card-img-top" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        </picture>
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h5>
                        <p class="card-text text-muted small mt-auto mb-2"><?php echo date('F d, Y', strtotime($post['created_at'])); ?></p>
                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($post['summary']); ?></p>
                        <a href="/raj-news/news.php?slug=<?php echo $post['slug']; ?>" class="btn btn-primary align-self-start">Read More</a>
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