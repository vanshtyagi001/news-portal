<?php
require_once 'includes/header.php';

// --- ROLE-BASED ACCESS CONTROL ---
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['super_admin', 'editor'])) {
    header("location: dashboard.php?error=access_denied"); 
    exit;
}

// --- ACTION HANDLING ---
// Handle Approve Action
if (isset($_GET['approve'])) {
    $comment_id = (int)$_GET['approve'];
    $sql = "UPDATE comments SET is_approved = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    mysqli_stmt_execute($stmt);
    header("Location: manage-comments.php?status=approved");
    exit();
}
// Handle Delete Action
if (isset($_GET['delete'])) {
    $comment_id = (int)$_GET['delete'];
    $sql = "DELETE FROM comments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    mysqli_stmt_execute($stmt);
    header("Location: manage-comments.php?status=deleted");
    exit();
}
?>
<div class="content-header">
    <h2>Manage Comments</h2>
</div>

<?php if(isset($_GET['status'])): ?>
    <div class="alert alert-success">
    <?php 
        if($_GET['status'] == 'deleted') echo 'Comment deleted successfully.';
        if($_GET['status'] == 'approved') echo 'Comment approved successfully.';
    ?>
    </div>
<?php endif; ?>

<!-- PENDING COMMENTS CARD -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa-solid fa-hourglass-half icon"></i> Pending Approval
    </div>
    <div class="card-body">
        <?php
        // Fetch pending comments and store them in an array
        $pending_comments = [];
        $sql_pending = "SELECT c.id, c.name, c.comment, c.created_at, p.title as post_title, p.slug as post_slug FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.is_approved = 0 ORDER BY c.created_at DESC";
        $result_pending = mysqli_query($conn, $sql_pending);
        if ($result_pending) {
            while ($row = mysqli_fetch_assoc($result_pending)) {
                $pending_comments[] = $row;
            }
        }
        ?>
        <!-- DESKTOP VIEW -->
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Commenter</th><th>Comment</th><th>In Response To</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!empty($pending_comments)): ?>
                    <?php foreach ($pending_comments as $comment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comment['name']); ?></td>
                            <td style='white-space:normal; min-width: 250px;'><?php echo htmlspecialchars($comment['comment']); ?></td>
                            <td><a href='../news.php?slug=<?php echo $comment['post_slug']; ?>' target='_blank' title="View Article"><?php echo htmlspecialchars($comment['post_title']); ?></a></td>
                            <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                            <td class='text-end'>
                                <a href='manage-comments.php?approve=<?php echo $comment['id']; ?>' class='action-btn approve' title='Approve'><i class='fa-solid fa-check'></i></a>
                                <a href='manage-comments.php?delete=<?php echo $comment['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure?');"><i class='fa-solid fa-trash-can'></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan='5' class='text-center'>No pending comments.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- MOBILE VIEW -->
        <div class="mobile-card-list">
            <?php if (!empty($pending_comments)): ?>
                <?php foreach ($pending_comments as $comment): ?>
                    <div class="mobile-card">
                        <p class="fst-italic">"<?php echo htmlspecialchars($comment['comment']); ?>"</p>
                        <div class="card-meta">
                            <span><i class="fa-solid fa-user icon"></i> <?php echo htmlspecialchars($comment['name']); ?></span>
                        </div>
                        <div class="card-meta">
                            <span><i class="fa-solid fa-newspaper icon"></i> In response to: <?php echo htmlspecialchars($comment['post_title']); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="manage-comments.php?approve=<?php echo $comment['id']; ?>" class="btn btn-sm btn-success btn-icon"><i class="fa-solid fa-check icon"></i> Approve</a>
                            <a href="manage-comments.php?delete=<?php echo $comment['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                 <p class="text-center">No pending comments.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- APPROVED COMMENTS CARD - THIS IS THE FIXED SECTION -->
<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-comment-dots icon"></i> Recently Approved Comments
    </div>
    <div class="card-body">
        <?php
        // Fetch approved comments and store them in an array
        $approved_comments = [];
        $sql_approved = "SELECT c.id, c.name, c.comment, c.created_at, p.title as post_title, p.slug as post_slug FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.is_approved = 1 ORDER BY c.created_at DESC LIMIT 15";
        $result_approved = mysqli_query($conn, $sql_approved);
        if ($result_approved) {
            while ($row = mysqli_fetch_assoc($result_approved)) {
                $approved_comments[] = $row;
            }
        }
        ?>
        <!-- DESKTOP VIEW -->
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Commenter</th><th>Comment</th><th>In Response To</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!empty($approved_comments)): ?>
                    <?php foreach ($approved_comments as $comment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comment['name']); ?></td>
                            <td style='white-space:normal; min-width: 250px;'><?php echo htmlspecialchars($comment['comment']); ?></td>
                            <td><a href='../news.php?slug=<?php echo $comment['post_slug']; ?>' target='_blank' title="View Article"><?php echo htmlspecialchars($comment['post_title']); ?></a></td>
                            <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                            <td class='text-end'>
                                <a href='manage-comments.php?delete=<?php echo $comment['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure?');"><i class='fa-solid fa-trash-can'></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan='5' class='text-center'>No approved comments yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- MOBILE VIEW -->
        <div class="mobile-card-list">
            <?php if (!empty($approved_comments)): ?>
                <?php foreach ($approved_comments as $comment): ?>
                    <div class="mobile-card">
                        <p class="fst-italic">"<?php echo htmlspecialchars($comment['comment']); ?>"</p>
                        <div class="card-meta">
                            <span><i class="fa-solid fa-user icon"></i> <?php echo htmlspecialchars($comment['name']); ?></span>
                        </div>
                        <div class="card-meta">
                            <span><i class="fa-solid fa-newspaper icon"></i> In response to: <?php echo htmlspecialchars($comment['post_title']); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="manage-comments.php?delete=<?php echo $comment['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                 <p class="text-center">No approved comments yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>