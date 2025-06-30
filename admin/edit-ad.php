<?php
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_editing = $ad_id > 0;

// Initialize variables
$ad_name = $ad_type = $ad_content = $ad_link = '';
$is_active = 1;
$current_image = '';

if ($is_editing) {
    // Fetch existing ad data
    $stmt = mysqli_prepare($conn, "SELECT * FROM ads WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $ad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ad = mysqli_fetch_assoc($result);
    if ($ad) {
        $ad_name = $ad['ad_name'];
        $ad_type = $ad['ad_type'];
        $ad_content = $ad['ad_content'];
        $ad_link = $ad['ad_link'];
        $is_active = $ad['is_active'];
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_name = trim($_POST['ad_name']);
    $ad_type = trim($_POST['ad_type']);
    $ad_link = trim($_POST['ad_link']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($ad_type == 'image') {
        $ad_content = $_POST['current_ad_content']; // Keep old path by default
        if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] == 0) {
            $target_dir = "../uploads/ads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0775, true); }
            $filename = "ad_" . time() . '_' . basename($_FILES["ad_image"]["name"]);
            if (move_uploaded_file($_FILES["ad_image"]["tmp_name"], $target_dir . $filename)) {
                $ad_content = "uploads/ads/" . $filename;
            }
        }
    } else { // 'code' type
        // Use $_POST directly for code to allow for special characters
        $ad_content = $_POST['ad_content'];
    }
    
    if ($is_editing) {
        $sql = "UPDATE ads SET ad_name=?, ad_type=?, ad_content=?, ad_link=?, is_active=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssii", $ad_name, $ad_type, $ad_content, $ad_link, $is_active, $ad_id);
    } else {
        $sql = "INSERT INTO ads (ad_name, ad_type, ad_content, ad_link, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $ad_name, $ad_type, $ad_content, $ad_link, $is_active);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        header("location: manage-ads.php?status=" . ($is_editing ? 'updated' : 'added'));
        exit();
    } else {
        $error = "Database operation failed: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="content-header">
    <h2><?php echo $is_editing ? 'Edit Ad Creative' : 'Add New Ad Creative'; ?></h2>
    <a href="manage-ads.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left icon"></i> Back to Ad List</a>
</div>

<?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="ad_name">Ad Name (for internal reference)</label>
                        <input type="text" id="ad_name" name="ad_name" class="form-control" value="<?php echo htmlspecialchars($ad_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ad_type">Ad Type</label>
                        <select id="ad_type" name="ad_type" class="form-control" required>
                            <option value="image" <?php if($ad_type == 'image') echo 'selected'; ?>>Image (Upload)</option>
                            <option value="code" <?php if($ad_type == 'code') echo 'selected'; ?>>Code (e.g., AdSense, Video VAST tag)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Status</label>
                        <div class="form-check form-switch p-3 border rounded">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active"><strong>Active</strong> (Show this ad on the site)</label>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Conditional fields based on Ad Type -->
            <div id="image_fields">
                <div class="form-group">
                    <label for="ad_image">Upload Ad Image</label>
                    <input type="file" id="ad_image" name="ad_image" class="form-control">
                    <input type="hidden" name="current_ad_content" value="<?php echo htmlspecialchars($ad_content); ?>">
                    <?php if ($is_editing && $ad_type == 'image' && !empty($ad_content)): ?>
                        <div class="mt-2"><small>Current Image:</small><br><img src="../<?php echo htmlspecialchars($ad_content); ?>" height="90" class="img-thumbnail"></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="ad_link">Destination Link (URL)</label>
                    <input type="url" id="ad_link" name="ad_link" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($ad_link); ?>">
                </div>
            </div>

            <div id="code_fields" style="display: none;">
                <div class="form-group">
                    <label for="ad_content">Ad Code (HTML/JavaScript/Video Tag)</label>
                    <textarea id="ad_content" name="ad_content" class="form-control" rows="8"><?php echo htmlspecialchars($ad_content); ?></textarea>
                </div>
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Save Ad Creative</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const adTypeSelect = document.getElementById('ad_type');
    const imageFields = document.getElementById('image_fields');
    const codeFields = document.getElementById('code_fields');

    function toggleFields() {
        if (adTypeSelect.value === 'image') {
            imageFields.style.display = 'block';
            codeFields.style.display = 'none';
        } else {
            imageFields.style.display = 'none';
            codeFields.style.display = 'block';
        }
    }
    toggleFields();
    adTypeSelect.addEventListener('change', toggleFields);
});
</script>

<?php require_once 'includes/footer.php'; ?>