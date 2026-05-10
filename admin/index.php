<?php
session_start();
if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}
require_once 'includes/db.php';

$username = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password, role FROM admins WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $id, $db_username, $hashed_password, $role);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            $_SESSION["admin_loggedin"] = true;
                            $_SESSION["admin_id"]       = $id;
                            $_SESSION["admin_username"] = $db_username;
                            $_SESSION["admin_role"]     = $role;
                            if (isset($_POST['remember_me'])) {
                                setcookie('admin_remember', bin2hex(random_bytes(32)), time() + (30 * 24 * 60 * 60), '/', '', false, true);
                            }
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    // NOTE: Do NOT close $conn here — we still need it below for settings/logo queries
}

// ── Fetch all site settings (logo, theme, font, name) ──────────────────────
$site_settings = [];
$s_res = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
if ($s_res) {
    while ($s_row = mysqli_fetch_assoc($s_res)) {
        $site_settings[$s_row['setting_name']] = $s_row['setting_value'];
    }
}

$site_name   = $site_settings['site_name']   ?? 'Express News';
$site_logo   = $site_settings['site_logo']   ?? '';
$site_theme  = $site_settings['site_theme']  ?? 'default';
$site_font   = $site_settings['site_font']   ?? 'default';

// Resolve logo to a usable <img> src (relative to /admin/)
$logo_src = '';
if (!empty($site_logo)) {
    $abs = __DIR__ . '/../' . ltrim($site_logo, '/');
    if (file_exists($abs)) {
        $logo_src = '../' . ltrim($site_logo, '/');
    }
}

mysqli_close($conn);

// ── Theme → CSS variable map (mirrors public style.css) ────────────────────
$theme_map = [
    'default'    => ['bg'=>'#f8f9fa',  'text'=>'#212529', 'card'=>'#ffffff', 'border'=>'rgba(0,0,0,.125)', 'primary'=>'#0d6efd',  'muted'=>'#6c757d', 'panel_bg'=>'#1e293b', 'panel_text'=>'#e2e8f0', 'btn_text'=>'#ffffff'],
    'toi'        => ['bg'=>'#ffffff',  'text'=>'#000000', 'card'=>'#ffffff', 'border'=>'#d4d4d4',          'primary'=>'#b50000',  'muted'=>'#6e6e6e', 'panel_bg'=>'#1a0000', 'panel_text'=>'#f5e0e0', 'btn_text'=>'#ffffff'],
    'bbc'        => ['bg'=>'#f2f2f2',  'text'=>'#111111', 'card'=>'#ffffff', 'border'=>'#e2e2e2',          'primary'=>'#bb1919',  'muted'=>'#5a5a5a', 'panel_bg'=>'#1a0000', 'panel_text'=>'#f5e0e0', 'btn_text'=>'#ffffff'],
    'times_now'  => ['bg'=>'#ffffff',  'text'=>'#000000', 'card'=>'#ffffff', 'border'=>'#e0e0e0',          'primary'=>'#e60023',  'muted'=>'#003366', 'panel_bg'=>'#001a33', 'panel_text'=>'#e0eeff', 'btn_text'=>'#ffffff'],
    'dark'       => ['bg'=>'#121212',  'text'=>'#ffffff', 'card'=>'#1e1e1e', 'border'=>'#333333',          'primary'=>'#ff3b3b',  'muted'=>'#aaaaaa', 'panel_bg'=>'#0a0a0a', 'panel_text'=>'#e0e0e0', 'btn_text'=>'#ffffff'],
    'minimal'    => ['bg'=>'#f5f5f5',  'text'=>'#222222', 'card'=>'#ffffff', 'border'=>'#e0e0e0',          'primary'=>'#007bff',  'muted'=>'#888888', 'panel_bg'=>'#1a1a2e', 'panel_text'=>'#e0e0ff', 'btn_text'=>'#ffffff'],
    'tech'       => ['bg'=>'#0f172a',  'text'=>'#e2e8f0', 'card'=>'#1e293b', 'border'=>'#334155',          'primary'=>'#38bdf8',  'muted'=>'#94a3b8', 'panel_bg'=>'#0a0f1e', 'panel_text'=>'#cbd5e1', 'btn_text'=>'#0f172a'],
    'warm'       => ['bg'=>'#fff8e1',  'text'=>'#3e2723', 'card'=>'#fff3cd', 'border'=>'#d7ccc8',          'primary'=>'#bf360c',  'muted'=>'#8d6e63', 'panel_bg'=>'#1a0e00', 'panel_text'=>'#ffe0c0', 'btn_text'=>'#ffffff'],
    'gradient'   => ['bg'=>'#667eea',  'text'=>'#ffffff', 'card'=>'rgba(255,255,255,0.12)', 'border'=>'rgba(255,255,255,0.2)', 'primary'=>'#ffffff', 'muted'=>'rgba(255,255,255,0.65)', 'panel_bg'=>'#3730a3', 'panel_text'=>'#e0e7ff', 'btn_text'=>'#667eea'],
];
$t = $theme_map[$site_theme] ?? $theme_map['default'];

// ── Font map ────────────────────────────────────────────────────────────────
$font_map = [
    'default'      => ['-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif',                '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif'],
    'classic'      => ["'Georgia', serif",                                                                         "'Times New Roman', Times, serif"],
    'modern'       => ["'Roboto', sans-serif",                                                                     "'Roboto Slab', serif"],
    'tech_font'    => ["'Inter', sans-serif",                                                                      "'Poppins', sans-serif"],
    'minimal_font' => ["'Open Sans', sans-serif",                                                                  "'Montserrat', sans-serif"],
    'bold'         => ["'Lato', sans-serif",                                                                       "'Oswald', sans-serif"],
    'magazine'     => ["'Merriweather', serif",                                                                    "'Playfair Display', serif"],
    'elegant'      => ["'Libre Baskerville', serif",                                                               "'Playfair Display', serif"],
    'futuristic'   => ["'Orbitron', sans-serif",                                                                   "'Orbitron', sans-serif"],
    'friendly'     => ["'Nunito', sans-serif",                                                                     "'Nunito', sans-serif"],
    'corporate'    => ["'Source Sans 3', sans-serif",                                                              "'Merriweather', serif"],
];
$fonts = $font_map[$site_font] ?? $font_map['default'];

// Dark themes need light text on the form panel
$dark_themes = ['dark', 'tech', 'gradient'];
$is_dark_theme = in_array($site_theme, $dark_themes);

// Gradient theme needs special body background
$body_bg_style = ($site_theme === 'gradient')
    ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; min-height:100vh;'
    : "background-color: {$t['bg']};";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts for active font theme -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lato:wght@400;700&family=Libre+Baskerville:wght@400;700&family=Merriweather:wght@400;700&family=Montserrat:wght@600;700&family=Nunito:wght@400;600;700&family=Open+Sans:wght@400;600&family=Orbitron:wght@400;700&family=Oswald:wght@400;600&family=Playfair+Display:wght@600;700&family=Poppins:wght@500;600;700&family=Roboto+Slab:wght@400;700&family=Roboto:wght@400;500;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        /* ── Active theme variables injected server-side ── */
        :root {
            --auth-bg:          <?php echo $t['bg']; ?>;
            --auth-text:        <?php echo $t['text']; ?>;
            --auth-card:        <?php echo $t['card']; ?>;
            --auth-border:      <?php echo $t['border']; ?>;
            --auth-primary:     <?php echo $t['primary']; ?>;
            --auth-muted:       <?php echo $t['muted']; ?>;
            --auth-panel-bg:    <?php echo $t['panel_bg']; ?>;
            --auth-panel-text:  <?php echo $t['panel_text']; ?>;
            --auth-btn-text:    <?php echo $t['btn_text']; ?>;
            --auth-font-body:   <?php echo $fonts[0]; ?>;
            --auth-font-heading:<?php echo $fonts[1]; ?>;
        }
        body.auth-page { <?php echo $body_bg_style; ?> }
    </style>
</head>
<body class="auth-page<?php echo $is_dark_theme ? ' auth-dark' : ''; ?>">

    <div class="auth-wrapper">

        <!-- ── Left panel — brand ── -->
        <div class="auth-brand-panel">
            <div class="auth-brand-inner">

                <div class="auth-brand-logo">
                    <?php if (!empty($logo_src)): ?>
                        <img src="<?php echo htmlspecialchars($logo_src); ?>"
                             alt="<?php echo htmlspecialchars($site_name); ?>">
                    <?php else: ?>
                        <span class="auth-wordmark"><?php echo htmlspecialchars($site_name); ?></span>
                    <?php endif; ?>
                </div>

                <h1 class="auth-brand-headline">Your newsroom,<br>under control.</h1>
                <p class="auth-brand-sub">Manage articles, categories, comments, ads and site settings — all from one place.</p>

                <div class="auth-brand-stats">
                    <div class="auth-stat"><i class="fa-solid fa-newspaper"></i><span>Articles</span></div>
                    <div class="auth-stat"><i class="fa-solid fa-comments"></i><span>Comments</span></div>
                    <div class="auth-stat"><i class="fa-solid fa-chart-line"></i><span>Analytics</span></div>
                    <div class="auth-stat"><i class="fa-solid fa-cog"></i><span>Settings</span></div>
                </div>

            </div>
        </div>

        <!-- ── Right panel — form ── -->
        <div class="auth-form-panel">
            <div class="auth-form-inner">

                <div class="auth-form-header">
                    <div class="auth-form-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h2>Admin Sign In</h2>
                    <p>Enter your credentials to access the dashboard.</p>
                </div>

                <?php if (isset($_GET['status']) && $_GET['status'] == 'signup_success'): ?>
                    <div class="auth-alert auth-alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        Account created successfully. Please sign in.
                    </div>
                <?php endif; ?>

                <?php if (!empty($login_err)): ?>
                    <div class="auth-alert auth-alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($login_err); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form" novalidate>

                    <div class="auth-field <?php echo !empty($username_err) ? 'has-error' : ''; ?>">
                        <label for="username">Username</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-user auth-input-icon"></i>
                            <input type="text" id="username" name="username"
                                   value="<?php echo htmlspecialchars($username); ?>"
                                   placeholder="Enter your username"
                                   autocomplete="username" required>
                        </div>
                        <?php if (!empty($username_err)): ?>
                            <span class="auth-field-error"><?php echo htmlspecialchars($username_err); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="auth-field <?php echo !empty($password_err) ? 'has-error' : ''; ?>">
                        <label for="password">Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-lock auth-input-icon"></i>
                            <input type="password" id="password" name="password"
                                   placeholder="Enter your password"
                                   autocomplete="current-password" required>
                            <button type="button" class="auth-toggle-pw" tabindex="-1" data-target="password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($password_err)): ?>
                            <span class="auth-field-error"><?php echo htmlspecialchars($password_err); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="auth-remember-row">
                        <label class="auth-checkbox-label">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            <span class="auth-checkbox-custom"></span>
                            Remember me for 30 days
                        </label>
                    </div>

                    <button type="submit" class="auth-submit-btn">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Sign In to Dashboard
                    </button>

                </form>

                <div class="auth-form-footer">
                    <a href="../index.php" class="auth-back-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to public site
                    </a>
                    <a href="signup.php" class="auth-register-link">Create admin account</a>
                </div>

            </div>
        </div>

    </div>

    <script>
    document.querySelectorAll('.auth-toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target || 'password');
            const icon  = btn.querySelector('i');
            if (!input) return;
            const hidden = input.type === 'password';
            input.type   = hidden ? 'text' : 'password';
            icon.className = hidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });
    </script>
</body>
</html>
