<?php
require_once 'includes/header.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) { /* ... handle error ... */ exit(); }

// Fetch tag details
$tag_sql = "SELECT * FROM tags WHERE slug = ?";
$tag_stmt = mysqli_prepare($conn, $tag_sql);
mysqli_stmt_bind_param($tag_stmt, "s", $slug);
mysqli_stmt_execute($tag_stmt);
$tag = mysqli_fetch_assoc(mysqli_stmt_get_result($tag_stmt));
mysqli_stmt_close($tag_stmt);

if (!$tag) { /* ... 404 error handling ... */ exit(); }

// Fetch posts for this tag
$posts = [];
$post_sql = "SELECT p.* FROM posts p JOIN post_tags pt ON p.id = pt.post_id WHERE pt.tag_id = ? ORDER BY p.created_at DESC";
$post_stmt = mysqli_prepare($conn, $post_sql);
mysqli_stmt_bind_param($post_stmt, "i", $tag['id']);
mysqli_stmt_execute($post_stmt);
$post_result = mysqli_stmt_get_result($post_stmt);
while($row = mysqli_fetch_assoc($post_result)) { $posts[] = $row; }
mysqli_stmt_close($post_stmt);
?>

<h1 class="mb-4 border-bottom pb-2">Posts tagged with: "<?php echo htmlspecialchars($tag['name']); ?>"</h1>

<div class="row">
    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
            <!-- ... (Loop and display post cards, same as category.php) ... -->
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alert alert-warning">No articles found with this tag.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>