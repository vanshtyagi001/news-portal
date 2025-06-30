<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect user to login page if they are not logged in
if (!isset($_SESSION['user_loggedin'])) { 
    header("Location: login.php"); 
    exit; 
}

require_once __DIR__ . '/../admin/includes/db.php';

$user_id = $_SESSION['user_id'];

// --- Fetch User's Core Data (for sidebar) ---
$sql_user = "SELECT username, email, avatar, created_at FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// If user from session somehow doesn't exist in DB, force logout for security.
if (!$user) {
    header("Location: logout.php");
    exit;
}

// --- Fetch User's Bookmarked Articles ---
$bookmarks = [];
$sql_bookmarks = "SELECT p.title, p.slug, p.summary, p.featured_image, b.created_at as bookmarked_at 
                  FROM user_bookmarks b
                  JOIN posts p ON b.post_id = p.id
                  WHERE b.user_id = ?
                  ORDER BY b.created_at DESC";
$stmt_bookmarks = mysqli_prepare($conn, $sql_bookmarks);
mysqli_stmt_bind_param($stmt_bookmarks, "i", $user_id);
mysqli_stmt_execute($stmt_bookmarks);
$bookmarks_result = mysqli_stmt_get_result($stmt_bookmarks);
while ($row = mysqli_fetch_assoc($bookmarks_result)) {
    $bookmarks[] = $row;
}
mysqli_stmt_close($stmt_bookmarks);

// --- Fetch User's Approved Comments ---
$comments = [];
$sql_comments = "SELECT c.comment, c.created_at, p.title as post_title, p.slug as post_slug
                FROM comments c
                JOIN posts p ON c.post_id = p.id
                WHERE c.name = ? AND c.is_approved = 1
                ORDER BY c.created_at DESC";
$stmt_comments = mysqli_prepare($conn, $sql_comments);
mysqli_stmt_bind_param($stmt_comments, "s", $user['username']);
mysqli_stmt_execute($stmt_comments);
$comments_result = mysqli_stmt_get_result($stmt_comments);
while ($row = mysqli_fetch_assoc($comments_result)) {
    $comments[] = $row;
}
mysqli_stmt_close($stmt_comments);

$page_title = $user['username'] . "'s Profile";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Profile Page Header -->
<div class="profile-page-header">
    <h2>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?>!</strong></h2>
    <p class="lead mb-0">Manage your profile, view your activity, and more.</p>
</div>

<div class="row">
    <!-- Left Sidebar Column -->
    <div class="col-lg-4 mb-4">
        <!-- Profile Avatar Card -->
        <div class="card profile-avatar-card shadow-sm mb-4">
            <div class="card-body">
                <?php 
                $avatar_path = !empty($user['avatar']) ? '/raj-news/' . $user['avatar'] : 'https://dummyimage.com/150x150/ced4da/6c757d.jpg'; 
                ?>
                <img src="<?php echo $avatar_path; ?>" class="rounded-circle profile-avatar" alt="User Avatar">
                <h4 class="card-title mt-3 mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                <p class="card-text text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <!-- Navigation Card -->
        <div class="card profile-nav shadow-sm">
            <div class="list-group list-group-flush">
                <?php $active_tab = $_GET['tab'] ?? 'comments'; ?>
                <a href="profile.php?tab=comments" class="list-group-item list-group-item-action <?php echo ($active_tab == 'comments') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-comments"></i> Comment History
                </a>
                <a href="profile.php?tab=bookmarks" class="list-group-item list-group-item-action <?php echo ($active_tab == 'bookmarks') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-bookmark"></i> My Bookmarks
                </a>
                <a href="edit-profile.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile & Avatar
                </a>
                <a href="change-password.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-key"></i> Change Password
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Right Content Column -->
    <div class="col-lg-8">
        <?php if ($active_tab == 'bookmarks'): ?>
        <!-- BOOKMARKS CONTENT -->
        <div class="card profile-content shadow-sm">
            <div class="card-header">My Bookmarks (<?php echo count($bookmarks); ?>)</div>
            <div class="card-body">
                <?php if(!empty($bookmarks)): ?>
                    <?php foreach($bookmarks as $bookmark): ?>
                        <a href="/raj-news/news.php?slug=<?php echo $bookmark['slug']; ?>" class="related-article-card mb-3">
                            <img src="/raj-news/<?php echo htmlspecialchars($bookmark['featured_image']); ?>" alt="<?php echo htmlspecialchars($bookmark['title']); ?>" class="related-article-img">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($bookmark['title']); ?></h6>
                                <p class="card-text"><?php echo htmlspecialchars($bookmark['summary']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted mt-3">You haven't bookmarked any articles yet. Click the "Save for Later" button on an article to add it to your collection.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- COMMENTS CONTENT (DEFAULT) -->
        <div class="card profile-content shadow-sm">
            <div class="card-header">My Comment History (<?php echo count($comments); ?>)</div>
            <div class="card-body">
                <?php if(!empty($comments)): ?>
                    <?php foreach($comments as $comment): ?>
                        <div class="comment-history-item">
                            <blockquote><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></blockquote>
                            <p class="mb-0">
                                <a href="/raj-news/news.php?slug=<?php echo $comment['post_slug']; ?>" class="meta-link">
                                    Commented on <strong><?php echo htmlspecialchars($comment['post_title']); ?></strong>
                                </a>
                                <span class="text-muted ms-2">- <?php echo date('F j, Y', strtotime($comment['created_at'])); ?></span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted mt-3">You have not made any approved comments yet. Your comments will appear here once they are approved by an admin.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once __DIR__ . '/../includes/footer.php'; 
?>