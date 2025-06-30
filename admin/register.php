<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup - Raj News</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="logo">Raj<span>News</span></div>
        <h2>Admin Account Creation</h2>
        
        <!-- Error/Success Messages -->

        <form action="register.php" method="post">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
                <i class="fa-solid fa-user icon"></i>
            </div>
            <div class="form-group">
                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                <i class="fa-solid fa-id-card icon"></i>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <i class="fa-solid fa-lock icon"></i>
            </div>
            <!-- ... other fields ... -->
            <div class="form-group">
                <button type="submit" class="btn">Create Account</button>
            </div>
        </form>
    </div>    
</body>
</html>