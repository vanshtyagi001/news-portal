<?php
session_start();
if(isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true){
    header("location: dashboard.php");
    exit;
}
require_once 'includes/db.php';

// IMPORTANT: Change this secret code for your live website!
define('ADMIN_SIGNUP_SECRET', '707875');
$errors = [];

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Fetch all data from the form
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']); // We need this
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $secret_code = trim($_POST['secret_code']);
    $role = trim($_POST['role']);

    // Validation
    if(empty($username) || empty($full_name) || empty($password) || empty($role)){
        $errors[] = "All fields are required.";
    }
    if($password !== $confirm_password){
        $errors[] = "Passwords do not match.";
    }
    if(strlen($password) < 6){
        $errors[] = "Password must be at least 6 characters long.";
    }
    if($secret_code !== ADMIN_SIGNUP_SECRET){
        $errors[] = "Invalid secret code. Admin registration is restricted.";
    }
    $allowed_roles = ['editor', 'author', 'super_admin']; // Allow creating super_admin if needed
    if(!in_array($role, $allowed_roles)){
        $errors[] = "Invalid role selected.";
    }

    // Check if username is already taken
    if(empty($errors)){
        $sql_check = "SELECT id FROM admins WHERE username = ?";
        if($stmt_check = mysqli_prepare($conn, $sql_check)){
            mysqli_stmt_bind_param($stmt_check, "s", $username);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if(mysqli_stmt_num_rows($stmt_check) > 0){
                $errors[] = "This username is already taken.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    if(empty($errors)){
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        //
        // --- THE CRITICAL FIX IS HERE ---
        // The SQL query must match the table structure exactly.
        // The correct fields are: username, password, full_name, role
        //
        $sql_insert = "INSERT INTO admins (username, password, full_name, role) VALUES (?, ?, ?, ?)";
        
        if($stmt_insert = mysqli_prepare($conn, $sql_insert)){
            // The bind params must match the order and number of '?' in the SQL.
            // 4 question marks = 4 variables. 'ssss' = 4 strings.
            mysqli_stmt_bind_param($stmt_insert, "ssss", $username, $hashed_password, $full_name, $role);
            
            if(mysqli_stmt_execute($stmt_insert)){
                header("location: index.php?status=signup_success");
                exit();
            } else {
                // If this error shows, it's a database-level problem.
                $errors[] = "Database Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_insert);
        }
    }
    if(!empty($conn)) {
      mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup - Raj News</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="logo">Raj<span>News</span></div>
        <h2>Create New Admin Account</h2>

        <?php 
        if(!empty($errors)){
            echo '<div class="alert alert-danger">';
            foreach($errors as $error){
                echo "<p class='mb-0'>$error</p>";
            }
            echo '</div>';
        }        
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
                <i class="fa-solid fa-user icon"></i>
            </div>
            <div class="form-group">
                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                <i class="fa-solid fa-id-card icon"></i>
            </div>
            <div class="form-group">
                <i class="fa-solid fa-user-shield icon"></i>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="roleDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding-left: 35px;">
                        Select Role
                    </button>
                    <ul class="dropdown-menu w-100" aria-labelledby="roleDropdown">
                        <li><a class="dropdown-item" href="#" data-value="editor">Editor</a></li>
                        <li><a class="dropdown-item" href="#" data-value="author">Author</a></li>
                    </ul>
                </div>
                <input type="hidden" name="role" id="roleInput" value="">
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <i class="fa-solid fa-lock icon"></i>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                <i class="fa-solid fa-check-double icon"></i>
            </div>
            <div class="form-group">
                <input type="password" name="secret_code" class="form-control" placeholder="Admin Secret Code" required>
                <i class="fa-solid fa-key icon"></i>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Create Account</button>
            </div>
        </form>
        <p class="text-center mt-3">Already have an admin account? <a href="index.php">Login Here</a></p>
    </div>    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleDropdown = document.getElementById('roleDropdown');
            const roleInput = document.getElementById('roleInput');
            const dropdownItems = document.querySelectorAll('.dropdown-menu a.dropdown-item');

            dropdownItems.forEach(item => {
                item.addEventListener('click', function(event) {
                    event.preventDefault();
                    const selectedValue = this.dataset.value;
                    const selectedText = this.textContent;
                    roleInput.value = selectedValue;
                    roleDropdown.textContent = selectedText;
                });
            });
        });
    </script>
</body>
</html>