<?php
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); 
    exit;
}

$admin_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($admin_id_to_edit == 0) {
    header("location: manage-admins.php");
    exit();
}

$errors = [];
$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $role = trim($_POST['role']);
    $password = $_POST['password']; // Optional password change

    // Basic validation
    if (empty($full_name) || empty($role)) {
        $errors[] = "Full name and role are required.";
    }
    if (!in_array($role, ['super_admin', 'editor', 'author'])) {
        $errors[] = "Invalid role selected.";
    }
    // You cannot demote the last super admin
    if ($role !== 'super_admin' && $admin_id_to_edit == 1) { // Assuming ID 1 is the primary super admin
        $errors[] = "Cannot change the role of the primary Super Admin.";
    }
    
    // If a new password is provided, validate and hash it
    $password_sql_part = "";
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $password_sql_part = ", password = '" . mysqli_real_escape_string($conn, $hashed_password) . "'";
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE admins SET full_name = ?, role = ? $password_sql_part WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $full_name, $role, $admin_id_to_edit);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Admin account updated successfully.";
            if (!empty($password)) {
                 $success_message .= " Password has been changed.";
            }
        } else {
            $errors[] = "Failed to update admin account.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch current admin data to pre-fill the form
$sql_fetch = "SELECT username, full_name, role FROM admins WHERE id = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $admin_id_to_edit);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
$admin = mysqli_fetch_assoc($result);
if (!$admin) {
    header("location: manage-admins.php");
    exit();
}
mysqli_stmt_close($stmt_fetch);
?>
<div class="content-header">
    <h2>Edit Admin: <?php echo htmlspecialchars($admin['username']); ?></h2>
    <a href="manage-admins.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left icon"></i> Back to All Admins</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?></div>
<?php endif; ?>
<?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Admin Details</div>
    <div class="card-body">
        <form action="edit-admin.php?id=<?php echo $admin_id_to_edit; ?>" method="post">
            <div class="form-group">
                <label for="username">Username (Cannot be changed)</label>
                <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control" <?php if($admin_id_to_edit == 1) echo 'disabled'; ?>>
                    <option value="author" <?php if($admin['role'] == 'author') echo 'selected'; ?>>Author</option>
                    <option value="editor" <?php if($admin['role'] == 'editor') echo 'selected'; ?>>Editor</option>
                    <option value="super_admin" <?php if($admin['role'] == 'super_admin') echo 'selected'; ?>>Super Admin</option>
                </select>
                 <?php if($admin_id_to_edit == 1): ?>
                    <small class="text-muted">The primary Super Admin role cannot be changed.</small>
                <?php endif; ?>
            </div>
            <hr>
            <h5 class="mt-4">Change Password (Optional)</h5>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Update Admin Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>