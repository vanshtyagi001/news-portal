<?php
/**
 * Express News — Media Upload Handler v2
 *
 * Handles single-file uploads via XHR (from the media library JS).
 * Images  → multi-size WebP variants (thumb/medium/large) stored in uploads/YYYY/MM/
 * Videos  → stored as-is in uploads/YYYY/MM/ (MP4 conversion requires FFmpeg)
 * Docs    → stored as-is in uploads/YYYY/MM/
 *
 * Naming: YYYYMMDDHHMMSS_<6-char-hex>.<ext>
 * Returns JSON: { success, id, urls, ... } or { error }
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_loggedin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

// ── Validate file ─────────────────────────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was sent.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension.',
    ];
    echo json_encode(['error' => $msgs[$code] ?? 'Unknown upload error.']);
    exit;
}

$file        = $_FILES['file'];
$tmp_path    = $file['tmp_name'];
$orig_name   = basename($file['name']);
$uploader_id = (int)$_SESSION['admin_id'];
$upload_root = __DIR__ . '/../uploads/';

// ── Detect MIME (use finfo, not browser-supplied type) ────────────────────────
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $tmp_path);
finfo_close($finfo);

// Allowed MIME types
$allowed = [
    // Images
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff',
    // Video
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska',
    'video/webm', 'video/mpeg',
    // Documents
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
];

if (!in_array($mime_type, $allowed, true)) {
    echo json_encode(['error' => 'File type not allowed: ' . htmlspecialchars($mime_type)]);
    exit;
}

// ── Max file size: 100 MB ─────────────────────────────────────────────────────
$max_bytes = 100 * 1024 * 1024;
if ($file['size'] > $max_bytes) {
    echo json_encode(['error' => 'File too large. Maximum size is 100 MB.']);
    exit;
}

// ── Route by type ─────────────────────────────────────────────────────────────
$is_image = str_starts_with($mime_type, 'image/');
$is_video = str_starts_with($mime_type, 'video/');

$db_row = null;

if ($is_image) {
    // ── Image: generate thumb/medium/large WebP variants ─────────────────────
    $result = media_process_image($tmp_path, $upload_root);

    if (!$result) {
        echo json_encode(['error' => 'Image processing failed. Ensure GD is enabled.']);
        exit;
    }

    $db_row = [
        'base_name'    => $result['base_name'],
        'storage_path' => $result['storage_path'],
        'original_name'=> $orig_name,
        'mime_type'    => $mime_type,
        'file_size'    => $result['file_size'],
        'has_thumbnail'=> (int)$result['has_thumbnail'],
        'has_medium'   => (int)$result['has_medium'],
        'has_large'    => (int)$result['has_large'],
        'has_webp'     => (int)$result['has_webp'],
        'image_width'  => $result['image_width'],
        'image_height' => $result['image_height'],
        'video_thumb'  => null,
        'video_duration'=> null,
        'title'        => pathinfo($orig_name, PATHINFO_FILENAME),
        'alt_text'     => pathinfo($orig_name, PATHINFO_FILENAME),
    ];

} elseif ($is_video) {
    // ── Video: store original, attempt FFmpeg thumbnail ───────────────────────
    $base    = media_generate_basename();
    $dir     = media_ensure_dir($upload_root);
    $storage = date('Y') . '/' . date('m');

    // Determine extension
    $ext_map = [
        'video/mp4'       => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-matroska'=> 'mkv',
        'video/webm'      => 'webm',
        'video/mpeg'      => 'mpg',
    ];
    $ext      = $ext_map[$mime_type] ?? 'mp4';
    $dst_path = $dir . $base . '.' . $ext;

    if (!move_uploaded_file($tmp_path, $dst_path)) {
        echo json_encode(['error' => 'Failed to save video file.']);
        exit;
    }

    // Try FFmpeg poster frame (1 second in)
    $poster_path    = $dir . $base . '_poster.jpg';
    $video_thumb_db = null;
    $duration       = null;

    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $ffmpeg = trim((string)shell_exec('which ffmpeg 2>/dev/null'));
        if ($ffmpeg) {
            $escaped_src    = escapeshellarg($dst_path);
            $escaped_poster = escapeshellarg($poster_path);
            exec("$ffmpeg -y -i $escaped_src -ss 00:00:01 -vframes 1 -q:v 3 $escaped_poster 2>/dev/null");
            if (file_exists($poster_path) && filesize($poster_path) > 0) {
                $video_thumb_db = $storage . '/' . $base . '_poster.jpg';
            }
            // Get duration
            $probe = shell_exec("$ffmpeg -i $escaped_src 2>&1 | grep Duration");
            if ($probe && preg_match('/Duration:\s*(\d+):(\d+):(\d+)/', $probe, $m)) {
                $duration = (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
            }
        }
    }

    $db_row = [
        'base_name'     => $base,
        'storage_path'  => $storage,
        'original_name' => $orig_name,
        'mime_type'     => $mime_type,
        'file_size'     => filesize($dst_path),
        'has_thumbnail' => 0,
        'has_medium'    => 0,
        'has_large'     => 0,
        'has_webp'      => 0,
        'image_width'   => null,
        'image_height'  => null,
        'video_thumb'   => $video_thumb_db,
        'video_duration'=> $duration,
        'title'         => pathinfo($orig_name, PATHINFO_FILENAME),
        'alt_text'      => '',
    ];

} else {
    // ── Document / other: store as-is ─────────────────────────────────────────
    $base    = media_generate_basename();
    $dir     = media_ensure_dir($upload_root);
    $storage = date('Y') . '/' . date('m');
    $ext     = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) ?: 'bin';
    $dst_path = $dir . $base . '.' . $ext;

    if (!move_uploaded_file($tmp_path, $dst_path)) {
        echo json_encode(['error' => 'Failed to save file.']);
        exit;
    }

    $db_row = [
        'base_name'     => $base,
        'storage_path'  => $storage,
        'original_name' => $orig_name,
        'mime_type'     => $mime_type,
        'file_size'     => filesize($dst_path),
        'has_thumbnail' => 0,
        'has_medium'    => 0,
        'has_large'     => 0,
        'has_webp'      => 0,
        'image_width'   => null,
        'image_height'  => null,
        'video_thumb'   => null,
        'video_duration'=> null,
        'title'         => pathinfo($orig_name, PATHINFO_FILENAME),
        'alt_text'      => '',
    ];
}

// ── Insert into DB ────────────────────────────────────────────────────────────
// 16 params: s s s s i  i i i i  i i s i  s s i
$sql = "INSERT INTO media
            (base_name, storage_path, original_name, mime_type, file_size,
             has_thumbnail, has_medium, has_large, has_webp,
             image_width, image_height, video_thumb, video_duration,
             title, alt_text, uploader_id)
        VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?)";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(['error' => 'DB prepare failed: ' . mysqli_error($conn)]);
    exit;
}

// Bind nullable ints — PHP passes NULL correctly when the variable is null
$p_base     = $db_row['base_name'];
$p_storage  = $db_row['storage_path'];
$p_origname = $db_row['original_name'];
$p_mime     = $db_row['mime_type'];
$p_size     = (int)$db_row['file_size'];
$p_thumb    = (int)$db_row['has_thumbnail'];
$p_medium   = (int)$db_row['has_medium'];
$p_large    = (int)$db_row['has_large'];
$p_webp     = (int)$db_row['has_webp'];
$p_width    = isset($db_row['image_width'])   ? (int)$db_row['image_width']   : null;
$p_height   = isset($db_row['image_height'])  ? (int)$db_row['image_height']  : null;
$p_vthumb   = $db_row['video_thumb'];          // string or null
$p_vdur     = isset($db_row['video_duration']) ? (int)$db_row['video_duration'] : null;
$p_title    = $db_row['title'];
$p_alt      = $db_row['alt_text'];
$p_uid      = $uploader_id;

mysqli_stmt_bind_param(
    $stmt,
    'ssssiiiiiiisissi',   // 16 type chars, no spaces
    $p_base,
    $p_storage,
    $p_origname,
    $p_mime,
    $p_size,
    $p_thumb,
    $p_medium,
    $p_large,
    $p_webp,
    $p_width,
    $p_height,
    $p_vthumb,
    $p_vdur,
    $p_title,
    $p_alt,
    $p_uid
);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['error' => 'DB insert failed: ' . mysqli_stmt_error($stmt)]);
    mysqli_stmt_close($stmt);
    exit;
}

$new_id = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// ── Build response ────────────────────────────────────────────────────────────
$urls = [];
if ($is_image) {
    $urls = media_urls(array_merge($db_row, ['id' => $new_id]));
} elseif ($is_video) {
    $prefix = media_public_prefix($db_row['storage_path']);
    $ext    = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) ?: 'mp4';
    $urls   = [
        'original'  => $prefix . $db_row['base_name'] . '.' . $ext,
        'thumbnail' => $db_row['video_thumb']
                       ? '/express-news/uploads/' . $db_row['video_thumb']
                       : '',
    ];
} else {
    $prefix = media_public_prefix($db_row['storage_path']);
    $ext    = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) ?: 'bin';
    $urls   = ['original' => $prefix . $db_row['base_name'] . '.' . $ext];
}

echo json_encode([
    'success'      => true,
    'id'           => $new_id,
    'base_name'    => $db_row['base_name'],
    'storage_path' => $db_row['storage_path'],
    'mime_type'    => $mime_type,
    'file_size'    => $db_row['file_size'],
    'urls'         => $urls,
    'message'      => 'Uploaded successfully.',
]);

mysqli_close($conn);
