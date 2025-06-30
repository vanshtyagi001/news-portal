<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_loggedin'])) { header("Location: login.php"); exit; }

require_once __DIR__ . '/../admin/includes/db.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Fetch current user data for the forms
$sql_user = "SELECT username, email, avatar, created_at FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// --- FORM SUBMISSION LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Handle Profile Details Update ---
    if (isset($_POST['update_details'])) {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);

        if (empty($new_username) || empty($new_email)) { $errors[] = "Username and email cannot be empty."; }
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
        
        // Check if new username/email is already taken by ANOTHER user
        $sql_check = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ssi", $new_username, $new_email, $user_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_fetch($stmt_check)) {
            $errors[] = "That username or email is already in use by another account.";
        }
        mysqli_stmt_close($stmt_check);

        if (empty($errors)) {
            $sql_update = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssi", $new_username, $new_email, $user_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['user_username'] = $new_username; // Update session
                $success_message = "Profile details updated successfully!";
                $user['username'] = $new_username; // Refresh local data for display
                $user['email'] = $new_email;
            } else {
                $errors[] = "Could not update profile details.";
            }
            mysqli_stmt_close($stmt_update);
        }
    }

    // --- Handle Avatar Upload ---
    if (isset($_POST['update_avatar']) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        // (This logic remains the same)
        $target_dir = __DIR__ . "/../uploads/avatars/";
        $filename = "user_" . $user_id . "_" . time() . "_" . basename($_FILES["avatar"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["avatar"]["tmp_name"]);
        if($check === false) { $errors[] = "File is not a valid image."; }
        if($_FILES["avatar"]["size"] > 2097152) { $errors[] = "Sorry, file is too large (Max 2MB)."; }
        if(!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) { $errors[] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed."; }

        if (empty($errors)) {
            if (!empty($user['avatar']) && file_exists(__DIR__ . "/../" . $user['avatar'])) {
                unlink(__DIR__ . "/../" . $user['avatar']);
            }
            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                $avatar_path = "uploads/avatars/" . $filename;
                $sql_avatar = "UPDATE users SET avatar = ? WHERE id = ?";
                $stmt_avatar = mysqli_prepare($conn, $sql_avatar);
                mysqli_stmt_bind_param($stmt_avatar, "si", $avatar_path, $user_id);
                if(mysqli_stmt_execute($stmt_avatar)) {
                    $success_message = "Profile picture updated successfully!";
                    $user['avatar'] = $avatar_path;
                } else { $errors[] = "Failed to update database."; }
                mysqli_stmt_close($stmt_avatar);
            } else { $errors[] = "Sorry, there was an error uploading your file."; }
        }
    }
}

$page_title = "Edit Profile";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Profile Page Header -->
<div class="profile-page-header">
    <h2>Account Settings</h2>
    <p class="lead mb-0">Update your profile information and preferences.</p>
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
                <a href="edit-profile.php" class="list-group-item list-group-item-action active">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile & Avatar
                </a>
                <a href="change-password.php" class="list-group-item list-group-item-action">
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
        
        <!-- Edit Profile Details Card -->
        <div class="card profile-content shadow-sm mb-4">
            <div class="card-header">Edit Profile Details</div>
            <div class="card-body">
                <form action="edit-profile.php" method="post">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" name="update_details" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Change Avatar Card -->
        <div class="card profile-content shadow-sm">
            <div class="card-header">Update Profile Picture</div>
            <div class="card-body">
                <form action="edit-profile.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="avatar" class="form-label">Choose a new image (Max 2MB: JPG, PNG, GIF)</label>
                        <input class="form-control" type="file" id="avatar" name="avatar" required>
                    </div>
                    <button type="submit" name="update_avatar" class="btn btn-primary">Upload New Picture</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once __DIR__ . '/../includes/footer.php'; 
?>