<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_loggedin'])) { header("Location: login.php"); exit; }

require_once __DIR__ . '/../admin/includes/db.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Fetch user data for the sidebar
$sql_user = "SELECT username, avatar, created_at FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // (Password change logic is unchanged)
    if(empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    } else {
        $sql_fetch = "SELECT password FROM users WHERE id = ?";
        $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
        mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
        mysqli_stmt_execute($stmt_fetch);
        $result = mysqli_stmt_get_result($stmt_fetch);
        $user_pass = mysqli_fetch_assoc($result);
        
        if ($user_pass && password_verify($current_password, $user_pass['password'])) {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update = "UPDATE users SET password = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "si", $new_hashed_password, $user_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $success_message = "Your password has been changed successfully!";
            } else { $errors[] = "Failed to update password."; }
            mysqli_stmt_close($stmt_update);
        } else { $errors[] = "Your current password is not correct."; }
        mysqli_stmt_close($stmt_fetch);
    }
}

$page_title = "Change Password";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Profile Page Header -->
<div class="profile-page-header">
    <h2>Account Security</h2>
    <p class="lead mb-0">Keep your account secure by using a strong, unique password.</p>
</div>

<div class="row">
    <!-- Left Sidebar Column -->
    <div class="col-lg-4 mb-4">
        <!-- Profile Avatar Card -->
        <div class="card profile-avatar-card shadow-sm mb-4">
            <div class="card-body">
                <?php $avatar_path = !empty($user['avatar']) ? '/raj-news/' . $user['avatar'] : 'https://dummyimage.com/150x150/ced4da/6c757d.jpg'; ?>
                <img src="<?php echo $avatar_path; ?>" class="rounded-circle profile-avatar" alt="User Avatar">
                <h4 class="card-title mt-3 mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                <p class="card-text text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <!-- Navigation Card -->
        <div class="card profile-nav shadow-sm">
            <div class="list-group list-group-flush">
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-comments"></i> Comment History
                </a>
                <a href="edit-profile.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile & Avatar
                </a>
                <a href="change-password.php" class="list-group-item list-group-item-action active">
                    <i class="fa-solid fa-key"></i> Change Password
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Right Content Column -->
    <div class="col-lg-8">
        <?php if(!empty($errors)): ?>
            <div class="alert alert-danger"><?php foreach($errors as $error) echo "<p class='mb-0'>$error</p>"; ?></div>
        <?php endif; ?>
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="card profile-content shadow-sm">
            <div class="card-header">Change Your Password</div>
            <div class="card-body">
                <form action="change-password.php" method="post">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once __DIR__ . '/../includes/footer.php'; 
?>