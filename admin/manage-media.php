<?php
// This PHP block must be at the VERY TOP of the file
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php'; // We need the DB connection regardless of mode.

// Check for "Picker" mode
$is_picker_mode = isset($_GET['picker']) && $_GET['picker'] === 'true';

// Role-Based Access Control
if (!isset($_SESSION['admin_loggedin'])) {
    die("Authentication Error: You must be logged in to access the media library.");
}

// Conditional Header: Render a full header or a lightweight one for the modal
if (!$is_picker_mode) {
    require_once 'includes/header.php';
} else {
    // Lightweight header for picker mode
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Media Library Picker</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body style="background: #fff;">
    <?php
}
?>

<!-- ========================================================= -->
<!--  V19 - Professional Media Details Overlay & Gallery CSS   -->
<!-- ========================================================= -->
<style>
    /* --- Main Overlay --- */
    .media-details-overlay {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(8px);
        z-index: 1050;
        display: none; 
        align-items: center; justify-content: center;
        padding: 2rem;
        opacity: 0;
        transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .media-details-overlay.active { display: flex; opacity: 1; }

    /* --- CSS Grid-based Modal Content --- */
    .details-modal-content {
        background-color: #f8fafc;
        border-radius: 0.75rem;
        width: 100%; height: 100%;
        max-width: 1400px;
        display: grid;
        grid-template-columns: 1fr 350px;
        grid-template-rows: 100%;
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
        overflow: hidden;
    }
    
    /* --- Preview Pane --- */
    .details-preview-pane {
        grid-column: 1 / 2;
        background-color: #020617;
        display: flex; align-items: center; justify-content: center;
        padding: 1rem;
        min-width: 0; /* Critical fix for flexbox inside grid */
    }
    .details-preview-pane img, 
    .details-preview-pane video {
        display: block; max-width: 100%; max-height: 100%;
        width: auto; height: auto;
        object-fit: contain;
        border-radius: 0.25rem;
    }

    /* --- Form/Metadata Pane --- */
    .details-form-pane {
        grid-column: 2 / 3;
        background-color: #fff;
        border-left: 1px solid #e2e8f0;
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .details-form-pane .header { padding: 1rem; border-bottom: 1px solid #e2e8f0; flex-shrink: 0; }
    .details-form-pane .body { padding: 1rem; overflow-y: auto; flex-grow: 1; }

    /* --- Professional Close Button --- */
    #details-overlay-close-btn {
        position: absolute; top: 1rem; right: 1rem;
        z-index: 1060;
        width: 2.25rem; height: 2.25rem;
        background: rgba(0, 0, 0, 0.3);
        border: none; border-radius: 50%;
        cursor: pointer; color: white;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
    }
    #details-overlay-close-btn:hover {
        background: rgba(0, 0, 0, 0.6);
        transform: scale(1.1);
    }
    #details-overlay-close-btn i { font-size: 1.25rem; line-height: 1; }

    /* --- Responsive Behavior --- */
    @media (max-width: 1024px) {
        .media-details-overlay { padding: 1rem; }
        .details-modal-content {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr auto;
            height: 95vh;
        }
        .details-preview-pane { grid-row: 1 / 2; }
        .details-form-pane { grid-row: 2 / 3; border-left: none; border-top: 1px solid #e2e8f0; max-height: 50%; }
    }
    
    /* --- Main Library Grid Styles --- */
    .media-manager-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
    .media-item { position: relative; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; cursor: pointer; aspect-ratio: 1 / 1; }
    .media-item:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .media-item.active { box-shadow: 0 0 0 3px #2563eb; border-color: #2563eb; }
    .media-item img { width: 100%; height: 100%; object-fit: cover; }
    .media-item .file-icon { font-size: 4rem; text-align: center; padding-top: 2rem; color: #6c757d; }
    .media-item .file-name { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; font-size: 0.8rem; padding: 5px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .media-item .select-checkbox { position: absolute; top: 5px; right: 5px; width: 22px; height: 22px; z-index: 10; background-color: rgba(255, 255, 255, 0.8); border: 1px solid #aaa; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; transition: all 0.2s ease; opacity: 0; }
    .media-item:hover .select-checkbox, body.media-select-mode .media-item .select-checkbox { opacity: 1; }
    .media-item.selected .select-checkbox { background-color: #2563eb; border-color: #2563eb; }
    .media-item.selected::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(37, 99, 235, 0.3); border: 2px solid #2563eb; border-radius: 4px; pointer-events: none; }
</style>

<!-- Main Page Content Wrapper -->
<div class="content-wrapper" id="media-manager-container" data-picker-mode="<?php echo $is_picker_mode ? 'true' : 'false'; ?>">

    <?php if (!$is_picker_mode): ?>
    <div class="content-header">
        <h2>Media Library</h2>
    </div>
    <?php endif; ?>

    <div class="media-library-pane">
        <!-- Uploader Card -->
        <div class="card mb-4">
            <div class="card-header"><i class="fa-solid fa-cloud-upload-alt icon"></i> Upload New Media</div>
            <div class="card-body">
                <div id="drop-zone" style="border: 2px dashed #ccc; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background-color: #f8f9fa;">
                    <p class="mb-2">Drag & Drop files here or</p>
                    <input type="file" id="file-input" multiple class="d-none">
                    <label for="file-input" class="btn btn-sm btn-primary">Select Files</label>
                </div>
                <div id="upload-progress-container" class="mt-3"></div>
            </div>
        </div>

        <!-- Library Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-photo-film icon"></i> Library</span>
                <div id="bulk-action-bar" style="display: none;">
                    <span id="bulk-counter" class="me-3 small text-muted align-self-center">0 selected</span>
                    <button type="button" class="btn btn-sm btn-danger" id="bulk-delete-btn">Delete Selected</button>
                    <button type="button" class="btn btn-sm btn-secondary ms-2" id="bulk-cancel-btn">Cancel</button>
                </div>
            </div>
            <div class="card-body">
                <div class="media-manager-grid" id="media-library-grid"></div>
                <div id="media-pagination-controls" class="d-flex justify-content-between align-items-center mt-4"></div>
            </div>
        </div>
    </div>
</div>

<!-- Details Overlay (Now separate from the main layout) -->
<div class="media-details-overlay" id="mediaDetailsOverlay">
    <div class="details-modal-content">
        <div class="details-preview-pane" id="details-preview-content">
            <!-- Preview img/video will be inserted here by JS -->
        </div>
        <div class="details-form-pane">
            <div class="header">
                <h5 class="mb-0">Attachment Details</h5>
            </div>
            <div class="body" id="details-form-content">
                <!-- The form and metadata will be inserted here by JS -->
            </div>
        </div>
    </div>
    <button type="button" id="details-overlay-close-btn" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>

<?php
// Conditional Footer
if (!$is_picker_mode) {
    require_once 'includes/footer.php';
} else {
    echo '<script src="assets/js/script.js"></script></body></html>';
}
?>