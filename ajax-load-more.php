<?php
/**
 * Raj News - AJAX Load More Handler (v9.2 - Final Image Fix)
 * This script is called by the "Load More" button on the homepage.
 * It fetches the next set of articles for a given category and returns the HTML.
 */

require_once 'admin/includes/db.php'; // This makes our getImagePaths() function available

// Sanitize inputs
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit = 4; // The number of posts to load each time, should match index.php

if ($category_id > 0 && $offset >= 0) {

    // This query is already correct from our previous fix.
    $sql = "SELECT p.title, p.slug, p.featured_image
            FROM posts p
            JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id = ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $category_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        // Loop through the results and build the HTML for each post card
        while ($post = mysqli_fetch_assoc($result)) {
            //
            // --- THE CRITICAL FIX IS HERE ---
            // We now use our smart helper function and the <picture> element.
            //
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
            <?php
        }
    } else {
        // No more posts to load for this category
        echo 'no-more';
    }
    mysqli_stmt_close($stmt);
} else {
    echo 'no-more';
}

mysqli_close($conn);
?>