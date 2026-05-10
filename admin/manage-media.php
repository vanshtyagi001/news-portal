<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php';
$is_picker_mode = isset($_GET['picker']) && $_GET['picker'] === 'true';
if (!isset($_SESSION['admin_loggedin'])) {
    die("Authentication Error: You must be logged in.");
}
if (!$is_picker_mode) {
    require_once 'includes/header.php';
} else {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Media Library</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"><link rel="stylesheet" href="assets/css/style.css"></head><body class="picker-body">';
}
?>
<style>
/* ============================================================
   MEDIA LIBRARY — Professional UI
   Matches admin theme variables from assets/css/style.css
   ============================================================ */

/* ── Picker body ─────────────────────────────────────────── */
.picker-body {
    background: #f8fafc;
    padding: 1rem;
}

/* ── Page wrapper ────────────────────────────────────────── */
.ml-page {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
}

/* ── Upload zone card ────────────────────────────────────── */
.ml-upload-card .card-header {
    display: flex;
    align-items: center;
    gap: .5rem;
    user-select: none;
}
.ml-upload-card .card-header .ms-auto {
    margin-left: auto;
}

.ml-upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2.5rem 1.5rem;
    text-align: center;
    background: #f8fafc;
    transition: border-color .25s ease, background .25s ease;
    cursor: pointer;
}
.ml-upload-zone.drag-over {
    border-color: #2563eb;
    background: rgba(37, 99, 235, .05);
}
.ml-upload-zone p { margin: 0; }

/* Progress items */
#ml-progress-container { margin-top: .75rem; }
.ml-progress-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .5rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: .85rem;
    color: #374151;
}
.ml-progress-item:last-child { border-bottom: none; }
.ml-progress-item .ml-prog-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.ml-progress-item .ml-prog-bar-wrap {
    flex: 2;
    height: 6px;
    background: #e2e8f0;
    border-radius: 99px;
    overflow: hidden;
}
.ml-progress-item .ml-prog-bar {
    height: 100%;
    background: #2563eb;
    border-radius: 99px;
    transition: width .3s ease;
    width: 0%;
}
.ml-progress-item .ml-prog-status {
    font-size: .78rem;
    color: #64748b;
    white-space: nowrap;
}
.ml-progress-item .ml-prog-status.done  { color: #10b981; }
.ml-progress-item .ml-prog-status.error { color: #ef4444; }

/* ── Toolbar ─────────────────────────────────────────────── */
.ml-toolbar-inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .6rem;
}
.ml-toolbar-left {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex: 1;
    flex-wrap: wrap;
}
.ml-toolbar-right {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-shrink: 0;
}

/* Search */
.ml-search-wrap {
    position: relative;
    flex: 1;
    min-width: 160px;
    max-width: 280px;
}
.ml-search-icon {
    position: absolute;
    left: .75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: .85rem;
    pointer-events: none;
}
.ml-search-input {
    width: 100%;
    padding: .55rem .9rem .55rem 2.2rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: .875rem;
    color: #0f172a;
    background: #f8fafc;
    transition: border-color .2s ease, box-shadow .2s ease;
    outline: none;
}
.ml-search-input:focus {
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}

/* Selects */
.ml-select {
    padding: .55rem .85rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: .875rem;
    color: #0f172a;
    background: #f8fafc;
    cursor: pointer;
    outline: none;
    transition: border-color .2s ease;
}
.ml-select:focus { border-color: #2563eb; }

/* Count badge */
.ml-count-badge {
    font-size: .8rem;
    color: #64748b;
    background: #f1f5f9;
    padding: .3rem .7rem;
    border-radius: 20px;
    white-space: nowrap;
}

/* View toggle buttons */
.ml-view-btn {
    width: 34px;
    height: 34px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    transition: all .2s ease;
}
.ml-view-btn:hover { border-color: #2563eb; color: #2563eb; }
.ml-view-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

/* Select mode button */
.ml-select-btn {
    padding: .45rem .85rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    font-size: .82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    transition: all .2s ease;
}
.ml-select-btn:hover { border-color: #2563eb; color: #2563eb; }
.ml-select-btn.active {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}

/* ── Main wrap (grid + panel side by side) ───────────────── */
.ml-main-wrap {
    display: flex;
    gap: 0;
    position: relative;
    align-items: flex-start;
}
.ml-main {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    transition: margin-right .35s cubic-bezier(.4,0,.2,1);
}
.ml-main.panel-open {
    margin-right: 390px;
}

/* ── Grid view ───────────────────────────────────────────── */
.ml-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    padding: .25rem .1rem;
}

/* ── List view ───────────────────────────────────────────── */
.ml-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.ml-list-header {
    display: flex;
    align-items: center;
    padding: .6rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    gap: .75rem;
}
.ml-list-row {
    display: flex;
    align-items: center;
    padding: .65rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    gap: .75rem;
    cursor: pointer;
    transition: background .15s ease;
    position: relative;
}
.ml-list-row:last-child { border-bottom: none; }
.ml-list-row:hover { background: #f8fafc; }
.ml-list-row.active { background: #eff6ff; }
.ml-list-row.selected { background: #eff6ff; }
.ml-list-thumb {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ml-list-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ml-list-thumb .ml-list-icon { font-size: 1.4rem; color: #94a3b8; }
.ml-list-name {
    flex: 2;
    font-size: .875rem;
    font-weight: 500;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.ml-list-meta {
    flex: 1;
    font-size: .8rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ml-list-actions {
    display: flex;
    gap: .4rem;
    flex-shrink: 0;
}
.ml-list-check {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    accent-color: #2563eb;
}

/* ── Media item (grid card) ──────────────────────────────── */
.ml-item {
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    background: #f1f5f9;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}
.ml-item:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 14px rgba(37,99,235,.15);
    transform: translateY(-2px);
}
.ml-item.active {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.25);
}
.ml-item.selected {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.25);
}
.ml-item.selected::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(37,99,235,.18);
    pointer-events: none;
    border-radius: 8px;
}
.ml-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ml-item-icon {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    color: #94a3b8;
    font-size: 2.5rem;
}
.ml-item-icon span {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
}
.ml-item-name {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,.75) 0%, transparent 100%);
    color: #fff;
    font-size: .72rem;
    padding: 1.5rem .5rem .4rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
    border-radius: 0 0 8px 8px;
}
.ml-item-check {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 22px;
    height: 22px;
    background: rgba(255,255,255,.85);
    border: 1.5px solid #cbd5e1;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    color: transparent;
    transition: all .2s ease;
    opacity: 0;
    z-index: 2;
}
.ml-item:hover .ml-item-check,
body.ml-select-mode .ml-item .ml-item-check { opacity: 1; }
.ml-item.selected .ml-item-check {
    opacity: 1;
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

/* ── Stat badge (file type label) ────────────────────────── */
.ml-stat-badge {
    display: inline-block;
    padding: .18em .55em;
    font-size: .68rem;
    font-weight: 700;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
    line-height: 1.4;
}
.ml-stat-badge.image    { background: #dbeafe; color: #1d4ed8; }
.ml-stat-badge.video    { background: #fce7f3; color: #9d174d; }
.ml-stat-badge.document { background: #fef3c7; color: #92400e; }
.ml-stat-badge.other    { background: #f1f5f9; color: #475569; }

/* ── Pagination ──────────────────────────────────────────── */
.ml-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    padding: 1.25rem 0 .5rem;
    flex-wrap: wrap;
}
.ml-page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 .6rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #374151;
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .2s ease;
}
.ml-page-btn:hover:not(:disabled) { border-color: #2563eb; color: #2563eb; }
.ml-page-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
.ml-page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ── Empty state ─────────────────────────────────────────── */
.ml-empty {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: #94a3b8;
    text-align: center;
    gap: .75rem;
}
.ml-empty i { font-size: 3.5rem; opacity: .5; }
.ml-empty p { margin: 0; font-size: .95rem; }

/* ── Details side panel ──────────────────────────────────── */
.ml-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 380px;
    height: 100vh;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    box-shadow: -4px 0 24px rgba(0,0,0,.08);
    z-index: 900;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform .35s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}
.ml-panel.open { transform: translateX(0); }

.ml-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    background: #fafbfc;
}
.ml-panel-title {
    font-weight: 700;
    font-size: .95rem;
    color: #0f172a;
}
.ml-panel-close {
    width: 30px;
    height: 30px;
    border: none;
    background: #f1f5f9;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 1rem;
    transition: all .2s ease;
}
.ml-panel-close:hover { background: #fee2e2; color: #dc2626; }

.ml-panel-preview {
    background: #0f172a;
    flex-shrink: 0;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.ml-panel-preview img,
.ml-panel-preview video {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.ml-panel-preview-placeholder {
    color: rgba(255,255,255,.2);
    font-size: 4rem;
}

.ml-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
}
.ml-panel-body::-webkit-scrollbar { width: 5px; }
.ml-panel-body::-webkit-scrollbar-track { background: transparent; }
.ml-panel-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

.ml-panel-empty {
    color: #94a3b8;
    text-align: center;
    padding: 2rem 0;
    font-size: .9rem;
}

/* Panel form fields */
.ml-panel-body .form-group { margin-bottom: 1rem; }
.ml-panel-body .form-label {
    display: block;
    font-size: .78rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: .3rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.ml-panel-body .form-control {
    width: 100%;
    padding: .55rem .8rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: .875rem;
    color: #0f172a;
    background: #f8fafc;
    transition: border-color .2s ease, box-shadow .2s ease;
    outline: none;
    resize: vertical;
}
.ml-panel-body .form-control:focus {
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}

/* File info table in panel */
.ml-file-info {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: .75rem 1rem;
    margin-bottom: 1rem;
    font-size: .82rem;
}
.ml-file-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: .3rem 0;
    border-bottom: 1px solid #f1f5f9;
    gap: .5rem;
}
.ml-file-info-row:last-child { border-bottom: none; }
.ml-file-info-label { color: #64748b; font-weight: 600; flex-shrink: 0; }
.ml-file-info-value { color: #0f172a; text-align: right; word-break: break-all; }

/* Copy URL button */
.ml-copy-url-wrap {
    display: flex;
    gap: .5rem;
    margin-bottom: 1rem;
}
.ml-copy-url-input {
    flex: 1;
    padding: .5rem .75rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: .8rem;
    color: #64748b;
    background: #f8fafc;
    outline: none;
    min-width: 0;
}
.ml-copy-url-btn {
    padding: .5rem .85rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #374151;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all .2s ease;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.ml-copy-url-btn:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
.ml-copy-url-btn.copied { border-color: #10b981; color: #10b981; background: #f0fdf4; }

/* Panel action buttons */
.ml-panel-actions {
    display: flex;
    gap: .6rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
    background: #fafbfc;
}
.ml-panel-actions .btn { flex: 1; justify-content: center; }

/* ── Bulk action bar ─────────────────────────────────────── */
.ml-bulk-bar {
    position: fixed;
    bottom: -80px;
    left: 50%;
    transform: translateX(-50%);
    background: #0f172a;
    color: #fff;
    padding: .85rem 1.5rem;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,.25);
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 950;
    transition: bottom .3s cubic-bezier(.4,0,.2,1);
    white-space: nowrap;
}
.ml-bulk-bar.visible { bottom: 1.5rem; }
.ml-bulk-bar #ml-bulk-count {
    font-size: .9rem;
    font-weight: 600;
    color: #94a3b8;
}
.ml-bulk-actions { display: flex; gap: .5rem; }

/* ── btn-secondary & btn-danger (local, if not in main CSS) ─ */
.btn-secondary {
    background: #64748b;
    color: #fff;
}
.btn-secondary:hover { background: #475569; }
.btn-danger {
    background: #ef4444;
    color: #fff;
}
.btn-danger:hover { background: #dc2626; }
.btn-sm {
    padding: .45rem .9rem;
    font-size: .82rem;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .ml-main.panel-open { margin-right: 0; }

    /* Panel becomes bottom sheet */
    .ml-panel {
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 80vh;
        border-left: none;
        border-top: 1px solid #e2e8f0;
        border-radius: 16px 16px 0 0;
        transform: translateY(100%);
        box-shadow: 0 -4px 24px rgba(0,0,0,.12);
    }
    .ml-panel.open { transform: translateY(0); }

    .ml-grid {
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 8px;
    }
    .ml-toolbar-left { flex-wrap: wrap; }
    .ml-search-wrap { max-width: 100%; }

    .ml-list-meta { display: none; }
}

@media (max-width: 480px) {
    .ml-grid {
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 6px;
    }
    .ml-bulk-bar {
        left: 1rem;
        right: 1rem;
        transform: none;
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>

<?php if (!$is_picker_mode): ?>
<div class="content-header">
    <h2><i class="fa-solid fa-photo-film icon"></i> Media Library</h2>
    <button type="button" class="btn btn-primary" id="ml-upload-open-btn">
        <i class="fa-solid fa-cloud-arrow-up icon"></i> Upload New
    </button>
</div>
<?php endif; ?>

<div id="ml-page" class="ml-page" data-picker="<?php echo $is_picker_mode ? 'true' : 'false'; ?>">

    <!-- ── Upload zone (collapsible) ─────────────────────── -->
    <div class="ml-upload-card card mb-3" id="ml-upload-card">
        <div class="card-header" style="cursor:pointer" id="ml-upload-toggle">
            <i class="fa-solid fa-cloud-arrow-up icon"></i> Upload Media
            <span class="ms-auto"><i class="fa-solid fa-chevron-down" id="ml-upload-chevron"></i></span>
        </div>
        <div class="card-body" id="ml-upload-body">
            <div id="ml-drop-zone" class="ml-upload-zone">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:2.5rem;color:#94a3b8;margin-bottom:.75rem;display:block;"></i>
                <p class="mb-1" style="font-weight:600;color:#374151;">Drag &amp; drop files here</p>
                <p class="mb-3" style="font-size:.85rem;color:#94a3b8;">Supports: JPG, PNG, GIF, WebP, MP4, PDF, and more</p>
                <input type="file" id="ml-file-input" multiple accept="image/*,video/*,.pdf,.doc,.docx" style="display:none">
                <label for="ml-file-input" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-folder-open icon"></i> Browse Files
                </label>
            </div>
            <div id="ml-progress-container"></div>
        </div>
    </div>

    <!-- ── Toolbar ────────────────────────────────────────── -->
    <div class="ml-toolbar card mb-3">
        <div class="card-body" style="padding:.75rem 1rem;">
            <div class="ml-toolbar-inner">
                <div class="ml-toolbar-left">
                    <div class="ml-search-wrap">
                        <i class="fa-solid fa-search ml-search-icon"></i>
                        <input type="text" id="ml-search" placeholder="Search media..." class="ml-search-input">
                    </div>
                    <select id="ml-type-filter" class="ml-select">
                        <option value="">All Types</option>
                        <option value="image">Images</option>
                        <option value="video">Videos</option>
                        <option value="document">Documents</option>
                    </select>
                    <select id="ml-sort" class="ml-select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name">Name A&ndash;Z</option>
                    </select>
                </div>
                <div class="ml-toolbar-right">
                    <span id="ml-total-count" class="ml-count-badge">0 items</span>
                    <button type="button" class="ml-view-btn active" id="ml-view-grid" title="Grid view">
                        <i class="fa-solid fa-grip"></i>
                    </button>
                    <button type="button" class="ml-view-btn" id="ml-view-list" title="List view">
                        <i class="fa-solid fa-list"></i>
                    </button>
                    <button type="button" class="ml-select-btn" id="ml-select-toggle" title="Select multiple">
                        <i class="fa-solid fa-check-square"></i> Select
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main area + side panel ────────────────────────── -->
    <div class="ml-main-wrap">
        <div class="ml-main" id="ml-main">

            <!-- Grid view -->
            <div class="ml-grid" id="ml-grid"></div>

            <!-- List view (hidden by default) -->
            <div class="ml-list" id="ml-list" style="display:none;"></div>

            <!-- Pagination -->
            <div id="ml-pagination" class="ml-pagination"></div>

        </div>

        <!-- ── Details side panel ─────────────────────────── -->
        <div class="ml-panel" id="ml-panel">
            <div class="ml-panel-header">
                <span class="ml-panel-title">Attachment Details</span>
                <button type="button" class="ml-panel-close" id="ml-panel-close" aria-label="Close panel">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ml-panel-preview" id="ml-panel-preview">
                <div class="ml-panel-preview-placeholder">
                    <i class="fa-solid fa-image"></i>
                </div>
            </div>
            <div class="ml-panel-body" id="ml-panel-body">
                <p class="ml-panel-empty">Select a file to view details</p>
            </div>
            <div class="ml-panel-actions" id="ml-panel-actions" style="display:none;">
                <button type="button" class="btn btn-danger btn-sm" id="ml-panel-delete">
                    <i class="fa-solid fa-trash icon"></i> Delete
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="ml-panel-save">
                    <i class="fa-solid fa-floppy-disk icon"></i> Save
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ── Bulk action bar ────────────────────────────────────── -->
<div class="ml-bulk-bar" id="ml-bulk-bar">
    <span id="ml-bulk-count">0 selected</span>
    <div class="ml-bulk-actions">
        <button type="button" class="btn btn-sm btn-danger" id="ml-bulk-delete">
            <i class="fa-solid fa-trash icon"></i> Delete Selected
        </button>
        <button type="button" class="btn btn-sm btn-secondary" id="ml-bulk-cancel">
            Cancel
        </button>
    </div>
</div>

<?php
if (!$is_picker_mode) {
    require_once 'includes/footer.php';
} else {
    echo '<script src="assets/js/script.js"></script></body></html>';
}
?>
