<?php
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); exit;
}

// Fetch all ads
$ads = [];
$sql = "SELECT a.*, h.description as hook_description FROM ads a LEFT JOIN ad_hooks h ON a.hook_name = h.hook_name ORDER BY a.created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ads[] = $row;
    }
}
?>
<div class="content-header">
    <h2>Manage Ad Creatives</h2>
    <div>
        <a href="manage-ad-placements.php" class="btn btn-secondary btn-icon"><i class="fa-solid fa-map-location-dot icon"></i> Visual Placements</a>
        <a href="edit-ad.php" class="btn btn-primary btn-icon"><i class="fa-solid fa-plus icon"></i> Add New Creative</a>
    </div>
</div>

<?php if(isset($_GET['status'])): ?>
    <div class="alert alert-success">
    <?php 
        if($_GET['status'] == 'deleted') echo 'Ad creative deleted successfully.';
        if($_GET['status'] == 'updated') echo 'Ad creative updated successfully.';
        if($_GET['status'] == 'added') echo 'Ad creative added successfully.';
    ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-bullhorn icon"></i> All Ad Creatives</div>
    <div class="card-body">
        <!-- DESKTOP VIEW -->
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Name</th><th>Type</th><th>Placement (Hook)</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (!empty($ads)): ?>
                        <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($ad['ad_name']); ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo ucfirst($ad['ad_type']); ?></span></td>
                            <td><?php echo $ad['hook_name'] ? htmlspecialchars($ad['hook_description']) : '<span class="text-muted">Not Placed</span>'; ?></td>
                            <td><?php echo $ad['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></td>
                            <td class='text-end'>
                                <a href='edit-ad.php?id=<?php echo $ad['id']; ?>' class='action-btn edit' title='Edit'><i class='fa-solid fa-pen-to-square'></i></a>
                                <a href='delete-ad.php?id=<?php echo $ad['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure?');"><i class='fa-solid fa-trash-can'></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No ads created yet. Click "Add New Creative" to start.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- MOBILE VIEW -->
        <div class="mobile-card-list">
             <?php if (!empty($ads)): ?>
                <?php foreach ($ads as $ad): ?>
                    <div class="mobile-card">
                        <div class="card-title"><?php echo htmlspecialchars($ad['ad_name']); ?></div>
                        <div class="card-meta">
                           <span><?php echo $ad['hook_name'] ? '<i class="fa-solid fa-map-pin icon"></i> ' . htmlspecialchars($ad['hook_description']) : '<span class="text-muted"><i class="fa-solid fa-map-pin icon"></i> Not Placed</span>'; ?></span>
                        </div>
                         <div class="card-meta">
                            <span><?php echo $ad['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="edit-ad.php?id=<?php echo $ad['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="fa-solid fa-pen-to-square icon"></i> Edit</a>
                            <a href="delete-ad.php?id=<?php echo $ad['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center">No ads created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>