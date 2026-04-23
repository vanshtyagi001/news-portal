<?php
/**
 * Express News - AJAX Load More Handler
 * Returns new card HTML matching the homepage news-grid-item structure.
 */
require_once 'admin/includes/db.php';

$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$offset      = isset($_POST['offset'])      ? (int)$_POST['offset']      : 0;
$limit       = 4;

if ($category_id > 0 && $offset >= 0) {
    $sql  = "SELECT p.id, p.title, p.slug, p.summary, p.featured_image, p.created_at
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
        while ($post = mysqli_fetch_assoc($result)) {
            $img = getImagePaths($post['featured_image']);
            ?>
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
            <p class="news-card-summary"><?php echo htmlspecialchars($post['summary']); ?></p>
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
            <?php
        }
    } else {
        echo 'no-more';
    }
    mysqli_stmt_close($stmt);
} else {
    echo 'no-more';
}
mysqli_close($conn);
?>
