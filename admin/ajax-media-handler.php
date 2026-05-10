<?php
/**
 * Express News — Media AJAX Handler v2
 * Works with the upgraded media table (base_name, storage_path, mime_type, etc.)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(0);
ini_set('display_errors', 0);
require_once 'includes/db.php';

if (!isset($_SESSION['admin_loggedin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Enrich a raw DB row with computed URL sets and display fields.
 */
function enrich_media_row(array $row): array {
    $urls = [];
    if (str_starts_with($row['mime_type'] ?? '', 'image/')) {
        $urls = media_urls($row);
    } elseif (str_starts_with($row['mime_type'] ?? '', 'video/')) {
        $prefix = media_public_prefix($row['storage_path']);
        $ext    = strtolower(pathinfo($row['original_name'] ?? '', PATHINFO_EXTENSION)) ?: 'mp4';
        $urls   = [
            'original'  => $prefix . $row['base_name'] . '.' . $ext,
            'thumbnail' => !empty($row['video_thumb'])
                           ? '/express-news/uploads/' . $row['video_thumb']
                           : '',
        ];
    } else {
        $prefix = media_public_prefix($row['storage_path']);
        $ext    = strtolower(pathinfo($row['original_name'] ?? '', PATHINFO_EXTENSION)) ?: 'bin';
        $urls   = ['original' => $prefix . $row['base_name'] . '.' . $ext];
    }
    $row['urls'] = $urls;

    // Convenience: a single "display_url" for the grid thumbnail
    // Prefer thumbnail → medium → original → plain fallback
    $row['display_url'] = $urls['thumbnail'] ?? $urls['medium'] ?? $urls['original'] ?? '/express-news/assets/images/placeholder.jpg';

    // Legacy fields the JS still reads
    $row['file_type']         = $row['mime_type'];
    $row['filename']          = $row['base_name'];
    $row['is_image_optimized']= (int)($row['has_webp'] ?? 0);

    return $row;
}

function delete_media_row(mysqli $conn, int $id): array {
    $stmt = mysqli_prepare($conn,
        "SELECT base_name, storage_path FROM media WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) return ['error' => 'Media not found.'];

    media_delete_files($row['base_name'], $row['storage_path'],
                       __DIR__ . '/../uploads/');

    $del = mysqli_prepare($conn, "DELETE FROM media WHERE id = ?");
    mysqli_stmt_bind_param($del, 'i', $id);
    $ok  = mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    return $ok ? ['success' => true] : ['error' => 'DB delete failed.'];
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $action   = $data['action'] ?? '';
    $media_id = isset($data['id']) ? (int)$data['id'] : 0;

    switch ($action) {

        case 'update_details':
            if ($media_id <= 0) { echo json_encode(['error' => 'Invalid ID.']); break; }
            $title       = trim($data['title']       ?? '');
            $alt_text    = trim($data['alt_text']    ?? '');
            $caption     = trim($data['caption']     ?? '');
            $description = trim($data['description'] ?? '');

            $stmt = mysqli_prepare($conn,
                "UPDATE media SET title=?, alt_text=?, caption=?, description=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi',
                $title, $alt_text, $caption, $description, $media_id);
            echo mysqli_stmt_execute($stmt)
                ? json_encode(['success' => true])
                : json_encode(['error' => 'Save failed.']);
            mysqli_stmt_close($stmt);
            break;

        case 'delete_media':
            if ($media_id <= 0) { echo json_encode(['error' => 'Invalid ID.']); break; }
            echo json_encode(delete_media_row($conn, $media_id));
            break;

        case 'bulk_delete':
            $ids = array_map('intval', $data['ids'] ?? []);
            if (empty($ids)) { echo json_encode(['error' => 'No IDs.']); break; }

            mysqli_begin_transaction($conn);
            try {
                foreach ($ids as $id) {
                    $r = delete_media_row($conn, $id);
                    if (!empty($r['error'])) throw new RuntimeException($r['error']);
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true,
                    'message' => count($ids) . ' item(s) deleted.']);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['error' => 'Bulk delete failed: ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action.']);
    }

// ── GET ───────────────────────────────────────────────────────────────────────
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {

        case 'get_all_media':
            $per_page = 30;
            $page     = max(1, (int)($_GET['page']   ?? 1));
            $search   = trim($_GET['search'] ?? '');
            $type     = trim($_GET['type']   ?? '');
            $sort     = in_array($_GET['sort'] ?? '', ['newest','oldest','name'])
                        ? $_GET['sort'] : 'newest';

            $where_parts = [];
            $bind_types  = '';
            $bind_vals   = [];

            if ($search !== '') {
                $like = '%' . $search . '%';
                $where_parts[] = '(m.title LIKE ? OR m.original_name LIKE ? OR m.alt_text LIKE ?)';
                $bind_types   .= 'sss';
                array_push($bind_vals, $like, $like, $like);
            }
            if ($type === 'image') {
                $where_parts[] = "m.mime_type LIKE 'image/%'";
            } elseif ($type === 'video') {
                $where_parts[] = "m.mime_type LIKE 'video/%'";
            } elseif ($type === 'document') {
                $where_parts[] = "m.mime_type NOT LIKE 'image/%' AND m.mime_type NOT LIKE 'video/%'";
            }

            $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';
            $order_sql = match($sort) {
                'oldest' => 'ORDER BY m.created_at ASC',
                'name'   => 'ORDER BY COALESCE(m.title, m.original_name) ASC',
                default  => 'ORDER BY m.created_at DESC',
            };

            // Count
            $cs = mysqli_prepare($conn, "SELECT COUNT(*) AS t FROM media m $where_sql");
            if ($bind_types) mysqli_stmt_bind_param($cs, $bind_types, ...$bind_vals);
            mysqli_stmt_execute($cs);
            $total       = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['t'];
            $total_pages = max(1, (int)ceil($total / $per_page));
            mysqli_stmt_close($cs);

            $offset = ($page - 1) * $per_page;
            $sql    = "SELECT m.*, a.username AS uploader_name
                       FROM media m
                       LEFT JOIN admins a ON m.uploader_id = a.id
                       $where_sql $order_sql
                       LIMIT ? OFFSET ?";
            $stmt   = mysqli_prepare($conn, $sql);
            $ft     = $bind_types . 'ii';
            $fv     = array_merge($bind_vals, [$per_page, $offset]);
            mysqli_stmt_bind_param($stmt, $ft, ...$fv);
            mysqli_stmt_execute($stmt);
            $rows   = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            echo json_encode([
                'media'      => array_map('enrich_media_row', $rows),
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages'  => $total_pages,
                    'totalItems'  => $total,
                ],
            ]);
            break;

        case 'get_media_details':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['error' => 'Invalid ID.']); break; }

            $stmt = mysqli_prepare($conn,
                "SELECT m.*, a.username AS uploader_name
                 FROM media m LEFT JOIN admins a ON m.uploader_id = a.id
                 WHERE m.id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            echo $row
                ? json_encode(enrich_media_row($row))
                : json_encode(['error' => 'Not found.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action.']);
    }
}

mysqli_close($conn);
