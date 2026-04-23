<?php
require_once 'includes/header.php';

if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

// Helper function to update a setting in the database
function update_setting($conn, $setting_name, $setting_value) {
    $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    mysqli_stmt_bind_param($stmt, "ss", $setting_name, $setting_value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large for upload limits.';
        case UPLOAD_ERR_PARTIAL:
            return 'File upload was incomplete. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporary upload directory is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server could not write the uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by a PHP extension.';
        default:
            return 'Unknown upload error.';
    }
}

// Helper function for logo/favicon uploads
function handle_identity_upload($file_input_name, $current_path, &$upload_errors) {
    if (!isset($_FILES[$file_input_name])) {
        return $current_path;
    }

    $file = $_FILES[$file_input_name];
    $input_label = ucwords(str_replace('_', ' ', $file_input_name));

    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_path;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors[] = $input_label . ': ' . get_upload_error_message((int)$file['error']);
        return $current_path;
    }

    $target_dir = __DIR__ . '/../assets/images/';
    if (!is_dir($target_dir) && !mkdir($target_dir, 0775, true)) {
        $upload_errors[] = $input_label . ': Could not create assets/images directory.';
        return $current_path;
    }

    if (!is_writable($target_dir)) {
        $upload_errors[] = $input_label . ': assets/images is not writable by PHP.';
        return $current_path;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/x-icon',
        'image/vnd.microsoft.icon'
    ];

    $original_name = basename($file['name']);
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions, true)) {
        $upload_errors[] = $input_label . ': Invalid file extension. Use JPG, PNG, GIF, WEBP, SVG, or ICO.';
        return $current_path;
    }

    $detected_mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if (!empty($detected_mime) && !in_array($detected_mime, $allowed_mimes, true)) {
        $upload_errors[] = $input_label . ': Invalid file type detected.';
        return $current_path;
    }

    $base_name = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($original_name, PATHINFO_FILENAME));
    if (empty($base_name)) {
        $base_name = 'site_identity';
    }
    $filename = time() . '_' . $base_name . '.' . $extension;
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Delete old file only when it is a custom uploaded identity file.
        $default_files = ['assets/images/logo.png', 'assets/images/favicon.ico'];
        if (!empty($current_path) && !in_array($current_path, $default_files, true)) {
            $old_file = __DIR__ . '/../' . ltrim($current_path, '/\\');
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        return 'assets/images/' . $filename;
    }

    $upload_errors[] = $input_label . ': Upload failed while moving file.';
    return $current_path; // Return old path if upload fails or no new file
}


// --- FORM PROCESSING BLOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload_errors = [];

    // We MUST fetch the current settings first to handle file uploads correctly
    $current_settings = [];
    $fetch_result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
    while($row = mysqli_fetch_assoc($fetch_result)){
        $current_settings[$row['setting_name']] = $row['setting_value'];
    }

    // Handle logo and favicon uploads, using current settings as a fallback
    $new_logo_path = handle_identity_upload('site_logo', $current_settings['site_logo'] ?? '', $upload_errors);
    $new_favicon_path = handle_identity_upload('site_favicon', $current_settings['site_favicon'] ?? '', $upload_errors);

    // Prepare an array of all settings to update
    $settings_to_update = [
        'site_name'       => trim($_POST['site_name']),
        'site_tagline'    => trim($_POST['site_tagline']),
        'site_logo'       => $new_logo_path,
        'site_favicon'    => $new_favicon_path,
        'show_view_count' => isset($_POST['show_view_count']) ? '1' : '0',
        'show_like_count' => isset($_POST['show_like_count']) ? '1' : '0',
        'allow_comments'  => isset($_POST['allow_comments']) ? '1' : '0',
        'ticker_speed'    => isset($_POST['ticker_speed']) ? (int)$_POST['ticker_speed'] : 40,
        'site_theme'      => $_POST['site_theme'] ?? 'default',
        'site_font'       => $_POST['site_font'] ?? 'default',
    ];

    // Loop through and update all settings in the database
    foreach ($settings_to_update as $name => $value) {
        update_setting($conn, $name, $value);
    }

    if (!empty($upload_errors)) {
        $_SESSION['settings_upload_errors'] = $upload_errors;
    }
    
    // --- THE CRITICAL FIX IS HERE ---
    // Redirect back to the same page with a success message.
    header("Location: settings.php?status=success");
    exit();
}


// --- DISPLAY LOGIC (runs on a GET request) ---
// Fetch all settings from the database for displaying in the form
$settings = [];
$result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
while($row = mysqli_fetch_assoc($result)){
    $settings[$row['setting_name']] = $row['setting_value'];
}

$upload_errors = $_SESSION['settings_upload_errors'] ?? [];
unset($_SESSION['settings_upload_errors']);
?>

<div class="content-header"><h2>Site Settings</h2></div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="alert alert-success">Settings updated successfully!</div>
<?php endif; ?>

<?php if (!empty($upload_errors)): ?>
    <div class="alert alert-warning">
        <?php foreach ($upload_errors as $upload_error): ?>
            <p class="mb-0"><?php echo htmlspecialchars($upload_error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Site Identity Card -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-id-card icon"></i> Site Identity</div>
    <div class="card-body">
        <form action="settings.php" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="site_name" class="form-label"><strong>Website Name</strong></label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Express News'); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="site_tagline" class="form-label"><strong>Tagline</strong></label>
                        <input type="text" class="form-control" id="site_tagline" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? 'Your Daily News Source'); ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="site_logo" class="form-label"><strong>Site Logo (PNG recommended)</strong></label>
                        <input type="file" class="form-control" id="site_logo" name="site_logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico">
                        <div class="mt-2">
                            <small>Current Logo:</small><br>
                            <img src="../<?php echo htmlspecialchars($settings['site_logo'] ?? ''); ?>" height="40" alt="Current Logo" style="background: #eee; padding: 5px; border-radius: 5px;">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                     <div class="form-group">
                        <label for="site_favicon" class="form-label"><strong>Favicon (.ico, .png)</strong></label>
                                <input type="file" class="form-control" id="site_favicon" name="site_favicon" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico">
                         <div class="mt-2">
                            <small>Current Favicon:</small><br>
                            <img src="../<?php echo htmlspecialchars($settings['site_favicon'] ?? ''); ?>" width="32" alt="Current Favicon">
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            <div class="card-header border-0 ps-0 mb-3"><i class="fa-solid fa-sliders icon"></i> General Settings</div>
            
            <div class="form-group border-bottom pb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show_view_count" name="show_view_count" value="1" <?php echo ($settings['show_view_count'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_view_count"><strong>Show public view count on articles</strong></label>
                </div>
            </div>
            <div class="form-group border-bottom py-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show_like_count" name="show_like_count" value="1" <?php echo ($settings['show_like_count'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_like_count"><strong>Show public like count on articles</strong></label>
                </div>
            </div>
             <div class="form-group border-bottom py-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1" <?php echo ($settings['allow_comments'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_comments"><strong>Enable Comment Section on all articles</strong></label>
                </div>
            </div>
            <div class="form-group pt-3">
                <label for="ticker_speed" class="form-label"><strong>News Ticker Speed (in seconds)</strong></label>
                <input type="range" class="form-range" min="10" max="100" step="5" id="ticker_speed" name="ticker_speed" value="<?php echo htmlspecialchars($settings['ticker_speed'] ?? '40'); ?>" oninput="this.nextElementSibling.value = this.value + 's'">
                <output class="badge bg-secondary"><?php echo htmlspecialchars($settings['ticker_speed'] ?? '40'); ?>s</output>
                <small class="d-block text-muted">Higher number = Slower scroll. Recommended: 30-60 seconds.</small>
            </div>
            
            <div class="form-group border-bottom py-3">
                <label class="form-label"><strong>Theme &amp; Font Customization</strong></label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <span class="text-muted me-2">Active Color Theme:</span>
                        <strong><?php
                            $theme_labels = ['default'=>'Default','toi'=>'TOI Theme','bbc'=>'BBC Theme','times_now'=>'Times Now','dark'=>'Dark Theme','minimal'=>'Minimal Light','tech'=>'Tech Theme','warm'=>'Warm Newspaper','gradient'=>'Gradient Modern'];
                            echo htmlspecialchars($theme_labels[$settings['site_theme'] ?? 'default'] ?? 'Default');
                        ?></strong>
                    </div>
                    <div>
                        <span class="text-muted me-2">Active Font Theme:</span>
                        <strong><?php
                            $font_labels = ['default'=>'Default','classic'=>'Classic News','modern'=>'Modern News','tech_font'=>'Tech Style','minimal_font'=>'Minimal Clean','bold'=>'Bold Headlines','magazine'=>'Premium Magazine','elegant'=>'Elegant Serif','futuristic'=>'Futuristic','friendly'=>'Friendly UI','corporate'=>'Professional Corporate'];
                            echo htmlspecialchars($font_labels[$settings['site_font'] ?? 'default'] ?? 'Default');
                        ?></strong>
                    </div>
                    <a href="theme-settings.php" class="btn btn-primary btn-icon btn-sm">
                        <i class="fa-solid fa-palette icon"></i> Open Theme Customization
                    </a>
                </div>
                <small class="d-block text-muted mt-2">Use the Theme Customization panel to visually select color and font themes with live previews.</small>
                <!-- Keep hidden inputs so the form doesn't overwrite theme settings on save -->
                <input type="hidden" name="site_theme" value="<?php echo htmlspecialchars($settings['site_theme'] ?? 'default'); ?>">
                <input type="hidden" name="site_font" value="<?php echo htmlspecialchars($settings['site_font'] ?? 'default'); ?>">
            </div>
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Save All Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>