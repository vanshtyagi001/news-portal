<?php require_once 'includes/header.php'; ?>

<div class="content-header">
    <h2>Manage News</h2>
    <a href="add-news.php" class="btn btn-primary btn-icon"><i class="fa-solid fa-plus icon"></i> Add New Article</a>
</div>

<?php if(isset($_GET['status']) || isset($_GET['error'])): ?>
    <div class="alert <?php echo isset($_GET['status']) ? 'alert-success' : 'alert-danger'; ?>">
    <?php 
        if(isset($_GET['status'])) {
            if($_GET['status'] == 'deleted') echo 'Article deleted successfully.';
            if($_GET['status'] == 'updated') echo 'Article updated successfully.';
            if($_GET['status'] == 'added') echo 'Article added successfully.';
        }
        if(isset($_GET['error']) && $_GET['error'] == 'permission_denied') {
            echo 'Access Denied: You do not have permission to perform that action.';
        }
    ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        All News Articles
    </div>
    <div class="card-body">
        <?php
        $limit = 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;

        // --- Fetch total count for pagination ---
        $count_result = mysqli_query($conn, "SELECT COUNT(*) FROM posts");
        $total_results = mysqli_fetch_array($count_result)[0];
        $total_pages = ceil($total_results / $limit);

        //
        // --- THE CRITICAL FIX IS HERE ---
        // This is the robust query to fetch all posts and their associated categories.
        //
        $posts = [];
        $sql = "SELECT 
                    p.id, p.title, p.created_at, p.author_id, 
                    a.username as author_name,
                    (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                     FROM categories c 
                     JOIN post_categories pc ON c.id = pc.category_id 
                     WHERE pc.post_id = p.id) as category_names
                FROM 
                    posts p
                JOIN 
                    admins a ON p.author_id = a.id
                ORDER BY 
                    p.created_at DESC
                LIMIT ?, ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
        }
        mysqli_stmt_close($stmt);
        ?>

        <!-- DESKTOP VIEW: TABLE -->
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Title</th><th>Categories</th><th>Author</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (!empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                            <td><?php echo htmlspecialchars($post['category_names'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($post['author_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                            <td class="text-end">
                                <?php if ($_SESSION['admin_role'] == 'super_admin' || $_SESSION['admin_role'] == 'editor' || $post['author_id'] == $_SESSION['admin_id']): ?>
                                    <a href='edit-news.php?id=<?php echo $post['id']; ?>' class='action-btn edit' title='Edit'><i class='fa-solid fa-pen-to-square'></i></a>
                                    <a href='delete-news.php?id=<?php echo $post['id']; ?>' class='action-btn delete' title='Delete' onclick="return confirm('Are you sure?');"><i class='fa-solid fa-trash-can'></i></a>
                                <?php else: ?>
                                    <span class="text-muted small">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No news articles found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE VIEW: CARD LIST -->
        <div class="mobile-card-list">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="mobile-card">
                        <div class="card-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        <div class="card-meta">
                            <span><i class="fa-solid fa-tag icon"></i> <?php echo htmlspecialchars($post['category_names'] ?? 'Uncategorized'); ?></span>
                            <span><i class="fa-solid fa-user icon"></i> <?php echo htmlspecialchars($post['author_name']); ?></span>
                        </div>
                        <div class="card-actions">
                            <?php if ($_SESSION['admin_role'] == 'super_admin' || $_SESSION['admin_role'] == 'editor' || $post['author_id'] == $_SESSION['admin_id']): ?>
                                <a href="edit-news.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="fa-solid fa-pen-to-square icon"></i> Edit</a>
                                <a href="delete-news.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('Are you sure?');"><i class="fa-solid fa-trash-can icon"></i> Delete</a>
                            <?php else: ?>
                                <p class="text-muted small m-0">No actions permitted.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center">No news articles found.</p>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>