document.addEventListener('DOMContentLoaded', function() {
    const mobileNavToggler = document.getElementById('mobileNavToggler');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');

    if (mobileNavToggler) {
        mobileNavToggler.addEventListener('click', function() {
            // This toggles a class on the BODY tag.
            // The CSS will handle the actual sliding animation.
            document.body.classList.toggle('sidebar-toggled');
        });
    }

    // Optional: Close sidebar when clicking on the main content area on mobile
    if (mainContent) {
        mainContent.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                document.body.classList.remove('sidebar-toggled');
            }
        });
    }
});

// =========================================================
//  MEDIA MANAGER LOGIC (v18.1 - Final, Complete & Corrected)
// =========================================================
// =========================================================
//  MEDIA MANAGER LOGIC (v18.1 - Professional Details Overlay Merged)
// =========================================================
document.addEventListener('DOMContentLoaded', () => {
    const mediaManagerContainer = document.getElementById('media-manager-container');
    if (!mediaManagerContainer) {
        return; // Exit if we're not on the media manager page
    }

    // --- 1. DOM Element References & State ---
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const progressContainer = document.getElementById('upload-progress-container');
    const mediaGrid = document.getElementById('media-library-grid');
    const paginationControls = document.getElementById('media-pagination-controls');
    
    // Bulk action elements
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const bulkCounter = document.getElementById('bulk-counter');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
    
    // Details Overlay elements
    const detailsOverlay = document.getElementById('mediaDetailsOverlay');
    const detailsPreview = document.getElementById('details-preview-content');
    const detailsFormContent = document.getElementById('details-form-content');
    const detailsOverlayCloseBtn = document.getElementById('details-overlay-close-btn');
    
    // State variables
    let currentSelectedId = null;
    let selectedItems = new Set();
    let currentPage = 1;

    // --- 2. UI Update Functions ---
    const openDetailsOverlay = () => detailsOverlay.classList.add('active');
    const closeDetailsOverlay = () => {
        detailsOverlay.classList.remove('active');
        document.querySelectorAll('.media-item.active').forEach(el => el.classList.remove('active'));
        currentSelectedId = null;
    };
    
    const updateBulkActionBar = () => {
        const selectModeClass = 'media-select-mode';
        if (selectedItems.size > 0) {
            bulkActionBar.style.display = 'flex';
            bulkCounter.textContent = `${selectedItems.size} selected`;
            document.body.classList.add(selectModeClass);
        } else {
            bulkActionBar.style.display = 'none';
            document.body.classList.remove(selectModeClass);
        }
    };
    
    const renderPagination = (paginationData) => {
        if (!paginationControls || !paginationData || paginationData.totalPages <= 1) {
            if (paginationControls) paginationControls.innerHTML = '';
            return;
        }
        const { currentPage, totalPages } = paginationData;
        const prevButton = `<button type="button" class="btn btn-sm btn-outline-secondary" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>Previous</button>`;
        const nextButton = `<button type="button" class="btn btn-sm btn-outline-secondary" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>`;
        const pageInfo = `<span class="text-muted small">Page ${currentPage} of ${totalPages}</span>`;
        paginationControls.innerHTML = `
            <div class="btn-group">${prevButton}</div>
            ${pageInfo}
            <div class="btn-group">${nextButton}</div>
        `;
    };

    // --- 3. Core Data & Display Functions ---
    const uploadFile = (file) => {
        const formData = new FormData();
        formData.append('file', file);
        const progressId = 'progress-' + Math.random().toString(36).substr(2, 9);
        const progressHTML = `<div class="mb-2" id="${progressId}"><small>${file.name}</small><div class="progress" style="height: 10px;"><div class="progress-bar" style="width: 0%;"></div></div></div>`;
        progressContainer.insertAdjacentHTML('beforeend', progressHTML);
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/raj-news/admin/media-upload-handler.php', true);
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                const bar = document.querySelector(`#${progressId} .progress-bar`);
                if (bar) bar.style.width = percent + '%';
            }
        };
        xhr.onload = () => {
            const barDiv = document.getElementById(progressId);
            if (!barDiv) return;
            const bar = barDiv.querySelector('.progress-bar');
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        bar.classList.add('bg-success');
                        setTimeout(() => barDiv.remove(), 2000);
                        loadMediaLibrary(1); // Go to first page to see new uploads
                    } else {
                        bar.classList.add('bg-danger');
                        barDiv.querySelector('small').textContent += ` - Error: ${res.error}`;
                    }
                } catch (e) { bar.classList.add('bg-danger'); barDiv.querySelector('small').textContent += ` - Error: Invalid Response`; }
            } else { bar.classList.add('bg-danger'); barDiv.querySelector('small').textContent += ` - Error: Upload Failed`; }
        };
        xhr.send(formData);
    };
    
    const loadMediaLibrary = async (page = 1) => {
        currentPage = page;
        mediaGrid.innerHTML = '<p class="text-muted text-center p-3">Loading media...</p>';
        try {
            const response = await fetch(`/raj-news/admin/ajax-media-handler.php?action=get_all_media&page=${page}`);
            if (!response.ok) throw new Error('Network response was not ok.');
            const data = await response.json();
            
            mediaGrid.innerHTML = '';
            if (data.media && data.media.length > 0) {
                data.media.forEach(item => mediaGrid.appendChild(createMediaItemElement(item)));
            } else {
                mediaGrid.innerHTML = '<p class="text-muted text-center p-3">No media found.</p>';
            }
            renderPagination(data.pagination);
        } catch (error) {
            console.error("Failed to load media library:", error);
            mediaGrid.innerHTML = `<p class="text-danger">Error loading library.</p>`;
        }
    };

    const createMediaItemElement = (item) => {
        const mediaItemDiv = document.createElement('div');
        mediaItemDiv.className = 'media-item';
        mediaItemDiv.dataset.mediaId = item.id;
        if (selectedItems.has(item.id)) {
            mediaItemDiv.classList.add('selected');
        }

        let thumbnailHTML = '';
        const uploadsUrl = '/raj-news/uploads/';
        if (item.file_type.startsWith('image/')) {
            const src = uploadsUrl + (parseInt(item.is_image_optimized) ? item.filename.replace(/\.[^/.]+$/, ".webp") : item.filename);
            thumbnailHTML = `<img src="${src}" alt="${item.alt_text || ''}" loading="lazy">`;
        } else {
            const iconClass = item.file_type.startsWith('video/') ? 'fa-file-video' : (item.file_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file');
            thumbnailHTML = `<div class="file-icon"><i class="fa-solid ${iconClass}"></i></div>`;
        }
        
        mediaItemDiv.innerHTML = `
            ${thumbnailHTML}
            <div class="file-name">${item.title || item.filename}</div>
            <div class="select-checkbox"><i class="fa-solid fa-check"></i></div>
        `;

        mediaItemDiv.addEventListener('click', (e) => {
            const isPicker = mediaManagerContainer.dataset.pickerMode === 'true';
            if (document.body.classList.contains('media-select-mode') || e.target.closest('.select-checkbox')) {
                e.preventDefault();
                toggleSelection(mediaItemDiv, item.id);
            } else if (isPicker) {
                const publicUrl = `${window.location.origin}/raj-news/uploads/${item.filename}`;
                window.parent.postMessage({ source: 'velion-media-manager', action: 'insert_media', fileType: item.file_type, url: publicUrl, alt: item.alt_text || item.title || '' }, '*');
            } else {
                document.querySelectorAll('.media-item.active').forEach(el => el.classList.remove('active'));
                mediaItemDiv.classList.add('active');
                showDetailsInOverlay(item.id);
            }
        });
        return mediaItemDiv;
    };

    const toggleSelection = (divElement, itemId) => {
        const id = parseInt(itemId);
        if (selectedItems.has(id)) {
            selectedItems.delete(id);
            divElement.classList.remove('selected');
        } else {
            selectedItems.add(id);
            divElement.classList.add('selected');
        }
        updateBulkActionBar();
    };

    const showDetailsInOverlay = async (mediaId) => {
        openDetailsOverlay();
        currentSelectedId = mediaId;
        detailsPreview.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>';
        detailsFormContent.innerHTML = '<p class="text-muted p-3">Loading Details...</p>';

        try {
            const response = await fetch(`/raj-news/admin/ajax-media-handler.php?action=get_media_details&id=${mediaId}`);
            if (!response.ok) throw new Error(`Network response was not ok: ${response.statusText}`);
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            // Populate Preview Pane
            let previewHTML = '';
            const publicUrl = `/raj-news/uploads/${data.filename}`;
            if (data.file_type.startsWith('image/')) {
                const webpUrl = parseInt(data.is_image_optimized) ? publicUrl.replace(/\.[^/.]+$/, ".webp") : publicUrl;
                previewHTML = `<picture><source srcset="${webpUrl}" type="image/webp"><img src="${publicUrl}" alt="Preview"></picture>`;
            } else if (data.file_type.startsWith('video/')) {
                previewHTML = `<video src="${publicUrl}" controls></video>`;
            } else {
                previewHTML = `<div class="file-icon text-light" style="font-size: 8rem;"><i class="fa-solid fa-file"></i></div>`;
            }
            detailsPreview.innerHTML = previewHTML;

            // Populate Form Pane
            detailsFormContent.innerHTML = `
                <form id="details-form" class="h-100 d-flex flex-column">
                    <div class="body flex-grow-1" style="overflow-y: auto;">
                        <div class="form-group mb-2"><label class="form-label small" for="details-title">Title</label><input type="text" id="details-title" class="form-control form-control-sm" value="${data.title || ''}"></div>
                        <div class="form-group mb-2"><label class="form-label small" for="details-alt">Alt Text</label><input type="text" id="details-alt" class="form-control form-control-sm" value="${data.alt_text || ''}"></div>
                        <div class="form-group mb-2"><label class="form-label small" for="details-caption">Caption</label><textarea id="details-caption" class="form-control form-control-sm" rows="2">${data.caption || ''}</textarea></div>
                        <div class="form-group mb-3"><label class="form-label small" for="details-desc">Description</label><textarea id="details-desc" class="form-control form-control-sm" rows="3">${data.description || ''}</textarea></div>
                        <hr>
                        <strong>Uploaded by:</strong> <p class="text-muted small mb-1">${data.uploader_name || 'N/A'}</p>
                        <strong>Uploaded on:</strong> <p class="text-muted small mb-1">${new Date(data.created_at).toLocaleString()}</p>
                        <strong>File type:</strong> <p class="text-muted small mb-1">${data.file_type}</p>
                        <strong>File size:</strong> <p class="text-muted small mb-3">${(data.file_size / 1024).toFixed(2)} KB</p>
                    </div>
                    <div class="footer p-3 bg-white border-top">
                        <div class="input-group input-group-sm mb-2">
                           <input type="text" id="details-url" class="form-control" value="${window.location.origin}${publicUrl}" readonly>
                           <button class="btn btn-outline-secondary" type="button" id="copy-url-btn">Copy</button>
                        </div>
                        <div class="d-flex justify-content-between">
                           <button type="button" class="btn btn-sm btn-outline-danger" id="delete-btn">Delete Permanently</button>
                           <button type="submit" class="btn btn-sm btn-success">Save Details</button>
                        </div>
                    </div>
                </form>
            `;
        } catch(error) {
            console.error("Failed to show details:", error);
            detailsPreview.innerHTML = '<p class="text-danger">Error</p>';
            detailsFormContent.innerHTML = `<p class="text-danger p-3">Error loading details. ${error.message}</p>`;
        }
    };

    // --- 4. Event Listeners ---
    const initializeEventListeners = () => {
        // Uploader listeners
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('drag-over'); if (e.dataTransfer.files.length) [...e.dataTransfer.files].forEach(uploadFile); });
        fileInput.addEventListener('change', () => { if (fileInput.files.length) [...fileInput.files].forEach(uploadFile); });
        
        // Details Overlay Listeners
        detailsOverlayCloseBtn.addEventListener('click', closeDetailsOverlay);
        detailsOverlay.addEventListener('click', (e) => {
            if (e.target === detailsOverlay) closeDetailsOverlay();
        });
        
        // Event delegation for actions inside the dynamically loaded form
        detailsFormContent.addEventListener('click', async (e) => {
            if (e.target.id === 'copy-url-btn') {
                const urlInput = document.getElementById('details-url');
                try {
                    await navigator.clipboard.writeText(urlInput.value);
                    e.target.textContent = 'Copied!';
                    setTimeout(() => { e.target.textContent = 'Copy'; }, 2000);
                } catch (err) { console.error('Failed to copy text: ', err); }
            }
            if (e.target.id === 'delete-btn') {
                if (confirm('Are you sure you want to permanently delete this file? This cannot be undone.')) {
                    try {
                        const response = await fetch('/raj-news/admin/ajax-media-handler.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'delete_media', id: currentSelectedId })
                        });
                        const data = await response.json();
                        if (data.success) {
                            closeDetailsOverlay();
                            loadMediaLibrary(currentPage);
                        } else { alert(`Error: ${data.error}`); }
                    } catch (err) { console.error('Delete failed:', err); }
                }
            }
        });
        detailsFormContent.addEventListener('submit', async (e) => {
            if (e.target.id === 'details-form') {
                e.preventDefault();
                const saveButton = e.target.querySelector('button[type="submit"]');
                const originalButtonText = saveButton.textContent;
                saveButton.textContent = 'Saving...';
                saveButton.disabled = true;

                const updatedData = {
                    action: 'update_details',
                    id: currentSelectedId,
                    title: document.getElementById('details-title').value,
                    alt_text: document.getElementById('details-alt').value,
                    caption: document.getElementById('details-caption').value,
                    description: document.getElementById('details-desc').value,
                };
                
                try {
                    const response = await fetch('/raj-news/admin/ajax-media-handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(updatedData)
                    });
                    const data = await response.json();
                    if (data.success) {
                        const gridItem = mediaGrid.querySelector(`.media-item[data-media-id='${currentSelectedId}'] .file-name`);
                        if(gridItem) gridItem.textContent = updatedData.title || 'Untitled';
                    } else { alert(`Error: ${data.error}`); }
                } catch (err) { console.error('Save failed:', err); }
                finally {
                    saveButton.textContent = originalButtonText;
                    saveButton.disabled = false;
                }
            }
        });

        // Bulk action listeners
        bulkCancelBtn.addEventListener('click', () => {
            selectedItems.clear();
            document.querySelectorAll('.media-item.selected').forEach(el => el.classList.remove('selected'));
            updateBulkActionBar();
        });
        bulkDeleteBtn.addEventListener('click', async () => {
            if (selectedItems.size === 0) return;
            if (confirm(`Are you sure you want to delete ${selectedItems.size} items? This cannot be undone.`)) {
                try {
                    const response = await fetch('/raj-news/admin/ajax-media-handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ action: 'bulk_delete', ids: Array.from(selectedItems) })
                    });
                    const data = await response.json();
                    if (data.success) {
                        selectedItems.clear();
                        updateBulkActionBar();
                        loadMediaLibrary(currentPage);
                        closeDetailsOverlay();
                    } else { alert(`Error: ${data.error}`); }
                } catch (err) { console.error('Bulk delete failed:', err); alert('An error occurred during bulk deletion.'); }
            }
        });
        
        // Pagination listener
        paginationControls.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' && e.target.dataset.page) {
                loadMediaLibrary(parseInt(e.target.dataset.page));
            }
        });
    };

    // --- 5. INITIALIZATION ---
    initializeEventListeners();
    setTimeout(() => {
        loadMediaLibrary(1);
    }, 100);
});