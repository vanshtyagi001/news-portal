<?php 
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); 
    exit;
}

// PHP logic for adding/editing/deleting categories
$edit_cat_name = "";
$edit_cat_id = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cat_name'])) {
    $cat_name = trim($_POST['cat_name']);
    $cat_id = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : 0;
    if (!empty($cat_name)) {
        $slug = create_slug($cat_name);
        if ($cat_id > 0) {
            $sql = "UPDATE categories SET name=?, slug=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $cat_name, $slug, $cat_id);
        } else {
            $sql = "INSERT INTO categories (name, slug) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $cat_name, $slug);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("location: manage-categories.php");
        exit();
    }
}
if (isset($_GET['delete'])) {
    $cat_id = (int)$_GET['delete'];
    $sql = "DELETE FROM categories WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $cat_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("location: manage-categories.php");
    exit();
}
if (isset($_GET['edit'])) {
    $edit_cat_id = (int)$_GET['edit'];
    $sql = "SELECT name FROM categories WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $edit_cat_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $edit_cat_name);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
?>
<div class="content-header">
    <h2>Manage Categories</h2>
</div>

<div class="row">
    <!-- Add/Edit Form Column -->
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <?php echo $edit_cat_id > 0 ? '<i class="fa-solid fa-pen-to-square icon"></i> Edit' : '<i class="fa-solid fa-plus icon"></i> Add New'; ?> Category
            </div>
            <div class="card-body">
                <form action="manage-categories.php" method="post">
                    <input type="hidden" name="cat_id" value="<?php echo $edit_cat_id; ?>">
                    <div class="form-group">
                        <label for="cat_name">Category Name</label>
                        <input type="text" id="cat_name" name="cat_name" class="form-control" value="<?php echo htmlspecialchars($edit_cat_name); ?>" placeholder="e.g., Politics" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fa-solid fa-check icon"></i> <?php echo $edit_cat_id > 0 ? 'Update' : 'Add'; ?> Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Existing Categories List Column -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-list icon"></i> Existing Categories
            </div>
            <div class="card-body">
                <?php
                $categories = [];
                $result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $categories[] = $row;
                    }
                }
                ?>
                <!-- DESKTOP VIEW -->
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td class='text-end'>
                                        <a href='manage-categories.php?edit=<?php echo $category['id']; ?>' class='action-btn edit' title='Edit'><i class='fa-solid fa-pen-to-square'></i></a>
                                        <a href='manage-categories.php?delete=<?php echo $category['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure? This may affect existing news articles.');"><i class='fa-solid fa-trash-can'></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='2' class='text-center'>No categories found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE VIEW -->
                <div class="mobile-card-list">
                     <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="mobile-card">
                                <div class="card-title"><?php echo htmlspecialchars($category['name']); ?></div>
                                <div class="card-actions">
                                    <a href="manage-categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="fa-solid fa-pen-to-square icon"></i> Edit</a>
                                    <a href="manage-categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No categories found.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>