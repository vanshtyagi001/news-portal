<?php
/**
 * Raj News - Search Results Page (v11.2 - Pathing Fix)
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
echo "<script>document.title = '" . addslashes($page_title) . " - Raj News';</script>";
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="mb-4 border-bottom pb-2">Search Results for: "<?php echo htmlspecialchars($query); ?>"</h1>

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

        <p class="text-muted"><?php echo $total_results; ?> article(s) found.</p>

        <div class="row">
            <?php if (!empty($posts_to_display)): ?>
                <?php foreach ($posts_to_display as $post): ?>
                    <?php
                        // Use our helper function to get the correct image paths
                        $image_paths = getImagePaths($post['featured_image']);
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm news-card">
                            <a href="/raj-news/news/<?php echo $post['slug']; ?>">
                                <picture>
                                    <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                                    <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                                    <img src="<?php echo $image_paths['jpg']; ?>" class="card-img-top" loading="lazy" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                </picture>
                            </a>
                            <div class="card-body d-flex flex-column">
                                <?php if(!empty($post['category_names'])): ?>
                                    <p class="card-text text-muted small mb-1"><?php echo htmlspecialchars($post['category_names']); ?></p>
                                <?php endif; ?>
                                <h5 class="card-title"><a href="/raj-news/news/<?php echo $post['slug']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h5>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars($post['summary']); ?></p>
                                <a href="/raj-news/news/<?php echo $post['slug']; ?>" class="btn btn-primary align-self-start mt-auto">Read More</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif(!empty($query)): ?>
                <div class="col-12">
                    <div class="alert alert-warning">Sorry, no articles matched your search query. Please try different keywords.</div>
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