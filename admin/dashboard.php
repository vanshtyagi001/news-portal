<?php require_once 'includes/header.php'; ?>

<div class="content-header">
    <h2>Dashboard</h2>
</div>

<?php if(isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
    <div class="alert alert-danger">You do not have permission to access that page.</div>
<?php endif; ?>

<div class="dashboard-stats mb-4">
    <div class="stat-card">
        <div class="icon" style="background: var(--primary-color);"><i class="fa-solid fa-newspaper"></i></div>
        <div class="info">
            <h4>Total News Articles</h4>
            <?php $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM posts"); echo "<p>" . mysqli_fetch_assoc($result)['total'] . "</p>"; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background: #17a2b8;"><i class="fa-solid fa-tags"></i></div>
        <div class="info">
            <h4>Total Categories</h4>
            <?php $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories"); echo "<p>" . mysqli_fetch_assoc($result)['total'] . "</p>"; ?>
        </div>
    </div>
     <div class="stat-card">
        <div class="icon" style="background: #28a745;"><i class="fa-solid fa-users-cog"></i></div>
        <div class="info">
            <h4>Admin Accounts</h4>
            <?php $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM admins"); echo "<p>" . mysqli_fetch_assoc($result)['total'] . "</p>"; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background: #ffc107;"><i class="fa-solid fa-comments"></i></div>
        <div class="info">
            <h4>Pending Comments</h4>
            <?php $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM comments WHERE is_approved = 0"); echo "<p>" . mysqli_fetch_assoc($result)['total'] . "</p>"; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-bolt icon"></i> Quick Actions</div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="add-news.php" class="action-card"><i class="fa-solid fa-plus icon"></i><span>Add New Article</span></a>
            <a href="manage-news.php" class="action-card"><i class="fa-solid fa-newspaper icon"></i><span>Manage Articles</span></a>
            <?php if (in_array($_SESSION['admin_role'], ['super_admin', 'editor'])): ?>
            <a href="manage-comments.php" class="action-card"><i class="fa-solid fa-comments icon"></i><span>Moderate Comments</span></a>
            <?php endif; ?>
            <?php if ($_SESSION['admin_role'] == 'super_admin'): ?>
            <a href="manage-categories.php" class="action-card"><i class="fa-solid fa-tags icon"></i><span>Manage Categories</span></a>
            <a href="settings.php" class="action-card"><i class="fa-solid fa-cog icon"></i><span>Site Settings</span></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>