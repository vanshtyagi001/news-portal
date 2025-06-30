<?php
/**
 * Raj News - User Registration Page (v13.5 - Include Fix)
 */

// We process the form submission BEFORE any HTML is outputted.
session_start();
$errors = [];

// If user is already logged in, redirect them away.
if (isset($_SESSION['user_loggedin'])) { 
    header("Location: /raj-news/user/profile.php"); 
    exit; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // We only need the database connection when the form is submitted.
    // require_once is safe; it won't be included again by the header.
    require_once __DIR__ . '/../admin/includes/db.php';

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Validation ---
    if (empty($username) || empty($email) || empty($password)) { $errors[] = "All fields are required."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    if (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters long."; }
    if ($password !== $confirm_password) { $errors[] = "Passwords do not match."; }

    if (empty($errors)) {
        // Check if username or email already exists
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Username or email is already taken.";
        }
        mysqli_stmt_close($stmt);
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed_password); // Corrected order
        if (mysqli_stmt_execute($stmt)) {
            header("Location: login.php?status=registered");
            exit();
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}

// Set the page title BEFORE including the header.
$page_title = "Create an Account";

// Now, include the header which will handle all core dependencies.
require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-wrapper">
    <h2>Create an Account</h2>
    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
        </div>
    <?php endif; ?>
    <form action="register.php" method="post">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
    <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a>.</p>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>