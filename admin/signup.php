<?php
session_start();
if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}
require_once 'includes/db.php';

define('ADMIN_SIGNUP_SECRET', '707875');
$errors = [];
$form   = ['username' => '', 'full_name' => '', 'role' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form['username']   = trim($_POST['username']   ?? '');
    $form['full_name']  = trim($_POST['full_name']  ?? '');
    $form['role']       = trim($_POST['role']       ?? '');
    $password           = $_POST['password']         ?? '';
    $confirm_password   = $_POST['confirm_password'] ?? '';
    $secret_code        = trim($_POST['secret_code'] ?? '');

    if (empty($form['username']) || empty($form['full_name']) || empty($password) || empty($form['role'])) {
        $errors[] = "All fields are required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($secret_code !== ADMIN_SIGNUP_SECRET) {
        $errors[] = "Invalid secret code. Admin registration is restricted.";
    }
    if (!in_array($form['role'], ['editor', 'author', 'super_admin'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $form['username']);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $errors[] = "This username is already taken.";
        }
        mysqli_stmt_close($stmt_check);
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO admins (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "ssss", $form['username'], $hashed, $form['full_name'], $form['role']);
        if (mysqli_stmt_execute($stmt_insert)) {
            header("location: index.php?status=signup_success");
            exit();
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_insert);
    }
    // NOTE: Do NOT close $conn here — still needed for settings below
}

// ── Fetch site settings ─────────────────────────────────────────────────────
$site_settings = [];
$s_res = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
if ($s_res) {
    while ($s_row = mysqli_fetch_assoc($s_res)) {
        $site_settings[$s_row['setting_name']] = $s_row['setting_value'];
    }
}
$site_name  = $site_settings['site_name']  ?? 'Express News';
$site_logo  = $site_settings['site_logo']  ?? '';
$site_theme = $site_settings['site_theme'] ?? 'default';
$site_font  = $site_settings['site_font']  ?? 'default';

$logo_src = '';
if (!empty($site_logo)) {
    $abs = __DIR__ . '/../' . ltrim($site_logo, '/');
    if (file_exists($abs)) {
        $logo_src = '../' . ltrim($site_logo, '/');
    }
}

mysqli_close($conn);

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

$font_map = [
    'default'      => ['-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif'],
    'classic'      => ["'Georgia', serif",                "'Times New Roman', Times, serif"],
    'modern'       => ["'Roboto', sans-serif",             "'Roboto Slab', serif"],
    'tech_font'    => ["'Inter', sans-serif",              "'Poppins', sans-serif"],
    'minimal_font' => ["'Open Sans', sans-serif",          "'Montserrat', sans-serif"],
    'bold'         => ["'Lato', sans-serif",               "'Oswald', sans-serif"],
    'magazine'     => ["'Merriweather', serif",            "'Playfair Display', serif"],
    'elegant'      => ["'Libre Baskerville', serif",       "'Playfair Display', serif"],
    'futuristic'   => ["'Orbitron', sans-serif",           "'Orbitron', sans-serif"],
    'friendly'     => ["'Nunito', sans-serif",             "'Nunito', sans-serif"],
    'corporate'    => ["'Source Sans 3', sans-serif",      "'Merriweather', serif"],
];
$fonts = $font_map[$site_font] ?? $font_map['default'];

$dark_themes   = ['dark', 'tech', 'gradient'];
$is_dark_theme = in_array($site_theme, $dark_themes);
$body_bg_style = ($site_theme === 'gradient')
    ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; min-height:100vh;'
    : "background-color: {$t['bg']};";

$role_labels = ['author' => 'Author', 'editor' => 'Editor', 'super_admin' => 'Super Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account — <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lato:wght@400;700&family=Libre+Baskerville:wght@400;700&family=Merriweather:wght@400;700&family=Montserrat:wght@600;700&family=Nunito:wght@400;600;700&family=Open+Sans:wght@400;600&family=Orbitron:wght@400;700&family=Oswald:wght@400;600&family=Playfair+Display:wght@600;700&family=Poppins:wght@500;600;700&family=Roboto+Slab:wght@400;700&family=Roboto:wght@400;500;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        :root {
            --auth-bg:           <?php echo $t['bg']; ?>;
            --auth-text:         <?php echo $t['text']; ?>;
            --auth-card:         <?php echo $t['card']; ?>;
            --auth-border:       <?php echo $t['border']; ?>;
            --auth-primary:      <?php echo $t['primary']; ?>;
            --auth-muted:        <?php echo $t['muted']; ?>;
            --auth-panel-bg:     <?php echo $t['panel_bg']; ?>;
            --auth-panel-text:   <?php echo $t['panel_text']; ?>;
            --auth-btn-text:     <?php echo $t['btn_text']; ?>;
            --auth-font-body:    <?php echo $fonts[0]; ?>;
            --auth-font-heading: <?php echo $fonts[1]; ?>;
        }
        body.auth-page { <?php echo $body_bg_style; ?> }
    </style>
</head>
<body class="auth-page<?php echo $is_dark_theme ? ' auth-dark' : ''; ?>">

    <div class="auth-wrapper">

        <!-- Left panel — branding -->
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
                <p class="auth-brand-sub">Create a new admin account to start publishing, editing, and managing content on <?php echo htmlspecialchars($site_name); ?>.</p>
                <div class="auth-roles-info">
                    <div class="auth-role-item">
                        <i class="fa-solid fa-pen-nib"></i>
                        <div>
                            <strong>Author</strong>
                            <span>Write and publish articles</span>
                        </div>
                    </div>
                    <div class="auth-role-item">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <div>
                            <strong>Editor</strong>
                            <span>Manage content &amp; comments</span>
                        </div>
                    </div>
                    <div class="auth-role-item">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Super Admin</strong>
                            <span>Full site control</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right panel — form -->
        <div class="auth-form-panel">
            <div class="auth-form-inner auth-form-inner--wide">

                <div class="auth-form-header">
                    <div class="auth-form-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h2>Create Admin Account</h2>
                    <p>Fill in the details below. A secret code is required.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="auth-alert auth-alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form" novalidate>

                    <div class="auth-fields-row">
                        <div class="auth-field">
                            <label for="username">Username</label>
                            <div class="auth-input-wrap">
                                <i class="fa-solid fa-user auth-input-icon"></i>
                                <input type="text" id="username" name="username"
                                       value="<?php echo htmlspecialchars($form['username']); ?>"
                                       placeholder="e.g. john_doe" required>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="full_name">Full Name</label>
                            <div class="auth-input-wrap">
                                <i class="fa-solid fa-id-card auth-input-icon"></i>
                                <input type="text" id="full_name" name="full_name"
                                       value="<?php echo htmlspecialchars($form['full_name']); ?>"
                                       placeholder="e.g. John Doe" required>
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="role">Role</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-user-shield auth-input-icon"></i>
                            <select id="role" name="role" required>
                                <option value="" disabled <?php echo empty($form['role']) ? 'selected' : ''; ?>>Select a role</option>
                                <?php foreach ($role_labels as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $form['role'] === $val ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="auth-fields-row">
                        <div class="auth-field">
                            <label for="password">Password</label>
                            <div class="auth-input-wrap">
                                <i class="fa-solid fa-lock auth-input-icon"></i>
                                <input type="password" id="password" name="password"
                                       placeholder="Min. 6 characters" required>
                                <button type="button" class="auth-toggle-pw" tabindex="-1" data-target="password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="auth-input-wrap">
                                <i class="fa-solid fa-check-double auth-input-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Repeat password" required>
                                <button type="button" class="auth-toggle-pw" tabindex="-1" data-target="confirm_password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="secret_code">Admin Secret Code</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-key auth-input-icon"></i>
                            <input type="password" id="secret_code" name="secret_code"
                                   placeholder="Enter the secret registration code" required>
                        </div>
                        <span class="auth-field-hint">Contact your super admin for the secret code.</span>
                    </div>

                    <button type="submit" class="auth-submit-btn">
                        <i class="fa-solid fa-user-plus"></i>
                        Create Account
                    </button>

                </form>

                <div class="auth-form-footer">
                    <a href="../index.php" class="auth-back-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to public site
                    </a>
                    <a href="index.php" class="auth-register-link">Already have an account?</a>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password visibility toggles
    document.querySelectorAll('.auth-toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target || 'password';
            const input    = document.getElementById(targetId);
            const icon     = btn.querySelector('i');
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type     = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });
    </script>
</body>
</html>
