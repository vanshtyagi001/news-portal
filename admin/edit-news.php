<?php
require_once 'includes/header.php';

// Role check and ID validation
if (!isset($_SESSION['admin_loggedin'])) { header("location: index.php"); exit; }
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($post_id == 0) { header("location: manage-news.php"); exit(); }

// --- Fetch existing post data ---
$stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$post) { header("location: manage-news.php"); exit(); }

// --- Permission Check ---
if ($_SESSION['admin_role'] == 'author' && $post['author_id'] != $_SESSION['admin_id']) {
    header("location: manage-news.php?error=permission_denied"); exit();
}

// Fetch current categories for this post
$current_category_ids = [];
$cat_stmt = mysqli_prepare($conn, "SELECT category_id FROM post_categories WHERE post_id = ?");
mysqli_stmt_bind_param($cat_stmt, "i", $post_id);
mysqli_stmt_execute($cat_stmt);
$cat_result = mysqli_stmt_get_result($cat_stmt);
while ($row = mysqli_fetch_assoc($cat_result)) { $current_category_ids[] = $row['category_id']; }
mysqli_stmt_close($cat_stmt);

// Fetch current tags for this post
$current_tags = [];
$tag_stmt = mysqli_prepare($conn, "SELECT t.name FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?");
mysqli_stmt_bind_param($tag_stmt, "i", $post_id);
mysqli_stmt_execute($tag_stmt);
$tag_result = mysqli_stmt_get_result($tag_stmt);
while ($row = mysqli_fetch_assoc($tag_result)) { $current_tags[] = $row['name']; }
mysqli_stmt_close($tag_stmt);

// Initialize form variables for pre-filling
$errors = [];
$title = $post['title'];
$summary = $post['summary'];
$category_ids = $current_category_ids; // For pre-selecting in Choices.js
$tags_input = implode(', ', $current_tags);
$current_image_base = $post['featured_image'];

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = trim($_POST['title']);
    $summary = trim($_POST['summary']);
    // The content will come from the hidden textarea populated by the Velion editor
    $content = $_POST['content'];
    $new_category_ids = $_POST['category_ids'] ?? [];
    $new_tags_input = trim($_POST['tags_input'] ?? '');
    
    // Validation
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($new_category_ids)) $errors[] = "At least one category is required.";
    
    // Image handling
    $featured_image_base = $current_image_base;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
        $target_dir = "../uploads/";
        $unique_basename = "post_" . time() . '_' . uniqid();
        $destination_path_no_ext = $target_dir . $unique_basename;
        
        $optimized_basename = optimize_image($_FILES["featured_image"]["tmp_name"], $destination_path_no_ext);
        if ($optimized_basename) {
            // New image was uploaded and optimized successfully
            // Delete old image files if they exist
            if (!empty($current_image_base)) {
                if(file_exists($target_dir . $current_image_base . '.jpg')) unlink($target_dir . $current_image_base . '.jpg');
                if(file_exists($target_dir . $current_image_base . '.webp')) unlink($target_dir . $current_image_base . '.webp');
            }
            $featured_image_base = $optimized_basename;
        } else {
            $errors[] = "Sorry, there was an error processing your new featured image.";
        }
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // 1. Update the main posts table
            $slug = create_slug($title);
            $sql_update_post = "UPDATE posts SET title=?, slug=?, summary=?, content=?, featured_image=? WHERE id=?";
            $stmt_update_post = mysqli_prepare($conn, $sql_update_post);
            mysqli_stmt_bind_param($stmt_update_post, "sssssi", $title, $slug, $summary, $content, $featured_image_base, $post_id);
            mysqli_stmt_execute($stmt_update_post);
            mysqli_stmt_close($stmt_update_post);
            
            // 2. Update Categories: Delete all old associations, then insert the new ones.
            mysqli_query($conn, "DELETE FROM post_categories WHERE post_id = $post_id");
            if (!empty($new_category_ids)) {
                $sql_cat = "INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)";
                $stmt_cat = mysqli_prepare($conn, $sql_cat);
                foreach ($new_category_ids as $cat_id) {
                    $current_cat_id = (int)$cat_id;
                    mysqli_stmt_bind_param($stmt_cat, "ii", $post_id, $current_cat_id);
                    mysqli_stmt_execute($stmt_cat);
                }
                mysqli_stmt_close($stmt_cat);
            }

            // 3. Update Tags: Delete all old associations, then "get or create" and insert new ones.
            mysqli_query($conn, "DELETE FROM post_tags WHERE post_id = $post_id");
            if (!empty($new_tags_input)) {
                $tags_array = array_unique(array_map('trim', explode(',', $new_tags_input)));
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
            header("location: manage-news.php?status=updated");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Database update failed: " . $e->getMessage();
        }
    }
}
?>
<div class="content-header">
    <h2>Edit Article</h2>
    <a href="manage-news.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left icon"></i> Back to All News</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="edit-news.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data" novalidate>
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>
            <div class="form-group">
                <label for="summary">Summary (Optional)</label>
                <textarea name="summary" id="summary" class="form-control" rows="3"><?php echo htmlspecialchars($summary); ?></textarea>
            </div>

            <?php 
            // Set the content variable for the editor component to use
            $editor_content = $post['content'];
            // Include the reusable editor component
            include 'editor-component.php'; 
            ?>

            <div class="form-group">
                <label for="category_ids">Categories</label>
                <select name="category_ids[]" id="category_ids" class="form-control" multiple required>
                    <?php
                    $all_cats_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
                    while ($cat = mysqli_fetch_assoc($all_cats_result)) {
                        $selected = in_array($cat['id'], $category_ids) ? 'selected' : '';
                        echo "<option value='{$cat['id']}' $selected>".htmlspecialchars($cat['name'])."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tags_input">Tags</label>
                <input type="text" name="tags_input" id="tags_input" class="form-control" value="<?php echo htmlspecialchars($tags_input); ?>">
            </div>
            <div class="form-group">
                <label for="featured_image">New Featured Image (Optional)</label>
                <input type="file" name="featured_image" id="featured_image" class="form-control">
                <small class="form-text text-muted">Leave blank to keep the current image.</small>
                <div class="mt-2">
                    <strong>Current Image:</strong><br>
                    <?php 
                        $image_paths = getImagePaths($current_image_base);
                    ?>
                    <picture>
                        <source srcset="<?php echo $image_paths['webp']; ?>" type="image/webp">
                        <source srcset="<?php echo $image_paths['jpg']; ?>" type="image/jpeg">
                        <img src="<?php echo $image_paths['jpg']; ?>" width="200" alt="Current Featured Image" class="img-thumbnail mt-1">
                    </picture>
                </div>
            </div>
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Update Article</button>
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