<?php
/**
 * Express News - Theme Customization Panel
 * Allows super_admin to select color themes and font presets.
 */
require_once 'includes/header.php';

if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied");
    exit;
}

// Helper: upsert a setting
function upsert_setting($conn, $name, $value) {
    $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    mysqli_stmt_bind_param($stmt, "ss", $name, $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// --- POST handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_themes = ['default','toi','bbc','times_now','dark','minimal','tech','warm','gradient'];
    $allowed_fonts  = ['default','classic','modern','tech_font','minimal_font','bold','magazine','elegant','futuristic','friendly','corporate'];

    $site_theme = in_array($_POST['site_theme'] ?? '', $allowed_themes) ? $_POST['site_theme'] : 'default';
    $site_font  = in_array($_POST['site_font']  ?? '', $allowed_fonts)  ? $_POST['site_font']  : 'default';

    upsert_setting($conn, 'site_theme', $site_theme);
    upsert_setting($conn, 'site_font',  $site_font);

    header("Location: theme-settings.php?status=success");
    exit;
}

// --- Fetch current settings ---
$settings = [];
$res = mysqli_query($conn, "SELECT setting_name, setting_value FROM settings");
while ($row = mysqli_fetch_assoc($res)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
$current_theme = $settings['site_theme'] ?? 'default';
$current_font  = $settings['site_font']  ?? 'default';

// --- Theme definitions (for the preview cards) ---
$color_themes = [
    'default'    => [
        'label'      => 'Default',
        'desc'       => 'Clean system default',
        'primary'    => '#0d6efd',
        'bg'         => '#f8f9fa',
        'text'       => '#212529',
        'card'       => '#ffffff',
        'secondary'  => '#6c757d',
        'dark'       => false,
    ],
    'toi'        => [
        'label'      => 'TOI Theme',
        'desc'       => 'Classic News',
        'primary'    => '#b50000',
        'bg'         => '#ffffff',
        'text'       => '#000000',
        'card'       => '#ffffff',
        'secondary'  => '#6e6e6e',
        'dark'       => false,
    ],
    'bbc'        => [
        'label'      => 'BBC Theme',
        'desc'       => 'Modern Clean',
        'primary'    => '#bb1919',
        'bg'         => '#ffffff',
        'text'       => '#111111',
        'card'       => '#ffffff',
        'secondary'  => '#5a5a5a',
        'dark'       => false,
    ],
    'times_now'  => [
        'label'      => 'Times Now',
        'desc'       => 'Bold TV Style',
        'primary'    => '#e60023',
        'bg'         => '#ffffff',
        'text'       => '#000000',
        'card'       => '#ffffff',
        'secondary'  => '#003366',
        'dark'       => false,
    ],
    'dark'       => [
        'label'      => 'Dark Theme',
        'desc'       => 'Night Mode',
        'primary'    => '#ff3b3b',
        'bg'         => '#121212',
        'text'       => '#ffffff',
        'card'       => '#1e1e1e',
        'secondary'  => '#bbbbbb',
        'dark'       => true,
    ],
    'minimal'    => [
        'label'      => 'Minimal Light',
        'desc'       => 'Clean & Simple',
        'primary'    => '#007bff',
        'bg'         => '#f5f5f5',
        'text'       => '#222222',
        'card'       => '#ffffff',
        'secondary'  => '#666666',
        'dark'       => false,
    ],
    'tech'       => [
        'label'      => 'Tech Theme',
        'desc'       => 'Modern Premium',
        'primary'    => '#38bdf8',
        'bg'         => '#0f172a',
        'text'       => '#e2e8f0',
        'card'       => '#1e293b',
        'secondary'  => '#22c55e',
        'dark'       => true,
    ],
    'warm'       => [
        'label'      => 'Warm Newspaper',
        'desc'       => 'Vintage Style',
        'primary'    => '#bf360c',
        'bg'         => '#fff8e1',
        'text'       => '#3e2723',
        'card'       => '#fff3cd',
        'secondary'  => '#8d6e63',
        'dark'       => false,
    ],
    'gradient'   => [
        'label'      => 'Gradient Modern',
        'desc'       => 'Vibrant & Bold',
        'primary'    => '#ffffff',
        'bg'         => '#667eea',
        'text'       => '#ffffff',
        'card'       => 'rgba(255,255,255,0.15)',
        'secondary'  => 'rgba(255,255,255,0.7)',
        'dark'       => true,
        'gradient'   => 'linear-gradient(135deg, #667eea, #764ba2)',
    ],
];

// --- Font definitions ---
$font_themes = [
    'default'      => ['label' => 'Default',              'desc' => 'System Fonts',                    'body' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', 'heading' => 'inherit',                          'google' => ''],
    'classic'      => ['label' => 'Classic News',         'desc' => 'Georgia / Times New Roman',       'body' => 'Georgia, serif',                                           'heading' => '"Times New Roman", Times, serif',  'google' => ''],
    'modern'       => ['label' => 'Modern News',          'desc' => 'Roboto / Roboto Slab',            'body' => "'Roboto', sans-serif",                                     'heading' => "'Roboto Slab', serif",             'google' => 'Roboto:wght@400;700&family=Roboto+Slab:wght@400;700'],
    'tech_font'    => ['label' => 'Tech Style',           'desc' => 'Inter / Poppins',                 'body' => "'Inter', sans-serif",                                      'heading' => "'Poppins', sans-serif",            'google' => 'Inter:wght@400;600&family=Poppins:wght@600;700'],
    'minimal_font' => ['label' => 'Minimal Clean',        'desc' => 'Open Sans / Montserrat',          'body' => "'Open Sans', sans-serif",                                  'heading' => "'Montserrat', sans-serif",         'google' => 'Open+Sans:wght@400;600&family=Montserrat:wght@600;700'],
    'bold'         => ['label' => 'Bold Headlines',       'desc' => 'Lato / Oswald',                   'body' => "'Lato', sans-serif",                                       'heading' => "'Oswald', sans-serif",             'google' => 'Lato:wght@400;700&family=Oswald:wght@400;600'],
    'magazine'     => ['label' => 'Premium Magazine',     'desc' => 'Merriweather / Playfair Display', 'body' => "'Merriweather', serif",                                    'heading' => "'Playfair Display', serif",        'google' => 'Merriweather:wght@400;700&family=Playfair+Display:wght@600;700'],
    'elegant'      => ['label' => 'Elegant Serif',        'desc' => 'Libre Baskerville / Playfair',    'body' => "'Libre Baskerville', serif",                               'heading' => "'Playfair Display', serif",        'google' => 'Libre+Baskerville:wght@400;700&family=Playfair+Display:wght@600;700'],
    'futuristic'   => ['label' => 'Futuristic',           'desc' => 'Orbitron',                        'body' => "'Orbitron', sans-serif",                                   'heading' => "'Orbitron', sans-serif",           'google' => 'Orbitron:wght@400;700'],
    'friendly'     => ['label' => 'Friendly UI',          'desc' => 'Nunito',                          'body' => "'Nunito', sans-serif",                                     'heading' => "'Nunito', sans-serif",             'google' => 'Nunito:wght@400;600;700'],
    'corporate'    => ['label' => 'Professional Corporate','desc' => 'Source Sans / Merriweather',     'body' => "'Source Sans 3', sans-serif",                              'heading' => "'Merriweather', serif",            'google' => 'Source+Sans+3:wght@400;600&family=Merriweather:wght@400;700'],
];
?>

<div class="content-header">
    <h2><i class="fa-solid fa-palette icon"></i> Theme Customization</h2>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="fa-solid fa-check-circle me-2"></i>
        Theme settings saved! The changes are now live on your website.
        <a href="../index.php" target="_blank" class="ms-2 text-success fw-bold">Preview Site <i class="fa-solid fa-external-link-alt"></i></a>
    </div>
<?php endif; ?>

<form action="theme-settings.php" method="post" id="themeForm">

    <!-- ===== COLOR THEMES ===== -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fa-solid fa-swatchbook icon"></i> Color Theme
            <span class="badge bg-primary ms-2" id="selectedThemeLabel">
                <?php echo htmlspecialchars($color_themes[$current_theme]['label'] ?? 'Default'); ?>
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Select a color theme that will be applied globally across the entire website for all visitors.</p>
            <div class="theme-grid" id="colorThemeGrid">
                <?php foreach ($color_themes as $key => $theme):
                    $is_selected = ($key === $current_theme);
                    $bg_style = isset($theme['gradient']) ? $theme['gradient'] : $theme['bg'];
                    $card_bg = $theme['card'];
                    $is_dark = $theme['dark'];
                ?>
                <div class="theme-card <?php echo $is_selected ? 'selected' : ''; ?>"
                     data-theme="<?php echo $key; ?>"
                     data-label="<?php echo htmlspecialchars($theme['label']); ?>"
                     onclick="selectTheme(this)">
                    <!-- Mini Preview -->
                    <div class="theme-preview" style="background: <?php echo $bg_style; ?>;">
                        <!-- Mini Navbar -->
                        <div class="tp-navbar" style="background: <?php echo $is_dark ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.9)'; ?>; border-bottom: 2px solid <?php echo $theme['primary']; ?>;">
                            <div class="tp-logo" style="background: <?php echo $theme['primary']; ?>;"></div>
                            <div class="tp-nav-lines">
                                <div style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.3)'; ?>;"></div>
                                <div style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.3)'; ?>;"></div>
                                <div style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.3)'; ?>;"></div>
                            </div>
                        </div>
                        <!-- Mini Cards -->
                        <div class="tp-content">
                            <div class="tp-card" style="background: <?php echo $card_bg; ?>;">
                                <div class="tp-card-img" style="background: <?php echo $theme['primary']; ?>;"></div>
                                <div class="tp-card-line" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.2)'; ?>;"></div>
                                <div class="tp-card-line short" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.12)'; ?>;"></div>
                            </div>
                            <div class="tp-card" style="background: <?php echo $card_bg; ?>;">
                                <div class="tp-card-img" style="background: <?php echo $theme['secondary']; ?>;"></div>
                                <div class="tp-card-line" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.2)'; ?>;"></div>
                                <div class="tp-card-line short" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.12)'; ?>;"></div>
                            </div>
                            <div class="tp-card" style="background: <?php echo $card_bg; ?>;">
                                <div class="tp-card-img" style="background: <?php echo $theme['primary']; ?>;"></div>
                                <div class="tp-card-line" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.2)'; ?>;"></div>
                                <div class="tp-card-line short" style="background: <?php echo $is_dark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.12)'; ?>;"></div>
                            </div>
                        </div>
                        <!-- Color Swatches -->
                        <div class="tp-swatches">
                            <span class="tp-swatch" style="background: <?php echo $theme['primary']; ?>;" title="Primary"></span>
                            <span class="tp-swatch" style="background: <?php echo $theme['secondary']; ?>;" title="Secondary"></span>
                            <span class="tp-swatch" style="background: <?php echo $theme['text']; ?>; border: 1px solid rgba(128,128,128,0.3);" title="Text"></span>
                        </div>
                    </div>
                    <!-- Label -->
                    <div class="theme-card-label">
                        <div class="tc-name"><?php echo htmlspecialchars($theme['label']); ?></div>
                        <div class="tc-desc"><?php echo htmlspecialchars($theme['desc']); ?></div>
                        <?php if ($is_selected): ?>
                            <span class="tc-active-badge"><i class="fa-solid fa-check"></i> Active</span>
                        <?php endif; ?>
                    </div>
                    <!-- Hidden radio -->
                    <input type="radio" name="site_theme" value="<?php echo $key; ?>"
                           <?php echo $is_selected ? 'checked' : ''; ?> style="display:none;">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== FONT THEMES ===== -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fa-solid fa-font icon"></i> Font Theme
            <span class="badge bg-primary ms-2" id="selectedFontLabel">
                <?php echo htmlspecialchars($font_themes[$current_font]['label'] ?? 'Default'); ?>
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Choose a typography style for headings and body text across the entire website.</p>
            <div class="font-grid" id="fontThemeGrid">
                <?php foreach ($font_themes as $key => $font):
                    $is_selected = ($key === $current_font);
                    $google_url = !empty($font['google'])
                        ? 'https://fonts.googleapis.com/css2?family=' . $font['google'] . '&display=swap'
                        : '';
                ?>
                <div class="font-card <?php echo $is_selected ? 'selected' : ''; ?>"
                     data-font="<?php echo $key; ?>"
                     data-label="<?php echo htmlspecialchars($font['label']); ?>"
                     data-body-font="<?php echo htmlspecialchars($font['body']); ?>"
                     data-heading-font="<?php echo htmlspecialchars($font['heading']); ?>"
                     data-google="<?php echo htmlspecialchars($google_url); ?>"
                     onclick="selectFont(this)">
                    <?php if (!empty($google_url)): ?>
                        <link rel="stylesheet" href="<?php echo htmlspecialchars($google_url); ?>">
                    <?php endif; ?>
                    <div class="font-preview">
                        <div class="fp-heading" style="font-family: <?php echo $font['heading']; ?>;">
                            Breaking News
                        </div>
                        <div class="fp-body" style="font-family: <?php echo $font['body']; ?>;">
                            The quick brown fox jumps over the lazy dog. Stay informed with the latest updates.
                        </div>
                    </div>
                    <div class="font-card-label">
                        <div class="fc-name"><?php echo htmlspecialchars($font['label']); ?></div>
                        <div class="fc-desc"><?php echo htmlspecialchars($font['desc']); ?></div>
                        <?php if ($is_selected): ?>
                            <span class="fc-active-badge"><i class="fa-solid fa-check"></i> Active</span>
                        <?php endif; ?>
                    </div>
                    <input type="radio" name="site_font" value="<?php echo $key; ?>"
                           <?php echo $is_selected ? 'checked' : ''; ?> style="display:none;">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <strong>Current Selection:</strong>
                <span class="badge bg-secondary ms-1" id="summaryTheme"><?php echo htmlspecialchars($color_themes[$current_theme]['label'] ?? 'Default'); ?></span>
                <span class="text-muted mx-1">+</span>
                <span class="badge bg-secondary" id="summaryFont"><?php echo htmlspecialchars($font_themes[$current_font]['label'] ?? 'Default'); ?></span>
            </div>
            <div class="d-flex gap-2">
                <a href="../index.php" target="_blank" class="btn btn-outline-secondary btn-icon">
                    <i class="fa-solid fa-eye icon"></i> Preview Site
                </a>
                <button type="submit" class="btn btn-primary btn-icon">
                    <i class="fa-solid fa-save icon"></i> Save Theme Settings
                </button>
            </div>
        </div>
    </div>

</form>

<style>
/* ===== Theme Grid ===== */
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
}
.theme-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
    background: #fff;
}
.theme-card:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 16px rgba(37,99,235,0.15);
    transform: translateY(-2px);
}
.theme-card.selected {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

/* Mini preview area */
.theme-preview {
    height: 110px;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
}
.tp-navbar {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 5px;
    border-radius: 4px;
    height: 18px;
}
.tp-logo {
    width: 22px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
}
.tp-nav-lines {
    display: flex;
    gap: 3px;
    align-items: center;
}
.tp-nav-lines div {
    width: 14px;
    height: 4px;
    border-radius: 2px;
}
.tp-content {
    display: flex;
    gap: 4px;
    flex: 1;
}
.tp-card {
    flex: 1;
    border-radius: 4px;
    padding: 4px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    overflow: hidden;
}
.tp-card-img {
    height: 28px;
    border-radius: 3px;
    opacity: 0.85;
}
.tp-card-line {
    height: 4px;
    border-radius: 2px;
}
.tp-card-line.short {
    width: 65%;
}
.tp-swatches {
    display: flex;
    gap: 4px;
    justify-content: center;
    padding: 2px 0;
}
.tp-swatch {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
}

/* Theme card label */
.theme-card-label {
    padding: 8px 10px;
    background: #fff;
    border-top: 1px solid #f0f0f0;
}
.tc-name {
    font-weight: 600;
    font-size: 0.82rem;
    color: #1f2937;
    line-height: 1.2;
}
.tc-desc {
    font-size: 0.72rem;
    color: #6b7280;
    margin-top: 1px;
}
.tc-active-badge {
    display: inline-block;
    margin-top: 4px;
    font-size: 0.68rem;
    background: #2563eb;
    color: #fff;
    padding: 1px 6px;
    border-radius: 20px;
    font-weight: 600;
}

/* ===== Font Grid ===== */
.font-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}
.font-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
    background: #fff;
}
.font-card:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 16px rgba(37,99,235,0.15);
    transform: translateY(-2px);
}
.font-card.selected {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}
.font-preview {
    padding: 14px 14px 10px;
    background: #fafafa;
    border-bottom: 1px solid #f0f0f0;
    min-height: 90px;
}
.fp-heading {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111;
    line-height: 1.3;
    margin-bottom: 6px;
}
.fp-body {
    font-size: 0.78rem;
    color: #555;
    line-height: 1.5;
}
.font-card-label {
    padding: 8px 12px;
    background: #fff;
}
.fc-name {
    font-weight: 600;
    font-size: 0.82rem;
    color: #1f2937;
}
.fc-desc {
    font-size: 0.72rem;
    color: #6b7280;
    margin-top: 1px;
}
.fc-active-badge {
    display: inline-block;
    margin-top: 4px;
    font-size: 0.68rem;
    background: #2563eb;
    color: #fff;
    padding: 1px 6px;
    border-radius: 20px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 576px) {
    .theme-grid { grid-template-columns: repeat(2, 1fr); }
    .font-grid  { grid-template-columns: 1fr; }
}
</style>

<script>
function selectTheme(card) {
    // Deselect all
    document.querySelectorAll('#colorThemeGrid .theme-card').forEach(c => {
        c.classList.remove('selected');
        const badge = c.querySelector('.tc-active-badge');
        if (badge) badge.remove();
        c.querySelector('input[type=radio]').checked = false;
    });
    // Select clicked
    card.classList.add('selected');
    card.querySelector('input[type=radio]').checked = true;
    const label = card.dataset.label;
    // Add active badge
    const labelDiv = card.querySelector('.theme-card-label');
    if (!labelDiv.querySelector('.tc-active-badge')) {
        const badge = document.createElement('span');
        badge.className = 'tc-active-badge';
        badge.innerHTML = '<i class="fa-solid fa-check"></i> Active';
        labelDiv.appendChild(badge);
    }
    // Update summary badges
    document.getElementById('selectedThemeLabel').textContent = label;
    document.getElementById('summaryTheme').textContent = label;
}

function selectFont(card) {
    // Deselect all
    document.querySelectorAll('#fontThemeGrid .font-card').forEach(c => {
        c.classList.remove('selected');
        const badge = c.querySelector('.fc-active-badge');
        if (badge) badge.remove();
        c.querySelector('input[type=radio]').checked = false;
    });
    // Select clicked
    card.classList.add('selected');
    card.querySelector('input[type=radio]').checked = true;
    const label = card.dataset.label;
    // Add active badge
    const labelDiv = card.querySelector('.font-card-label');
    if (!labelDiv.querySelector('.fc-active-badge')) {
        const badge = document.createElement('span');
        badge.className = 'fc-active-badge';
        badge.innerHTML = '<i class="fa-solid fa-check"></i> Active';
        labelDiv.appendChild(badge);
    }
    // Update summary badges
    document.getElementById('selectedFontLabel').textContent = label;
    document.getElementById('summaryFont').textContent = label;
}
</script>

<?php require_once 'includes/footer.php'; ?>
