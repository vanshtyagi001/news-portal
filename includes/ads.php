<?php
/**
 * Renders all active ads assigned to a specific hook name.
 *
 * @param mysqli $conn The database connection object.
 * @param string $hook_name The hook to render ads for (e.g., 'header_top').
 */
function display_ads_for_hook($conn, $hook_name) {
    if (!$conn) return;

    $stmt = mysqli_prepare($conn, "SELECT ad_type, ad_content, ad_link FROM ads WHERE hook_name = ? AND is_active = 1 ORDER BY display_order ASC");
    if (!$stmt) return;

    mysqli_stmt_bind_param($stmt, "s", $hook_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($ad = mysqli_fetch_assoc($result)) {
        echo '<div class="ad-container ad-hook-' . htmlspecialchars($hook_name) . ' mb-3">';
        if ($ad['ad_type'] === 'image' && !empty($ad['ad_content'])) {
            $image_url = '/raj-news/' . htmlspecialchars($ad['ad_content']);
            $link_url = !empty($ad['ad_link']) ? htmlspecialchars($ad['ad_link']) : '#';
            echo '<a href="' . $link_url . '" target="_blank" rel="noopener sponsored">';
            echo '<img src="' . $image_url . '" alt="Advertisement" style="max-width: 100%; height: auto; border: 1px solid #ddd;">';
            echo '</a>';
        } elseif ($ad['ad_type'] === 'code' && !empty($ad['ad_content'])) {
            echo $ad['ad_content'];
        }
        echo '</div>';
    }
    mysqli_stmt_close($stmt);
}
?>