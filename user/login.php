<?php
// We no longer include db.php here directly.
// The header will handle it. But we must process the form BEFORE any HTML is sent.
// Therefore, we do all PHP logic first, then include the header.

session_start();
$error = "";

// If user is already logged in, redirect them to the homepage.
if (isset($_SESSION['user_loggedin'])) { 
    header("Location: /raj-news/index.php"); 
    exit; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // We need to establish a DB connection just for this POST block.
    // We include it here, but it won't be re-included by the header thanks to require_once.
    require_once __DIR__ . '/../admin/includes/db.php';

    $email = trim($_POST['email']);
    $password_from_form = $_POST['password'];

    if (empty($email) || empty($password_from_form)) { 
        $error = "Email and password are required."; 
    } else {
        $sql = "SELECT id, username, password FROM users WHERE email = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            if(mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password_from_db);
                    if(mysqli_stmt_fetch($stmt)) {
                        if(password_verify($password_from_form, $hashed_password_from_db)) {
                            $_SESSION["user_loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["user_username"] = $username;                            
                            header("location: /raj-news/index.php?status=login_success");
                            exit;
                        } else {
                            $error = "Invalid email or password.";
                        }
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($conn);
}

// Set the page title BEFORE including the header.
$page_title = "User Login";

// Now include the header. It will handle db.php, ads.php, and session_start() for us.
require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-wrapper">
    <h2>User Login</h2>
    <?php if(isset($_GET['status']) && $_GET['status'] == 'registered'): ?>
        <div class="alert alert-success">Registration successful! Please log in.</div>
    <?php endif; ?>
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form action="login.php" method="post">
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a>.</p>
</div>
<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>