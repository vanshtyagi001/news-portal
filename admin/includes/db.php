<?php
/**
 * Express News - Database Connection & Core Functions
 * This file is the single source of truth for database connections and helper functions.
 * It's included by other files to gain access to the database and utilities.
 */

// --- Error Reporting ---
// Useful for development to see all errors. Should be commented out or set to 0 for a live production website.
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// --- Database Configuration ---
// These constants define the connection details for your MySQL database.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'express_news_db');

// --- Establish Database Connection ---
// This creates the $conn variable that all other scripts will use to interact with the database.
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check the connection and stop the entire application if it fails.
// This is a critical check to prevent the site from running with a broken database link.
if ($conn === false) {
    // In a live environment, you might log this error to a file instead of showing it to the user.
    die("FATAL ERROR: Could not connect to the database. " . mysqli_connect_error());
}

// --- Helper Functions ---

/**
 * Creates a URL-friendly "slug" from a string (e.g., a post title).
 * "My Awesome Post!" becomes "my-awesome-post".
 *
 * @param string $string The input string.
 * @return string The sanitized, lowercased, hyphenated slug.
 */
function create_slug($string) {
   // 1. Convert to lowercase
   $string = strtolower($string);
   // 2. Remove all characters that are not letters, numbers, spaces, or hyphens
   $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
   // 3. Replace all spaces and consecutive hyphens with a single hyphen
   $string = preg_replace('/[\s-]+/', '-', $string);
   // 4. Remove any leading or trailing hyphens
   return trim($string, '-');
}

/**
 * Checks whether core GD functions are available for image optimization.
 */
function is_gd_available(): bool {
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagejpeg');
}

// ─────────────────────────────────────────────────────────────────────────────
//  MEDIA SYSTEM v2
//  Generates: thumbnail (320px), medium (768px), large (1440px) + WebP fallback
//  Naming:    YYYYMMDDHHMMSS_<6-char-hex>
//  Storage:   uploads/YYYY/MM/
// ─────────────────────────────────────────────────────────────────────────────

/** Image variant definitions: [suffix, max_width, quality] */
const MEDIA_VARIANTS = [
    'thumb'  => [320,  80],
    'medium' => [768,  82],
    'large'  => [1440, 85],
];

/**
 * Generate a unique base filename: YYYYMMDDHHMMSS_<6-char-hex>
 */
function media_generate_basename(): string {
    return date('YmdHis') . '_' . bin2hex(random_bytes(3));
}

/**
 * Ensure the date-based upload directory exists and return its absolute path.
 * Creates uploads/YYYY/MM/ if needed.
 *
 * @param string $base_dir  Absolute path to the uploads root (with trailing slash).
 * @return string  Absolute path to the YYYY/MM/ folder (with trailing slash).
 */
function media_ensure_dir(string $base_dir): string {
    $dir = $base_dir . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Return the public URL prefix for a storage_path value (e.g. "2026/04").
 */
function media_public_prefix(string $storage_path): string {
    return '/express-news/uploads/' . str_replace('\\', '/', $storage_path) . '/';
}

/**
 * Load a GD image resource from a file, regardless of type.
 *
 * @return resource|GdImage|false
 */
function media_load_gd(string $path, int $type) {
    switch ($type) {
        case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:  return imagecreatefrompng($path);
        case IMAGETYPE_GIF:  return imagecreatefromgif($path);
        case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
        default:             return false;
    }
}

/**
 * Resize a GD resource to fit within $max_width, preserving aspect ratio.
 *
 * @return resource|GdImage
 */
function media_resize_gd($src, int $src_w, int $src_h, int $max_width) {
    if ($src_w <= $max_width) {
        // No resize needed — clone
        $dst = imagecreatetruecolor($src_w, $src_h);
        imagecopy($dst, $src, 0, 0, 0, 0, $src_w, $src_h);
        return $dst;
    }
    $ratio  = $src_h / $src_w;
    $new_w  = $max_width;
    $new_h  = (int)round($max_width * $ratio);
    $dst    = imagecreatetruecolor($new_w, $new_h);
    // Preserve transparency
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
    return $dst;
}

/**
 * Process an uploaded image file:
 *  - Generates thumbnail, medium, large variants as WebP (primary) + JPEG (fallback)
 *  - Saves into uploads/YYYY/MM/
 *  - Returns metadata array or false on failure
 *
 * @param string $tmp_path   PHP tmp file path
 * @param string $upload_root  Absolute path to uploads/ root (trailing slash)
 * @return array|false  [
 *   'base_name'    => string,
 *   'storage_path' => string,   // e.g. "2026/04"
 *   'file_size'    => int,      // bytes of the large variant
 *   'image_width'  => int,
 *   'image_height' => int,
 *   'has_thumbnail'=> bool,
 *   'has_medium'   => bool,
 *   'has_large'    => bool,
 *   'has_webp'     => bool,
 * ]
 */
function media_process_image(string $tmp_path, string $upload_root): array|false {
    $info = @getimagesize($tmp_path);
    if (!$info) return false;

    [$orig_w, $orig_h, $type] = $info;

    // GD fallback: just copy the original if GD is unavailable
    if (!is_gd_available()) {
        $ext_map = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
        $ext     = $ext_map[$type] ?? 'jpg';
        $base    = media_generate_basename();
        $dir     = media_ensure_dir($upload_root);
        $storage = date('Y') . '/' . date('m');
        if (copy($tmp_path, $dir . $base . '.' . $ext)) {
            return [
                'base_name'    => $base,
                'storage_path' => $storage,
                'file_size'    => filesize($dir . $base . '.' . $ext),
                'image_width'  => $orig_w,
                'image_height' => $orig_h,
                'has_thumbnail'=> false,
                'has_medium'   => false,
                'has_large'    => false,
                'has_webp'     => false,
            ];
        }
        return false;
    }

    $src = media_load_gd($tmp_path, $type);
    if (!$src) return false;

    $base    = media_generate_basename();
    $dir     = media_ensure_dir($upload_root);
    $storage = date('Y') . '/' . date('m');
    $results = [];

    foreach (MEDIA_VARIANTS as $suffix => [$max_w, $quality]) {
        $resized = media_resize_gd($src, $orig_w, $orig_h, $max_w);

        // Primary: WebP
        $webp_path = $dir . $base . '_' . $suffix . '.webp';
        $has_webp  = function_exists('imagewebp') && imagewebp($resized, $webp_path, $quality);

        // Fallback: JPEG
        $jpg_path  = $dir . $base . '_' . $suffix . '.jpg';
        $has_jpg   = imagejpeg($resized, $jpg_path, $quality);

        // Remove JPEG if WebP succeeded (save space)
        if ($has_webp && $has_jpg) {
            @unlink($jpg_path);
            $has_jpg = false;
        }

        imagedestroy($resized);
        $results[$suffix] = $has_webp || $has_jpg;
    }

    imagedestroy($src);

    // File size = large variant
    $large_webp = $dir . $base . '_large.webp';
    $large_jpg  = $dir . $base . '_large.jpg';
    $file_size  = file_exists($large_webp) ? filesize($large_webp)
                : (file_exists($large_jpg) ? filesize($large_jpg) : 0);

    return [
        'base_name'    => $base,
        'storage_path' => $storage,
        'file_size'    => $file_size,
        'image_width'  => $orig_w,
        'image_height' => $orig_h,
        'has_thumbnail'=> $results['thumb']  ?? false,
        'has_medium'   => $results['medium'] ?? false,
        'has_large'    => $results['large']  ?? false,
        'has_webp'     => function_exists('imagewebp'),
    ];
}

/**
 * Delete all generated variants for a media record.
 *
 * @param string $base_name    e.g. "20260423153045_a8f9c2"
 * @param string $storage_path e.g. "2026/04"
 * @param string $upload_root  Absolute path to uploads/ root
 */
function media_delete_files(string $base_name, string $storage_path, string $upload_root): void {
    $dir = rtrim($upload_root, '/\\') . DIRECTORY_SEPARATOR
         . str_replace('/', DIRECTORY_SEPARATOR, $storage_path) . DIRECTORY_SEPARATOR;

    $suffixes = ['thumb', 'medium', 'large'];
    $exts     = ['webp', 'jpg', 'mp4', 'pdf', 'doc', 'docx', 'txt'];

    foreach ($suffixes as $s) {
        foreach (['webp', 'jpg'] as $e) {
            $f = $dir . $base_name . '_' . $s . '.' . $e;
            if (file_exists($f)) @unlink($f);
        }
    }
    // Original / non-image files
    foreach ($exts as $e) {
        $f = $dir . $base_name . '.' . $e;
        if (file_exists($f)) @unlink($f);
    }
    // Video thumbnail
    $vt = $dir . $base_name . '_poster.jpg';
    if (file_exists($vt)) @unlink($vt);
}

/**
 * Build a URL set for a media record.
 * Returns an array with keys: thumbnail, medium, large, original
 * Each value is a public URL string (best available format).
 *
 * @param array $media  Row from the media table
 * @return array
 */
function media_urls(array $media): array {
    $prefix  = media_public_prefix($media['storage_path']);
    $base    = $media['base_name'];
    $default = '/express-news/assets/images/placeholder.jpg';

    $upload_root = __DIR__ . '/../../uploads/';
    $dir = rtrim($upload_root, '/\\') . DIRECTORY_SEPARATOR
         . str_replace('/', DIRECTORY_SEPARATOR, $media['storage_path']) . DIRECTORY_SEPARATOR;

    // Helper: find the best existing file for a given suffix
    $url = function(string $suffix) use ($dir, $prefix, $base): string {
        foreach (['webp', 'jpg', 'png', 'gif'] as $e) {
            if (file_exists($dir . $base . '_' . $suffix . '.' . $e)) {
                return $prefix . $base . '_' . $suffix . '.' . $e;
            }
        }
        return '';
    };

    // Helper: find the plain file (GD-unavailable fallback or non-image)
    $plain_url = function() use ($dir, $prefix, $base): string {
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp'] as $e) {
            if (file_exists($dir . $base . '.' . $e)) {
                return $prefix . $base . '.' . $e;
            }
        }
        return '';
    };

    $thumb  = $url('thumb');
    $medium = $url('medium');
    $large  = $url('large');
    $plain  = $plain_url();

    // If no variants exist (GD unavailable), use the plain file for everything
    $fallback = $large ?: $medium ?: $thumb ?: $plain ?: $default;

    return [
        'thumbnail' => $thumb  ?: $plain ?: $default,
        'medium'    => $medium ?: $plain ?: $default,
        'large'     => $large  ?: $plain ?: $default,
        'original'  => $fallback,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
//  LEGACY COMPATIBILITY — keeps existing post featured-image code working
// ─────────────────────────────────────────────────────────────────────────────

/**
 * getImagePaths() — backward-compatible wrapper.
 * Accepts either:
 *   (a) old format: "post_1234567890_name" (basename without ext, stored in uploads/)
 *   (b) new format: "2026/04/20260423153045_a8f9c2" (storage_path/base_name)
 */
function getImagePaths(?string $db_image_value): array {
    $default_jpg  = '/express-news/assets/images/placeholder.jpg';
    $default_webp = '/express-news/assets/images/placeholder.webp';

    if (empty($db_image_value)) {
        return ['webp' => $default_webp, 'jpg' => $default_jpg];
    }

    $normalized = ltrim(str_replace('\\', '/', trim($db_image_value)), '/');

    // ── New format: contains a slash → storage_path/base_name ──────────────
    if (substr_count($normalized, '/') >= 2) {
        // e.g. "2026/04/20260423153045_a8f9c2"
        $last_slash   = strrpos($normalized, '/');
        $storage_path = substr($normalized, 0, $last_slash);
        $base_name    = substr($normalized, $last_slash + 1);
        $prefix       = '/express-news/uploads/' . $storage_path . '/';
        $upload_root  = __DIR__ . '/../../uploads/';
        $dir          = rtrim($upload_root, '/\\') . DIRECTORY_SEPARATOR
                      . str_replace('/', DIRECTORY_SEPARATOR, $storage_path) . DIRECTORY_SEPARATOR;

        foreach (['large', 'medium', 'thumb'] as $s) {
            if (file_exists($dir . $base_name . '_' . $s . '.webp')) {
                return [
                    'webp' => $prefix . $base_name . '_' . $s . '.webp',
                    'jpg'  => file_exists($dir . $base_name . '_' . $s . '.jpg')
                              ? $prefix . $base_name . '_' . $s . '.jpg'
                              : $prefix . $base_name . '_' . $s . '.webp',
                ];
            }
            if (file_exists($dir . $base_name . '_' . $s . '.jpg')) {
                return [
                    'webp' => $prefix . $base_name . '_' . $s . '.jpg',
                    'jpg'  => $prefix . $base_name . '_' . $s . '.jpg',
                ];
            }
        }
        return ['webp' => $default_webp, 'jpg' => $default_jpg];
    }

    // ── Old format: flat uploads/ directory ────────────────────────────────
    $uploads_abs = __DIR__ . '/../../uploads/';
    $uploads_pub = '/express-news/uploads/';
    $file_name   = basename($normalized);
    $image_base  = pathinfo($file_name, PATHINFO_FILENAME);
    $image_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (empty($image_base)) {
        return ['webp' => $default_webp, 'jpg' => $default_jpg];
    }

    $jpg_abs  = $uploads_abs . $image_base . '.jpg';
    $webp_abs = $uploads_abs . $image_base . '.webp';

    if (file_exists($jpg_abs) || file_exists($webp_abs)) {
        $jpg_url  = file_exists($jpg_abs)  ? $uploads_pub . $image_base . '.jpg'  : $default_jpg;
        $webp_url = file_exists($webp_abs) ? $uploads_pub . $image_base . '.webp' : $jpg_url;
        return ['webp' => $webp_url, 'jpg' => $jpg_url];
    }

    if (!empty($image_ext) && file_exists($uploads_abs . $file_name)) {
        $url = $uploads_pub . $file_name;
        return ['webp' => $url, 'jpg' => $url];
    }

    return ['webp' => $default_webp, 'jpg' => $default_jpg];
}

/**
 * delete_post_image_files() — backward-compatible, handles both old and new paths.
 */
function delete_post_image_files(?string $db_image_value, ?string $upload_dir = null): void {
    if (empty($db_image_value)) return;

    $normalized = ltrim(str_replace('\\', '/', trim($db_image_value)), '/');

    // New format
    if (substr_count($normalized, '/') >= 2) {
        $last_slash   = strrpos($normalized, '/');
        $storage_path = substr($normalized, 0, $last_slash);
        $base_name    = substr($normalized, $last_slash + 1);
        $root         = $upload_dir ?: (__DIR__ . '/../../uploads/');
        media_delete_files($base_name, $storage_path, $root);
        return;
    }

    // Old format
    $root      = $upload_dir ?: (__DIR__ . '/../../uploads/');
    $root      = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
    $file_name = basename($normalized);
    $base_name = pathinfo($file_name, PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $candidates = [
        $root . $base_name . '.jpg',
        $root . $base_name . '.webp',
        $root . $base_name . '.jpeg',
        $root . $base_name . '.png',
        $root . $base_name . '.gif',
    ];
    if (!empty($extension)) $candidates[] = $root . $file_name;

    foreach (array_unique($candidates) as $path) {
        if (file_exists($path)) @unlink($path);
    }
}

// Keep old optimize_image() so any code that still calls it doesn't break
function optimize_image(string $source_path, string $destination_path_no_ext, int $max_width = 1200, int $quality = 75): string|false {
    $info = @getimagesize($source_path);
    if (!$info) return false;
    [$w, $h, $type] = $info;
    $ext_map = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
    $src_ext = $ext_map[$type] ?? '';
    if (!$src_ext) return false;

    if (!is_gd_available()) {
        $dst = $destination_path_no_ext . '.' . $src_ext;
        return copy($source_path, $dst) ? basename($dst) : false;
    }

    $src = media_load_gd($source_path, $type);
    if (!$src) return false;

    $resized = media_resize_gd($src, $w, $h, $max_width);
    imagedestroy($src);

    $jpg  = $destination_path_no_ext . '.jpg';
    $webp = $destination_path_no_ext . '.webp';
    $ok_jpg  = imagejpeg($resized, $jpg, $quality);
    $ok_webp = function_exists('imagewebp') ? imagewebp($resized, $webp, $quality) : false;
    imagedestroy($resized);

    if ($ok_jpg && $ok_webp) return basename($destination_path_no_ext);
    if ($ok_jpg)             return basename($jpg);
    return false;
}


?>