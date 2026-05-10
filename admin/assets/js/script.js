document.addEventListener('DOMContentLoaded', function() {
    const mobileNavToggler = document.getElementById('mobileNavToggler');
    const mainContent      = document.getElementById('main-content');

    // Create a backdrop overlay for mobile
    const backdrop = document.createElement('div');
    backdrop.id = 'sidebarBackdrop';
    backdrop.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;backdrop-filter:blur(2px);transition:opacity 0.3s ease;';
    document.body.appendChild(backdrop);

    function openSidebar() {
        document.body.classList.add('sidebar-toggled');
        backdrop.style.display = 'block';
        requestAnimationFrame(() => { backdrop.style.opacity = '1'; });
    }
    function closeSidebar() {
        document.body.classList.remove('sidebar-toggled');
        backdrop.style.opacity = '0';
        setTimeout(() => { backdrop.style.display = 'none'; }, 300);
    }

    if (mobileNavToggler) {
        mobileNavToggler.addEventListener('click', function() {
            document.body.classList.contains('sidebar-toggled') ? closeSidebar() : openSidebar();
        });
    }

    backdrop.addEventListener('click', closeSidebar);

    if (mainContent) {
        mainContent.addEventListener('click', function() {
            if (window.innerWidth <= 992) closeSidebar();
        });
    }
});

// =========================================================

// =========================================================
//  MEDIA LIBRARY — Complete WordPress-like implementation
// =========================================================
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('ml-page');
    if (!page) return;

    // ── Constants ──────────────────────────────────────────
    const BASE   = '/express-news/admin/ajax-media-handler.php';
    const UPLOAD = '/express-news/admin/media-upload-handler.php';
    const UPLOADS_URL = '/express-news/uploads/';
    const isPicker = page.dataset.picker === 'true';

    // ── State ───────────────────────────────────────────────
    let currentPage   = 1;
    let totalPages    = 1;
    let currentItem   = null;   // full item object currently in panel
    let selectedIds   = new Set();
    let isSelectMode  = false;
    let currentView   = 'grid'; // 'grid' | 'list'
    let searchTimer   = null;

    // ── DOM refs ────────────────────────────────────────────
    const grid          = document.getElementById('ml-grid');
    const list          = document.getElementById('ml-list');
    const pagination    = document.getElementById('ml-pagination');
    const totalCount    = document.getElementById('ml-total-count');
    const panel         = document.getElementById('ml-panel');
    const panelPreview  = document.getElementById('ml-panel-preview');
    const panelBody     = document.getElementById('ml-panel-body');
    const panelActions  = document.getElementById('ml-panel-actions');
    const panelClose    = document.getElementById('ml-panel-close');
    const panelSave     = document.getElementById('ml-panel-save');
    const panelDelete   = document.getElementById('ml-panel-delete');
    const mlMain        = document.getElementById('ml-main');
    const dropZone      = document.getElementById('ml-drop-zone');
    const fileInput     = document.getElementById('ml-file-input');
    const progressCont  = document.getElementById('ml-progress-container');
    const searchInput   = document.getElementById('ml-search');
    const typeFilter    = document.getElementById('ml-type-filter');
    const sortSelect    = document.getElementById('ml-sort');
    const viewGridBtn   = document.getElementById('ml-view-grid');
    const viewListBtn   = document.getElementById('ml-view-list');
    const selectToggle  = document.getElementById('ml-select-toggle');
    const bulkBar       = document.getElementById('ml-bulk-bar');
    const bulkCount     = document.getElementById('ml-bulk-count');
    const bulkDelete    = document.getElementById('ml-bulk-delete');
    const bulkCancel    = document.getElementById('ml-bulk-cancel');
    const uploadToggle  = document.getElementById('ml-upload-toggle');
    const uploadBody    = document.getElementById('ml-upload-body');
    const uploadChevron = document.getElementById('ml-upload-chevron');
    const uploadOpenBtn = document.getElementById('ml-upload-open-btn');

    // ── Helpers ─────────────────────────────────────────────
    const fmt = {
        size: (bytes) => {
            if (!bytes) return '—';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(2) + ' MB';
        },
        date: (str) => str ? new Date(str).toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        }) : '—',
        typeBadge: (mime) => {
            if (!mime) return '<span class="ml-stat-badge other">File</span>';
            if (mime.startsWith('image/')) return '<span class="ml-stat-badge image">Image</span>';
            if (mime.startsWith('video/')) return '<span class="ml-stat-badge video">Video</span>';
            if (mime === 'application/pdf') return '<span class="ml-stat-badge document">PDF</span>';
            return '<span class="ml-stat-badge other">Doc</span>';
        },
        thumb: (item) => {
            // Use server-computed display_url (thumbnail or medium variant)
            if (item.display_url && item.file_type && item.file_type.startsWith('image/')) {
                return `<img src="${esc(item.display_url)}" alt="${esc(item.alt_text || '')}" loading="lazy">`;
            }
            if (item.file_type && item.file_type.startsWith('image/')) {
                // Fallback: try urls object
                const src = (item.urls && item.urls.thumbnail) || (item.urls && item.urls.medium) || '';
                if (src) return `<img src="${esc(src)}" alt="${esc(item.alt_text || '')}" loading="lazy">`;
            }
            // Non-image icon
            const icon = item.file_type && item.file_type.startsWith('video/')
                ? 'fa-file-video'
                : (item.file_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file');
            const ext  = (item.original_name || item.filename || '').split('.').pop().toUpperCase();
            return `<div class="ml-item-icon"><i class="fa-solid ${icon}"></i><span>${ext}</span></div>`;
        },
        listThumb: (item) => {
            if (item.display_url && item.file_type && item.file_type.startsWith('image/')) {
                return `<img src="${esc(item.display_url)}" alt="" loading="lazy">`;
            }
            if (item.file_type && item.file_type.startsWith('video/') && item.urls && item.urls.thumbnail) {
                return `<img src="${esc(item.urls.thumbnail)}" alt="" loading="lazy">`;
            }
            const icon = item.file_type && item.file_type.startsWith('video/')
                ? 'fa-file-video'
                : (item.file_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file');
            return `<i class="fa-solid ${icon} ml-list-icon"></i>`;
        },
    };

    const esc = (s) => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const publicUrl = (item) => {
        // New schema: use urls.large or urls.original
        if (item.urls) {
            return item.urls.large || item.urls.original || item.display_url || '';
        }
        // Legacy fallback
        return `${window.location.origin}${UPLOADS_URL}${item.filename}`;
    };

    // ── Load library ────────────────────────────────────────
    const load = async (pg = 1) => {
        currentPage = pg;
        const search = searchInput ? searchInput.value.trim() : '';
        const type   = typeFilter  ? typeFilter.value  : '';
        const sort   = sortSelect  ? sortSelect.value  : 'newest';

        const params = new URLSearchParams({
            action: 'get_all_media',
            page: pg,
            search,
            type,
            sort,
        });

        grid.innerHTML = '<div class="ml-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></div>';
        list.innerHTML = '';

        try {
            const res  = await fetch(`${BASE}?${params}`);
            const data = await res.json();

            totalPages = data.pagination.totalPages;
            if (totalCount) totalCount.textContent = `${data.pagination.totalItems} item${data.pagination.totalItems !== 1 ? 's' : ''}`;

            renderGrid(data.media);
            renderList(data.media);
            renderPagination(data.pagination);
        } catch (e) {
            grid.innerHTML = '<div class="ml-empty"><i class="fa-solid fa-triangle-exclamation"></i><p>Failed to load media.</p></div>';
        }
    };

    // ── Render grid ─────────────────────────────────────────
    const renderGrid = (items) => {
        if (!items || items.length === 0) {
            grid.innerHTML = '<div class="ml-empty"><i class="fa-solid fa-photo-film"></i><p>No media found.</p></div>';
            return;
        }
        grid.innerHTML = '';
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'ml-item' + (selectedIds.has(+item.id) ? ' selected' : '');
            div.dataset.id = item.id;
            div.innerHTML = `
                ${fmt.thumb(item)}
                <div class="ml-item-name">${esc(item.title || item.original_name || item.filename)}</div>
                <div class="ml-item-check"><i class="fa-solid fa-check"></i></div>
            `;
            div.addEventListener('click', (e) => onItemClick(e, item, div));
            grid.appendChild(div);
        });
    };

    // ── Render list ─────────────────────────────────────────
    const renderList = (items) => {
        if (!items || items.length === 0) {
            list.innerHTML = '<div class="ml-empty"><i class="fa-solid fa-photo-film"></i><p>No media found.</p></div>';
            return;
        }
        list.innerHTML = `
            <div class="ml-list-header">
                <div style="width:48px;flex-shrink:0;"></div>
                <div style="flex:2;">Name</div>
                <div style="flex:1;">Type</div>
                <div style="flex:1;">Size</div>
                <div style="flex:1;">Date</div>
            </div>
        `;
        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'ml-list-row' + (selectedIds.has(+item.id) ? ' selected' : '');
            row.dataset.id = item.id;
            row.innerHTML = `
                <div class="ml-list-thumb">${fmt.listThumb(item)}</div>
                <div class="ml-list-name">${esc(item.title || item.original_name || item.filename)}</div>
                <div class="ml-list-meta">${fmt.typeBadge(item.file_type)}</div>
                <div class="ml-list-meta">${fmt.size(item.file_size)}</div>
                <div class="ml-list-meta">${fmt.date(item.created_at)}</div>
            `;
            row.addEventListener('click', (e) => onItemClick(e, item, row));
            list.appendChild(row);
        });
    };

    // ── Item click handler ──────────────────────────────────
    const onItemClick = (e, item, el) => {
        if (isPicker) {
            // Picker mode: send message to parent
            window.parent.postMessage({
                source: 'velion-media-manager',
                action: 'insert_media',
                fileType: item.file_type,
                url: publicUrl(item),
                alt: item.alt_text || item.title || '',
                id: item.id,
            }, '*');
            return;
        }

        if (isSelectMode) {
            toggleSelect(item.id, el);
            return;
        }

        // Normal click: open panel
        document.querySelectorAll('.ml-item.active, .ml-list-row.active').forEach(x => x.classList.remove('active'));
        el.classList.add('active');
        openPanel(item);
    };

    // ── Selection ───────────────────────────────────────────
    const toggleSelect = (id, el) => {
        const numId = +id;
        if (selectedIds.has(numId)) {
            selectedIds.delete(numId);
            el.classList.remove('selected');
        } else {
            selectedIds.add(numId);
            el.classList.add('selected');
        }
        updateBulkBar();
    };

    const updateBulkBar = () => {
        if (selectedIds.size > 0) {
            bulkBar.classList.add('visible');
            bulkCount.textContent = `${selectedIds.size} selected`;
        } else {
            bulkBar.classList.remove('visible');
        }
    };

    const clearSelection = () => {
        selectedIds.clear();
        document.querySelectorAll('.ml-item.selected, .ml-list-row.selected').forEach(x => x.classList.remove('selected'));
        updateBulkBar();
    };

    // ── Panel ───────────────────────────────────────────────
    const openPanel = (item) => {
        currentItem = item;
        panel.classList.add('open');
        mlMain.classList.add('panel-open');

        // Preview
        const url = publicUrl(item);
        if (item.file_type && item.file_type.startsWith('image/')) {
            const large  = (item.urls && item.urls.large)  || url;
            const medium = (item.urls && item.urls.medium) || url;
            panelPreview.innerHTML = `
                <picture>
                    <source srcset="${esc(large)}"  type="image/webp" media="(min-width:600px)">
                    <source srcset="${esc(medium)}" type="image/webp">
                    <img src="${esc(large)}" alt="${esc(item.alt_text || '')}"
                         style="max-width:100%;max-height:100%;object-fit:contain;">
                </picture>`;
        } else if (item.file_type && item.file_type.startsWith('video/')) {
            const videoUrl = (item.urls && item.urls.original) || url;
            const poster   = (item.urls && item.urls.thumbnail) || '';
            panelPreview.innerHTML = `<video src="${esc(videoUrl)}" ${poster ? `poster="${esc(poster)}"` : ''} controls style="max-width:100%;max-height:100%;"></video>`;
        } else {
            const icon = item.file_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file';
            panelPreview.innerHTML = `<div style="color:rgba(255,255,255,.4);font-size:5rem;"><i class="fa-solid ${icon}"></i></div>`;
        }

        // Metadata form
        panelBody.innerHTML = `
            <div class="ml-copy-url-wrap">
                <input type="text" class="ml-copy-url-input" id="ml-url-input" value="${esc(url)}" readonly>
                <button type="button" class="ml-copy-url-btn" id="ml-copy-btn">
                    <i class="fa-solid fa-copy"></i> Copy
                </button>
            </div>

            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" id="ml-f-title" value="${esc(item.title || '')}">
            </div>
            <div class="form-group">
                <label class="form-label">Alt Text</label>
                <input type="text" class="form-control" id="ml-f-alt" value="${esc(item.alt_text || '')}">
                <small style="color:#94a3b8;font-size:.75rem;">Describe the image for accessibility &amp; SEO.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Caption</label>
                <textarea class="form-control" id="ml-f-caption" rows="2">${esc(item.caption || '')}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="ml-f-desc" rows="3">${esc(item.description || '')}</textarea>
            </div>

            <div class="ml-file-info">
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">File name</span>
                    <span class="ml-file-info-value">${esc(item.original_name || item.filename)}</span>
                </div>
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Type</span>
                    <span class="ml-file-info-value">${fmt.typeBadge(item.file_type)}</span>
                </div>
                ${item.image_width ? `
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Dimensions</span>
                    <span class="ml-file-info-value">${item.image_width} × ${item.image_height}px</span>
                </div>` : ''}
                ${item.has_thumbnail || item.has_medium || item.has_large ? `
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Variants</span>
                    <span class="ml-file-info-value">
                        ${item.has_thumbnail ? '<span class="ml-stat-badge image">Thumb</span> ' : ''}
                        ${item.has_medium    ? '<span class="ml-stat-badge image">Medium</span> ' : ''}
                        ${item.has_large     ? '<span class="ml-stat-badge image">Large</span>' : ''}
                    </span>
                </div>` : ''}
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Size</span>
                    <span class="ml-file-info-value">${fmt.size(item.file_size)}</span>
                </div>
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Folder</span>
                    <span class="ml-file-info-value">uploads/${esc(item.storage_path || '')}/</span>
                </div>
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">Uploaded</span>
                    <span class="ml-file-info-value">${fmt.date(item.created_at)}</span>
                </div>
                <div class="ml-file-info-row">
                    <span class="ml-file-info-label">By</span>
                    <span class="ml-file-info-value">${esc(item.uploader_name || '—')}</span>
                </div>
            </div>
        `;

        panelActions.style.display = 'flex';

        // Copy URL
        document.getElementById('ml-copy-btn').addEventListener('click', async () => {
            const btn = document.getElementById('ml-copy-btn');
            try {
                await navigator.clipboard.writeText(url);
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
                }, 2500);
            } catch {
                document.getElementById('ml-url-input').select();
                document.execCommand('copy');
            }
        });
    };

    const closePanel = () => {
        panel.classList.remove('open');
        mlMain.classList.remove('panel-open');
        currentItem = null;
        panelActions.style.display = 'none';
        panelPreview.innerHTML = '<div class="ml-panel-preview-placeholder"><i class="fa-solid fa-image"></i></div>';
        panelBody.innerHTML = '<p class="ml-panel-empty">Select a file to view details</p>';
        document.querySelectorAll('.ml-item.active, .ml-list-row.active').forEach(x => x.classList.remove('active'));
    };

    // ── Save metadata ───────────────────────────────────────
    const saveDetails = async () => {
        if (!currentItem) return;
        const btn = panelSave;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin icon"></i> Saving…';

        const payload = {
            action:      'update_details',
            id:          currentItem.id,
            title:       document.getElementById('ml-f-title')?.value   || '',
            alt_text:    document.getElementById('ml-f-alt')?.value     || '',
            caption:     document.getElementById('ml-f-caption')?.value || '',
            description: document.getElementById('ml-f-desc')?.value    || '',
        };

        try {
            const res  = await fetch(BASE, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.success) {
                // Update local state
                currentItem.title       = payload.title;
                currentItem.alt_text    = payload.alt_text;
                currentItem.caption     = payload.caption;
                currentItem.description = payload.description;
                // Update grid/list label
                document.querySelectorAll(`.ml-item[data-id="${currentItem.id}"] .ml-item-name,
                                           .ml-list-row[data-id="${currentItem.id}"] .ml-list-name`)
                    .forEach(el => el.textContent = payload.title || currentItem.filename);
                btn.innerHTML = '<i class="fa-solid fa-check icon"></i> Saved!';
                setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-floppy-disk icon"></i> Save'; btn.disabled = false; }, 2000);
            } else {
                alert('Error: ' + (data.error || 'Save failed'));
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk icon"></i> Save';
                btn.disabled = false;
            }
        } catch {
            alert('Network error. Please try again.');
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk icon"></i> Save';
            btn.disabled = false;
        }
    };

    // ── Delete single ───────────────────────────────────────
    const deleteSingle = async () => {
        if (!currentItem) return;
        if (!confirm(`Delete "${currentItem.title || currentItem.filename}" permanently? This cannot be undone.`)) return;

        try {
            const res  = await fetch(BASE, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'delete_media', id: currentItem.id }) });
            const data = await res.json();
            if (data.success) {
                closePanel();
                load(currentPage);
            } else {
                alert('Error: ' + (data.error || 'Delete failed'));
            }
        } catch {
            alert('Network error.');
        }
    };

    // ── Bulk delete ─────────────────────────────────────────
    const bulkDeleteFn = async () => {
        if (selectedIds.size === 0) return;
        if (!confirm(`Permanently delete ${selectedIds.size} item(s)? This cannot be undone.`)) return;

        try {
            const res  = await fetch(BASE, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'bulk_delete', ids: Array.from(selectedIds) }) });
            const data = await res.json();
            if (data.success) {
                clearSelection();
                closePanel();
                load(currentPage);
            } else {
                alert('Error: ' + (data.error || 'Bulk delete failed'));
            }
        } catch {
            alert('Network error.');
        }
    };

    // ── Upload ──────────────────────────────────────────────
    const uploadFile = (file) => {
        const id  = 'prog-' + Math.random().toString(36).slice(2, 9);
        const row = document.createElement('div');
        row.className = 'ml-progress-item';
        row.id = id;
        row.innerHTML = `
            <span class="ml-prog-name" title="${esc(file.name)}">${esc(file.name)}</span>
            <div class="ml-prog-bar-wrap"><div class="ml-prog-bar"></div></div>
            <span class="ml-prog-status">0%</span>
        `;
        progressCont.appendChild(row);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD, true);

        xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            const r   = document.getElementById(id);
            if (!r) return;
            r.querySelector('.ml-prog-bar').style.width = pct + '%';
            r.querySelector('.ml-prog-status').textContent = pct + '%';
        };

        xhr.onload = () => {
            const r = document.getElementById(id);
            if (!r) return;
            const bar    = r.querySelector('.ml-prog-bar');
            const status = r.querySelector('.ml-prog-status');
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        bar.style.background = '#10b981';
                        status.textContent = '✓ Done';
                        status.className = 'ml-prog-status done';
                        setTimeout(() => r.remove(), 2500);
                        load(1);
                    } else {
                        bar.style.background = '#ef4444';
                        status.textContent = 'Error';
                        status.className = 'ml-prog-status error';
                        r.querySelector('.ml-prog-name').title += ': ' + (res.error || 'Upload failed');
                    }
                } catch {
                    bar.style.background = '#ef4444';
                    status.textContent = 'Error';
                    status.className = 'ml-prog-status error';
                }
            } else {
                bar.style.background = '#ef4444';
                status.textContent = 'Failed';
                status.className = 'ml-prog-status error';
            }
        };

        const fd = new FormData();
        fd.append('file', file);
        xhr.send(fd);
    };

    // ── Pagination ──────────────────────────────────────────
    const renderPagination = ({ currentPage: cp, totalPages: tp, totalItems }) => {
        if (!pagination) return;
        if (tp <= 1) { pagination.innerHTML = ''; return; }

        let html = '';
        html += `<button class="ml-page-btn" data-pg="${cp - 1}" ${cp <= 1 ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-left"></i>
                 </button>`;

        // Show up to 7 page buttons with ellipsis
        const range = [];
        for (let i = 1; i <= tp; i++) {
            if (i === 1 || i === tp || (i >= cp - 2 && i <= cp + 2)) range.push(i);
            else if (range[range.length - 1] !== '…') range.push('…');
        }
        range.forEach(p => {
            if (p === '…') {
                html += `<span style="padding:0 .25rem;color:#94a3b8;">…</span>`;
            } else {
                html += `<button class="ml-page-btn${p === cp ? ' active' : ''}" data-pg="${p}">${p}</button>`;
            }
        });

        html += `<button class="ml-page-btn" data-pg="${cp + 1}" ${cp >= tp ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-right"></i>
                 </button>`;
        pagination.innerHTML = html;
    };

    // ── View toggle ─────────────────────────────────────────
    const setView = (v) => {
        currentView = v;
        if (v === 'grid') {
            grid.style.display = '';
            list.style.display = 'none';
            viewGridBtn.classList.add('active');
            viewListBtn.classList.remove('active');
        } else {
            grid.style.display = 'none';
            list.style.display = '';
            viewListBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
        }
    };

    // ── Upload zone collapse ────────────────────────────────
    const toggleUpload = () => {
        const isOpen = uploadBody.style.display !== 'none';
        uploadBody.style.display = isOpen ? 'none' : '';
        uploadChevron.style.transform = isOpen ? 'rotate(-90deg)' : '';
    };

    // ── Event listeners ─────────────────────────────────────

    // Upload zone
    if (dropZone) {
        dropZone.addEventListener('dragover',  (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            [...e.dataTransfer.files].forEach(uploadFile);
        });
    }
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            [...fileInput.files].forEach(uploadFile);
            fileInput.value = '';
        });
    }

    // Upload toggle
    if (uploadToggle) uploadToggle.addEventListener('click', toggleUpload);
    if (uploadOpenBtn) uploadOpenBtn.addEventListener('click', () => {
        uploadBody.style.display = '';
        uploadChevron.style.transform = '';
        uploadBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Search (debounced)
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => load(1), 400);
        });
    }

    // Filters
    if (typeFilter) typeFilter.addEventListener('change', () => load(1));
    if (sortSelect) sortSelect.addEventListener('change', () => load(1));

    // View toggle
    if (viewGridBtn) viewGridBtn.addEventListener('click', () => setView('grid'));
    if (viewListBtn) viewListBtn.addEventListener('click', () => setView('list'));

    // Select mode
    if (selectToggle) {
        selectToggle.addEventListener('click', () => {
            isSelectMode = !isSelectMode;
            selectToggle.classList.toggle('active', isSelectMode);
            document.body.classList.toggle('ml-select-mode', isSelectMode);
            if (!isSelectMode) clearSelection();
        });
    }

    // Panel close
    if (panelClose) panelClose.addEventListener('click', closePanel);
    if (panelSave)  panelSave.addEventListener('click', saveDetails);
    if (panelDelete) panelDelete.addEventListener('click', deleteSingle);

    // Bulk actions
    if (bulkDelete) bulkDelete.addEventListener('click', bulkDeleteFn);
    if (bulkCancel) bulkCancel.addEventListener('click', () => {
        isSelectMode = false;
        selectToggle && selectToggle.classList.remove('active');
        document.body.classList.remove('ml-select-mode');
        clearSelection();
    });

    // Pagination
    if (pagination) {
        pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.ml-page-btn');
            if (btn && !btn.disabled) {
                const pg = parseInt(btn.dataset.pg);
                if (!isNaN(pg) && pg >= 1 && pg <= totalPages) load(pg);
            }
        });
    }

    // Keyboard: Escape closes panel
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (panel.classList.contains('open')) closePanel();
        }
    });

    // ── Init ────────────────────────────────────────────────
    // Collapse upload zone by default (cleaner initial view)
    if (uploadBody) uploadBody.style.display = 'none';
    if (uploadChevron) uploadChevron.style.transform = 'rotate(-90deg)';

    load(1);
});
