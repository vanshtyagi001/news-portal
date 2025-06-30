<?php
// Set the header to output XML
header('Content-Type: application/xml; charset=utf-8');

require_once 'admin/includes/db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain_name . '/raj-news/';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Static Pages (Homepage)
echo "  <url>\n";
echo "    <loc>" . $base_url . "index.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>daily</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// 2. News Articles (Posts)
$post_sql = "SELECT slug, updated_at FROM posts ORDER BY updated_at DESC";
$post_result = mysqli_query($conn, $post_sql);
if ($post_result) {
    while ($row = mysqli_fetch_assoc($post_result)) {
        echo "  <url>\n";
        echo "    <loc>" . $base_url . "news.php?slug=" . htmlspecialchars($row['slug']) . "</loc>\n";
        echo "    <lastmod>" . date('Y-m-d', strtotime($row['updated_at'])) . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.9</priority>\n";
        echo "  </url>\n";
    }
}

// 3. Category Pages
$cat_sql = "SELECT slug FROM categories";
$cat_result = mysqli_query($conn, $cat_sql);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        echo "  <url>\n";
        echo "    <loc>" . $base_url . "category.php?slug=" . htmlspecialchars($row['slug']) . "</loc>\n";
        echo "    <changefreq>daily</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
}

// 4. Tag Pages
$tag_sql = "SELECT slug FROM tags";
$tag_result = mysqli_query($conn, $tag_sql);
if ($tag_result) {
    while ($row = mysqli_fetch_assoc($tag_result)) {
        echo "  <url>\n";
        echo "    <loc>" . $base_url . "tag.php?slug=" . htmlspecialchars($row['slug']) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "  </url>\n";
    }
}

mysqli_close($conn);
echo '</urlset>';
?>