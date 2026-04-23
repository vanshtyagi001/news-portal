<?php
/**
 * Express News - Search Results Page (v11.2 - Pathing Fix)
 * This page displays a paginated list of posts matching a search query.
 */

//
// --- THE CRITICAL FIX IS HERE ---
// The path should be 'includes/header.php', not '../includes/header.php'
// because search.php is in the same root directory as the 'includes' folder.
//
require_once 'includes/header.php';

// Get and sanitize the search query from the URL.
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Set a dynamic page title
$page_title = 'Search Results for "' . htmlspecialchars($query) . '"';

// Dynamically update the page title using JavaScript because the header is already loaded.
echo "<script>document.title = '" . addslashes($page_title) . " - Express News';</script>";
?>

<div class="row">
    <div class="col-lg-12">
        <div class="page-header mb-4">
            <div class="section-header">
                <div class="section-header-left">
                    <span class="section-accent-bar"></span>
                    <h1 class="section-title">Search Results</h1>
                </div>
            </div>
        </div>

        <?php
        $posts_to_display = [];
        $total_results = 0;

        if (!empty($query)) {
            $search_term = '%' . $query . '%';

            // --- Setup Pagination ---
            $posts_per_page = 9;
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($current_page < 1) { $current_page = 1; }
            $offset = ($current_page - 1) * $posts_per_page;

            // --- Count total matching results for pagination ---
            $count_sql = "SELECT COUNT(*) as total FROM posts WHERE title LIKE ? OR content LIKE ?";
            $stmt_count = mysqli_prepare($conn, $count_sql);
            mysqli_stmt_bind_param($stmt_count, "ss", $search_term, $search_term);
            mysqli_stmt_execute($stmt_count);
            $count_result = mysqli_stmt_get_result($stmt_count);
            $total_results = mysqli_fetch_assoc($count_result)['total'];
            $total_pages = ceil($total_results / $posts_per_page);
            mysqli_stmt_close($stmt_count);
            
            // --- Fetch the posts for the current page ---
            $sql = "SELECT p.*,
                           (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                            FROM categories c 
                            JOIN post_categories pc ON c.id = pc.category_id 
                            WHERE pc.post_id = p.id) as category_names
                    FROM posts p
                    WHERE p.title LIKE ? OR p.content LIKE ?
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt_posts = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt_posts, "ssii", $search_term, $search_term, $posts_per_page, $offset);
            mysqli_stmt_execute($stmt_posts);
            $result_posts = mysqli_stmt_get_result($stmt_posts);
            while ($row = mysqli_fetch_assoc($result_posts)) {
                $posts_to_display[] = $row;
            }
            mysqli_stmt_close($stmt_posts);
        }
        ?>

        <p class="text-muted mb-4"><?php echo $total_results; ?> article(s) found for "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>

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
                                <?php if (!empty($post['category_names'])): ?>
                                    <span class="news-card-category-label"><?php echo htmlspecialchars($post['category_names']); ?></span>
                                <?php endif; ?>
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
            <?php elseif(!empty($query)): ?>
                <div class="col-12">
                    <div class="alert alert-warning">No articles matched your search. Please try different keywords.</div>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">Please enter a search term to find articles.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination Links -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?query=<?php echo urlencode($query); ?>&page=<?php echo $current_page - 1; ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>"><a class="page-link" href="?query=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?query=<?php echo urlencode($query); ?>&page=<?php echo $current_page + 1; ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the footer
require_once 'includes/footer.php';
?>