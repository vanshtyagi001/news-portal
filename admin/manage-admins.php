<?php
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("location: dashboard.php?error=access_denied"); 
    exit;
}

// Fetch all admins from the database to display
$admins = [];
$sql = "SELECT id, username, full_name, role, created_at FROM admins ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
}
?>
<div class="content-header">
    <h2>Manage Admin Accounts</h2>
    <!-- The "Add Admin" button can simply link to the existing signup page -->
    <a href="signup.php" class="btn btn-primary btn-icon"><i class="fa-solid fa-user-plus icon"></i> Add New Admin</a>
</div>

<?php if(isset($_GET['status'])): ?>
    <div class="alert alert-success">
    <?php 
        if($_GET['status'] == 'deleted') echo 'Admin account deleted successfully.';
        if($_GET['status'] == 'updated') echo 'Admin account updated successfully.';
    ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-users-cog icon"></i> All Admin Users
    </div>
    <div class="card-body">
        <!-- DESKTOP VIEW: TABLE -->
        <div class="table-wrapper">
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (!empty($admins)): ?>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo $admin['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><span class="author-role <?php echo str_replace('_', '-', htmlspecialchars($admin['role'])); ?>"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($admin['role']))); ?></span></td>
                            <td>
                                <a href='edit-admin.php?id=<?php echo $admin['id']; ?>' class='action-btn edit' title='Edit'><i class='fa-solid fa-pen-to-square'></i></a>
                                <?php 
                                // Prevent a super_admin from deleting their own account
                                if ($admin['id'] != $_SESSION['admin_id']): 
                                ?>
                                    <a href='delete-admin.php?id=<?php echo $admin['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure you want to delete this admin account? This action cannot be undone.');"><i class='fa-solid fa-trash-can'></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No admin accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE VIEW: CARD LIST -->
        <div class="mobile-card-list">
            <?php if (!empty($admins)): ?>
                <?php foreach ($admins as $admin): ?>
                    <div class="mobile-card">
                        <div class="card-title"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                        <p class="text-muted mb-2">@<?php echo htmlspecialchars($admin['username']); ?></p>
                        <div class="card-meta">
                            <span class="author-role <?php echo str_replace('_', '-', htmlspecialchars($admin['role'])); ?>"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($admin['role']))); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="edit-admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="fa-solid fa-pen-to-square icon"></i> Edit</a>
                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                <a href="delete-admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center">No admin accounts found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>