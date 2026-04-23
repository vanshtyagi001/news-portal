    </main>

    <!-- =====================================================
         PROFESSIONAL FOOTER
         ===================================================== -->
    <footer class="site-footer">

        <!-- Top accent bar -->
        <div class="footer-accent-bar"></div>

        <div class="footer-main">
            <div class="container">
                <div class="row gy-5">

                    <!-- Brand column -->
                    <div class="col-lg-4 col-md-6">
                        <div class="footer-brand">
                            <?php
                            // Re-use site settings already fetched by header.php
                            $f_name    = $site_name    ?? 'Express News';
                            $f_tagline = $site_tagline ?? 'Your Daily News Source';
                            $f_logo    = $site_logo    ?? '';
                            ?>
                            <?php if (!empty($f_logo) && file_exists(__DIR__ . '/../' . $f_logo)): ?>
                                <img src="/express-news/<?php echo htmlspecialchars($f_logo); ?>"
                                     alt="<?php echo htmlspecialchars($f_name); ?>"
                                     class="footer-logo mb-3">
                            <?php else: ?>
                                <div class="footer-wordmark mb-3"><?php echo htmlspecialchars($f_name); ?></div>
                            <?php endif; ?>
                            <p class="footer-tagline"><?php echo htmlspecialchars($f_tagline); ?></p>
                            <p class="footer-about">
                                Delivering accurate, timely, and insightful news across politics, technology,
                                sports, business, and culture — keeping you informed every day.
                            </p>
                            <!-- Social icons -->
                            <div class="footer-social">
                                <a href="#" class="footer-social-link" aria-label="Facebook">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                                </a>
                                <a href="#" class="footer-social-link" aria-label="Twitter / X">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                </a>
                                <a href="#" class="footer-social-link" aria-label="Instagram">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                </a>
                                <a href="#" class="footer-social-link" aria-label="YouTube">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                </a>
                                <a href="#" class="footer-social-link" aria-label="Telegram">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="col-lg-2 col-md-6 col-6">
                        <h6 class="footer-heading">Quick Links</h6>
                        <ul class="footer-links">
                            <li><a href="/express-news/">Home</a></li>
                            <li><a href="/express-news/search.php">Search</a></li>
                            <li><a href="/express-news/user/login.php">Login</a></li>
                            <li><a href="/express-news/user/register.php">Register</a></li>
                            <?php if(isset($_SESSION['user_loggedin'])): ?>
                            <li><a href="/express-news/user/profile.php">My Profile</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Categories -->
                    <div class="col-lg-2 col-md-6 col-6">
                        <h6 class="footer-heading">Categories</h6>
                        <ul class="footer-links">
                            <?php
                            if (isset($conn)) {
                                $footer_cats = mysqli_query($conn, "SELECT name, slug FROM categories ORDER BY name ASC LIMIT 7");
                                if ($footer_cats) {
                                    while ($fc = mysqli_fetch_assoc($footer_cats)) {
                                        echo '<li><a href="/express-news/category/' . htmlspecialchars($fc['slug']) . '">' . htmlspecialchars($fc['name']) . '</a></li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </div>

                    <!-- Newsletter / Latest -->
                    <div class="col-lg-4 col-md-6">
                        <h6 class="footer-heading">Stay Updated</h6>
                        <p class="footer-newsletter-text">Get the latest headlines delivered straight to your inbox.</p>
                        <form class="footer-newsletter-form" onsubmit="return false;">
                            <div class="footer-newsletter-input-group">
                                <input type="email" placeholder="Your email address" aria-label="Email address">
                                <button type="submit">Subscribe</button>
                            </div>
                        </form>

                        <!-- Latest 3 posts -->
                        <div class="footer-latest mt-4">
                            <?php
                            if (isset($conn)) {
                                $latest_res = mysqli_query($conn, "SELECT title, slug, created_at FROM posts ORDER BY created_at DESC LIMIT 3");
                                if ($latest_res) {
                                    while ($lp = mysqli_fetch_assoc($latest_res)) {
                                        echo '<a href="/express-news/news/' . htmlspecialchars($lp['slug']) . '" class="footer-latest-item">';
                                        echo '<span class="footer-latest-dot"></span>';
                                        echo '<div>';
                                        echo '<div class="footer-latest-title">' . htmlspecialchars($lp['title']) . '</div>';
                                        echo '<div class="footer-latest-date">' . date('M d, Y', strtotime($lp['created_at'])) . '</div>';
                                        echo '</div>';
                                        echo '</a>';
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-inner">
                    <span class="footer-copyright">
                        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($f_name ?? 'Express News'); ?>. All Rights Reserved.
                    </span>
                    <div class="footer-bottom-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Use</a>
                        <a href="#">Advertise</a>
                        <a href="#">Contact</a>
                    </div>
                </div>
            </div>
        </div>

    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="/express-news/assets/js/script.js"></script>
</body>
</html>
