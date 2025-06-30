<?php
require_once 'includes/header.php';

if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

// Fetch all available hooks and ads
$hooks_result = mysqli_query($conn, "SELECT * FROM ad_hooks ORDER BY description ASC");
$ads_result = mysqli_query($conn, "SELECT id, ad_name FROM ads WHERE is_active = 1 ORDER BY ad_name ASC");

// Fetch current placements to pre-select dropdowns
$placements = [];
$placements_result = mysqli_query($conn, "SELECT hook_name, id FROM ads WHERE hook_name IS NOT NULL");
if ($placements_result) {
    while($row = mysqli_fetch_assoc($placements_result)) {
        $placements[$row['hook_name']] = $row['id'];
    }
}

// Handle form submission to assign an ad to a hook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ad'])) {
    $ad_id = (int)$_POST['ad_id'];
    $hook_name = trim($_POST['hook_name']);
    
    mysqli_query($conn, "UPDATE ads SET hook_name = NULL WHERE hook_name = '" . mysqli_real_escape_string($conn, $hook_name) . "'");
    
    if ($ad_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE ads SET hook_name = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hook_name, $ad_id);
        mysqli_stmt_execute($stmt);
    }
    $success = "Ad placement updated successfully! Changes will be live immediately.";
    // Refresh placements after update
    $placements[$hook_name] = $ad_id;
}
?>
<style>
    .layout-preview-container { position: relative; background-color: #e9ecef; padding: 1rem; border-radius: 8px;}
    .ad-hotspot { fill: rgba(74, 105, 189, 0.2); stroke: rgba(74, 105, 189, 0.7); stroke-width: 2; cursor: pointer; transition: all 0.2s ease; }
    .ad-hotspot:hover { fill: rgba(74, 105, 189, 0.4); }
    .ad-hotspot.active { fill: rgba(40, 167, 69, 0.5); stroke: #28a745; }
</style>

<div class="content-header">
    <h2>Visual Ad Placements</h2>
    <a href="manage-ads.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left icon"></i> Back to Ad List</a>
</div>

<?php if(isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">Click an Ad Zone to Assign an Ad</div>
            <div class="card-body layout-preview-container">
                <svg viewBox="0 0 800 600" style="width: 100%; height: auto; border: 1px solid #dee2e6;">
                    <rect x="1" y="1" width="798" height="598" fill="#fff" />
                    <rect x="20" y="20" width="760" height="60" fill="#343a40" /><text x="40" y="55" fill="#fff" font-size="24">Raj News</text>
                    <rect class="ad-hotspot" id="hook-header_top" data-hook="header_top" data-desc="Header Banner" x="50" y="100" width="700" height="70" />
                    <rect x="50" y="190" width="450" height="400" fill="#f8f9fa" />
                    <rect x="530" y="190" width="220" height="300" fill="#f8f9fa" />
                    <rect class="ad-hotspot" id="hook-sidebar_top" data-hook="sidebar_top" data-desc="Sidebar Top" x="530" y="190" width="220" height="180" />
                    <rect class="ad-hotspot" id="hook-middle_of_article" data-hook="middle_of_article" data-desc="Middle of Article" x="50" y="320" width="450" height="60" />
                    <rect class="ad-hotspot" id="hook-after_article_content" data-hook="after_article_content" data-desc="After Article Content" x="50" y="480" width="450" height="90" />
                    <text x="400" y="140" text-anchor="middle" font-size="18" fill="#555" pointer-events="none">Header Banner</text>
                    <text x="640" y="280" text-anchor="middle" font-size="18" fill="#555" pointer-events="none">Sidebar Ad</text>
                    <text x="275" y="355" text-anchor="middle" font-size="18" fill="#555" pointer-events="none">In-Article Ad</text>
                    <text x="275" y="530" text-anchor="middle" font-size="18" fill="#555" pointer-events="none">After Content Ad</text>
                </svg>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-hand-pointer icon"></i> Assignment Panel</div>
            <div class="card-body">
                <p class="text-muted">Click a blue zone on the left to activate this panel.</p>
                <form action="manage-ad-placements.php" method="post" id="assignment-form" class="d-none">
                    <div class="form-group">
                        <label>Selected Ad Zone</label>
                        <input type="text" id="selected-hook-display" class="form-control" value="None" readonly>
                        <input type="hidden" id="selected-hook-input" name="hook_name" value="">
                    </div>
                    <div class="form-group">
                        <label for="ad_id">Assign this Ad to the Zone:</label>
                        <select name="ad_id" id="ad_id" class="form-control">
                            <option value="0">-- None (Deactivate Zone) --</option>
                            <?php mysqli_data_seek($ads_result, 0); // Reset pointer ?>
                            <?php while($ad = mysqli_fetch_assoc($ads_result)): ?>
                                <option value="<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['ad_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="assign_ad" id="assign-btn" class="btn btn-primary btn-icon">
                            <i class="fa-solid fa-check icon"></i> Update Placement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hotspots = document.querySelectorAll('.ad-hotspot');
    const displayField = document.getElementById('selected-hook-display');
    const inputField = document.getElementById('selected-hook-input');
    const adSelect = document.getElementById('ad_id');
    const assignForm = document.getElementById('assignment-form');
    const placements = <?php echo json_encode($placements); ?>;

    hotspots.forEach(spot => {
        spot.addEventListener('click', function() {
            hotspots.forEach(s => s.classList.remove('active'));
            this.classList.add('active');

            const hookName = this.dataset.hook;
            const hookDesc = this.dataset.desc;

            displayField.value = hookDesc;
            inputField.value = hookName;
            
            // Pre-select the currently assigned ad
            adSelect.value = placements[hookName] || '0';

            assignForm.classList.remove('d-none');
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>