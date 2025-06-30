<?php
require_once 'includes/header.php';

if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

// Helper function to update a setting in the database
function update_setting($conn, $setting_name, $setting_value) {
    $stmt = mysqli_prepare($conn, "UPDATE settings SET setting_value = ? WHERE setting_name = ?");
    mysqli_stmt_bind_param($stmt, "ss", $setting_value, $setting_name);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Helper function for logo/favicon uploads
function handle_identity_upload($file_input_name, $current_path) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
        $target_dir = "../assets/images/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0775, true); }
        
        // Sanitize filename to prevent security issues
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES[$file_input_name]["name"]));
        $target_file = $target_dir . $filename;
        
        // Add more robust validation (file type, size)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/x-icon', 'image/svg+xml'];
        if (!in_array($_FILES[$file_input_name]['type'], $allowed_types)) {
            return $current_path; // Or return an error message
        }
        
        if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
            // Delete old file if it exists and is not a default one
            if (!empty($current_path) && strpos($current_path, 'logo.png') === false && strpos($current_path, 'favicon.ico') === false && file_exists("../" . $current_path)) {
                unlink("../" . $current_path);
            }
            return "assets/images/" . $filename; // Return the new path
        }
    }
    return $current_path; // Return old path if upload fails or no new file
}


// --- FORM PROCESSING BLOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We MUST fetch the current settings first to handle file uploads correctly
    $current_settings = [];
    $fetch_result = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
    while($row = mysqli_fetch_assoc($fetch_result)){
        $current_settings[$row['setting_name']] = $row['setting_value'];
    }

    // Handle logo and favicon uploads, using current settings as a fallback
    $new_logo_path = handle_identity_upload('site_logo', $current_settings['site_logo'] ?? '');
    $new_favicon_path = handle_identity_upload('site_favicon', $current_settings['site_favicon'] ?? '');

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
    ];

    // Loop through and update all settings in the database
    foreach ($settings_to_update as $name => $value) {
        update_setting($conn, $name, $value);
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
?>

<div class="content-header"><h2>Site Settings</h2></div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="alert alert-success">Settings updated successfully!</div>
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
                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Raj News'); ?>">
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
                        <input type="file" class="form-control" id="site_logo" name="site_logo">
                        <div class="mt-2">
                            <small>Current Logo:</small><br>
                            <img src="../<?php echo htmlspecialchars($settings['site_logo'] ?? ''); ?>" height="40" alt="Current Logo" style="background: #eee; padding: 5px; border-radius: 5px;">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                     <div class="form-group">
                        <label for="site_favicon" class="form-label"><strong>Favicon (.ico, .png)</strong></label>
                        <input type="file" class="form-control" id="site_favicon" name="site_favicon">
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
            
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="fa-solid fa-check icon"></i> Save All Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>