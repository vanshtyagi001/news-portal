<?php
require_once 'includes/header.php'; // This includes db.php and session start

// Ensure user is logged in
if (!isset($_SESSION['admin_loggedin'])) {
    header("location: index.php");
    exit;
}

$errors = [];
// Initialize form variables to avoid "undefined" notices on page load
$title = $summary = '';
$category_ids = [];
$tags_input = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and get all form data
    $title = trim($_POST['title']);
    $summary = trim($_POST['summary']);
    // The content comes from the hidden textarea populated by the Velion editor
    $content = $_POST['content']; 
    $author_id = $_SESSION['admin_id'];
    $category_ids = $_POST['category_ids'] ?? [];
    $tags_input = trim($_POST['tags_input'] ?? '');

    // --- Validation ---
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($content)) $errors[] = "Content is required.";
    if (empty($category_ids)) $errors[] = "At least one category is required.";
    if (!isset($_FILES['featured_image']) || $_FILES['featured_image']['error'] != 0) {
        $errors[] = "A featured image is required.";
    }

    // --- Image Processing using your existing function ---
    $featured_image_base = "";
    if (empty($errors)) {
        $target_dir = "../uploads/";
        // Generate a unique base filename WITHOUT extension
        $unique_basename = "post_" . time() . '_' . uniqid();
        $destination_path_no_ext = $target_dir . $unique_basename;

        // The function now returns the base filename on success
        $optimized_basename = optimize_image($_FILES["featured_image"]["tmp_name"], $destination_path_no_ext);

        if ($optimized_basename) {
            $featured_image_base = $optimized_basename;
        } else {
            $errors[] = "Sorry, there was an error processing your image. It might be an unsupported file type or a permission issue in the /uploads/ folder.";
        }
    }

    // --- Database Insertion with Transaction ---
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $slug = create_slug($title);
            // Insert the base filename (e.g., "post_12345") into the database
            $sql_post = "INSERT INTO posts (title, slug, summary, content, featured_image, author_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_post = mysqli_prepare($conn, $sql_post);
            mysqli_stmt_bind_param($stmt_post, "sssssi", $title, $slug, $summary, $content, $featured_image_base, $author_id);
            mysqli_stmt_execute($stmt_post);
            $post_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_post);

            if (!$post_id) {
                throw new Exception("Failed to create the post. Post ID was zero.");
            }

            // Insert categories
            if (!empty($category_ids)) {
                $sql_cat = "INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)";
                $stmt_cat = mysqli_prepare($conn, $sql_cat);
                foreach ($category_ids as $cat_id) {
                    $current_cat_id = (int)$cat_id;
                    mysqli_stmt_bind_param($stmt_cat, "ii", $post_id, $current_cat_id);
                    mysqli_stmt_execute($stmt_cat);
                }
                mysqli_stmt_close($stmt_cat);
            }
            
            // Handle Tags
            if (!empty($tags_input)) {
                $tags_array = array_unique(array_map('trim', explode(',', $tags_input)));
                $stmt_select_tag = mysqli_prepare($conn, "SELECT id FROM tags WHERE slug = ?");
                $stmt_insert_tag = mysqli_prepare($conn, "INSERT INTO tags (name, slug) VALUES (?, ?)");
                $stmt_post_tag = mysqli_prepare($conn, "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                foreach($tags_array as $tag_name) {
                    if (empty($tag_name)) continue;
                    $tag_slug = create_slug($tag_name);
                    $tag_id = null;
                    mysqli_stmt_bind_param($stmt_select_tag, "s", $tag_slug);
                    mysqli_stmt_execute($stmt_select_tag);
                    $result_tag = mysqli_stmt_get_result($stmt_select_tag);
                    if ($row = mysqli_fetch_assoc($result_tag)) {
                        $tag_id = $row['id'];
                    } else {
                        mysqli_stmt_bind_param($stmt_insert_tag, "ss", $tag_name, $tag_slug);
                        mysqli_stmt_execute($stmt_insert_tag);
                        $tag_id = mysqli_insert_id($conn);
                    }
                    if ($tag_id) {
                        mysqli_stmt_bind_param($stmt_post_tag, "ii", $post_id, $tag_id);
                        mysqli_stmt_execute($stmt_post_tag);
                    }
                }
                mysqli_stmt_close($stmt_select_tag);
                mysqli_stmt_close($stmt_insert_tag);
                mysqli_stmt_close($stmt_post_tag);
            }
            
            mysqli_commit($conn);
            header("location: manage-news.php?status=added");
            exit();

        } catch (Exception $exception) {
            mysqli_rollback($conn);
            $errors[] = "Database transaction failed: " . $exception->getMessage();
        }
    }
}
?>

<div class="content-header">
    <h2>Add New Article</h2>
    <a href="manage-news.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left icon"></i> Back to All News</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo $error; ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="add-news.php" method="post" enctype="multipart/form-data" novalidate>
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>
            <div class="form-group">
                <label for="summary">Summary (Optional)</label>
                <textarea name="summary" id="summary" class="form-control" rows="3"><?php echo htmlspecialchars($summary); ?></textarea>
            </div>

            <?php
            // Include the reusable editor component.
            // Since we don't define $editor_content, it will show the default placeholder.
            include 'editor-component.php'; 
            ?>

            <div class="form-group">
                <label for="category_ids">Categories (select one or more)</label>
                <select name="category_ids[]" id="category_ids" class="form-control" multiple required>
                    <?php
                    $cat_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
                    while ($cat = mysqli_fetch_assoc($cat_result)) {
                        // Pre-select categories if there was a form submission error
                        $selected = in_array($cat['id'], $category_ids) ? 'selected' : '';
                        echo "<option value='{$cat['id']}' $selected>".htmlspecialchars($cat['name'])."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tags_input">Tags (comma-separated)</label>
                <input type="text" name="tags_input" id="tags_input" class="form-control" value="<?php echo htmlspecialchars($tags_input); ?>" placeholder="e.g., tech, AI, startups">
            </div>
            <div class="form-group">
                <label for="featured_image">Featured Image (Required)</label>
                <input type="file" name="featured_image" id="featured_image" class="form-control" required>
            </div>
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Publish Article</button>
            </div>
        </form>
    </div>
</div>

<script>
// This script block is for initializing the Choices.js library for the category/tag inputs.
document.addEventListener('DOMContentLoaded', function() {
    const categoriesElement = document.getElementById('category_ids');
    if (categoriesElement) {
        new Choices(categoriesElement, {
            removeItemButton: true, placeholder: true, placeholderValue: 'Select categories...', searchPlaceholderValue: 'Search for categories',
        });
    }
    const tagsElement = document.getElementById('tags_input');
    if (tagsElement) {
        new Choices(tagsElement, {
            delimiter: ',', editItems: true, removeItemButton: true, placeholder: true, placeholderValue: 'Add tags and press enter',
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>