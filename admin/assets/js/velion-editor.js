// ADD THIS to your main DOMContentLoaded event listener at the top of the file
document.addEventListener('DOMContentLoaded', function() {
    const editor = document.getElementById('editor');
    // ... all your existing initialization code ...
    
    // ADD THIS PART
    const mainForm = document.querySelector('form[action*="news.php"]');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            // Sync the editor one last time before the form is sent
            syncEditorToTextarea();
        });
    }


    // In velion-editor.js, inside the DOMContentLoaded listener

window.addEventListener('message', function(event) {
    // Security: Check the message source
    if (event.data.source !== 'velion-media-manager') {
        return;
    }

    if (event.data.action === 'insert_media') {
        let htmlToInsert = '';
        const { fileType, url, alt } = event.data;

        if (fileType.startsWith('image/')) {
            htmlToInsert = `<img src="${url}" alt="${alt}">`;
        } else if (fileType.startsWith('video/')) {
            htmlToInsert = `<video src="${url}" controls></video>`;
        } else {
            // For PDFs and other docs, insert a link
            htmlToInsert = `<a href="${url}">${alt || url.split('/').pop()}</a>`;
        }

        // Restore focus to the editor and insert the content
        document.getElementById('editor').focus();
        document.execCommand('insertHTML', false, htmlToInsert);
        saveState(); // Save the state after insertion
        closeModal('mediaManagerModal');
    }
});


});
let currentRange = null;
let balloonMode = false;
let isDarkMode = false;
let editorHistory = [];
let historyIndex = -1;
let selectedImage = null; // For image resizing
let currentColorCommand = null; // 'foreColor' or 'backColor'


// Initialize editor
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('editor');
    updateWordCount();
    saveState();

    setInterval(autoSave, 30000);

    initSpecialChars();
    initColorPicker();
    setupImageResizing();

    editor.addEventListener('paste', handlePaste);
    editor.addEventListener('blur', () => hideResizer());
    document.addEventListener('selectionchange', () => {
        handleSelectionChange();
        updateToolbar();
    });
});

// Core formatting functions
function toggleFormat(command, value = null) {
    document.execCommand(command, false, value);
    updateToolbar();
    saveState();
}

function formatBlock(tag) {
    if (tag) {
        document.execCommand('formatBlock', false, tag);
        saveState();
    }
}

function fontName(font) {
    if (font) {
        document.execCommand('fontName', false, font);
        saveState();
    }
}

function fontSize(size) {
    if (size) {
        document.execCommand('fontSize', false, size);
        saveState();
    }
}

// Color Picker Logic
function initColorPicker() {
    const swatchesContainer = document.getElementById('colorSwatches');
    const predefinedColors = [
        '#000000', '#444444', '#666666', '#999999', '#CCCCCC', '#EEEEEE', '#F3F3F3', '#FFFFFF',
        '#FF0000', '#FF9900', '#FFFF00', '#00FF00', '#00FFFF', '#0000FF', '#9900FF', '#FF00FF',
        '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4',
        '#009688', '#4CAF50', '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800', '#FF5722'
    ];

    predefinedColors.forEach(color => {
        const swatch = document.createElement('div');
        swatch.className = 'color-swatch';
        swatch.style.backgroundColor = color;
        swatch.dataset.color = color;
        swatch.onclick = () => selectSwatch(color);
        swatchesContainer.appendChild(swatch);
    });

    const customColorInput = document.getElementById('customColorInput');
    const customColorText = document.getElementById('customColorText');
    customColorInput.addEventListener('input', () => customColorText.value = customColorInput.value);
    customColorText.addEventListener('input', () => customColorInput.value = customColorText.value);
}

function showColorModal(command) {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        currentRange = selection.getRangeAt(0).cloneRange();
    }
    currentColorCommand = command;
    document.getElementById('colorModalTitle').textContent = command === 'foreColor' ? 'Select Text Color' : 'Select Highlight Color';
    showModal('colorModal');
}

function selectSwatch(color) {
    document.getElementById('customColorInput').value = color;
    document.getElementById('customColorText').value = color;
}

function applyColor() {
    const color = document.getElementById('customColorInput').value;
    if (currentRange) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(currentRange);
    }
    if (currentColorCommand && color) {
        document.execCommand(currentColorCommand, false, color);
    }
    closeModal('colorModal');
    saveState();
    updateToolbar();
}

// Advanced formatting functions
function insertQuote() {
    toggleFormat('formatBlock', 'blockquote');
}

function insertCode() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        const selectedText = selection.toString();
        if (selectedText.includes('\n')) {
            const pre = document.createElement('pre');
            const code = document.createElement('code');
            code.textContent = selectedText;
            pre.appendChild(code);

            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(pre);
        } else {
            document.execCommand('insertHTML', false, `<code>${selectedText || 'code'}</code>`);
        }
        saveState();
    }
}

function insertHR() {
    document.execCommand('insertHorizontalRule', false, null);
    saveState();
}

function clearFormatting() {
    document.execCommand('removeFormat', false, null);
    document.execCommand('unlink', false, null);
    saveState();
}

// Modal functions
function showModal(modalId) { document.getElementById(modalId).classList.add('active'); }
function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }

function showLinkModal() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        currentRange = selection.getRangeAt(0).cloneRange();
    }
    document.getElementById('linkText').value = selection.toString();
    document.getElementById('linkUrl').value = '';
    showModal('linkModal');
}

function insertLink() {
    const url = document.getElementById('linkUrl').value;
    const text = document.getElementById('linkText').value;
    if (!url || !text) return;

    if (currentRange) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(currentRange);
    }
    const newTab = document.getElementById('linkNewTab').checked;
    const html = `<a href="${url}" ${newTab ? 'target="_blank"' : ''}>${text}</a>`;
    document.execCommand('insertHTML', false, html);
    closeModal('linkModal');
    saveState();
}
// In velion-editor.js

function showImageModal() {
    // We now open the Media Manager in an iframe.
    // We add ?picker=true to the URL to tell the page it's in selection mode.
    const mediaFrame = document.getElementById('mediaManagerFrame');
    mediaFrame.src = 'manage-media.php?picker=true';
    showModal('mediaManagerModal');
}

// DELETE the old handleImageUpload and insertImage functions

// ADD THIS NEW FUNCTION in their place
function handleImageUpload(input) {
    const file = input.files[0];
    if (!file) return;

    showToast('Uploading image...', 'info');

    const formData = new FormData();
    formData.append('image', file);

    fetch('editor-upload-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Insert the image using the URL from the server
            const html = `<img src="${data.url}" alt="User uploaded image">`;
            document.execCommand('insertHTML', false, html);
            closeModal('imageModal');
            saveState();
            showToast('Image uploaded successfully!', 'success');
        } else {
            // Show an error message from the server
            showToast(`Upload Error: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        console.error('Upload failed:', error);
        showToast('A network error occurred during upload.', 'error');
    });
    
    // Clear the file input for the next upload
    input.value = '';
}



function showTableModal() {
    showModal('tableModal');
}

function insertTable() {
    const rows = parseInt(document.getElementById('tableRows').value);
    const cols = parseInt(document.getElementById('tableCols').value);
    const hasHeader = document.getElementById('tableHeader').checked;
    let html = '<table>';
    if (hasHeader) {
        html += '<thead><tr>';
        for (let j = 0; j < cols; j++) html += '<th>Header</th>';
        html += '</tr></thead>';
    }
    html += '<tbody>';
    for (let i = 0; i < (hasHeader ? rows - 1 : rows); i++) {
        html += '<tr>';
        for (let j = 0; j < cols; j++) html += '<td> </td>';
        html += '</tr>';
    }
    html += '</tbody></table><p> </p>';
    document.execCommand('insertHTML', false, html);
    closeModal('tableModal');
    saveState();
}

function showVideoModal() {
    showModal('videoModal');
}

function insertVideo() {
    const url = document.getElementById('videoUrl').value;
    if (url) {
        let embedUrl = '';
        if (url.includes('youtube.com/watch')) {
            const videoId = new URL(url).searchParams.get('v');
            if (videoId) embedUrl = `https://www.youtube.com/embed/${videoId}`;
        } else if (url.includes('youtu.be/')) {
            const videoId = new URL(url).pathname.slice(1);
            if (videoId) embedUrl = `https://www.youtube.com/embed/${videoId}`;
        } else if (url.includes('vimeo.com/')) {
            const videoId = new URL(url).pathname.slice(1);
            if (videoId) embedUrl = `https://player.vimeo.com/video/${videoId}`;
        }

        if (embedUrl) {
            const html = `
                        <div class="video-container" contenteditable="false">
                            <iframe src="${embedUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                        <p><br></p>`;
            document.execCommand('insertHTML', false, html);
            closeModal('videoModal');
            saveState();
        } else {
            showToast('Could not parse video URL. Please use a valid YouTube or Vimeo link.', 'error');
        }
    }
}

function showSpecialChars() {
    showModal('specialCharsModal');
}

function initSpecialChars() {
    const chars = [
        '©', '®', '™', '€', '£', '¥', '¢', '§', '¶', '†', '‡', '•', '…', '‹', '›', '«', '»',
        'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï',
        'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'Þ', 'ß',
        'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï',
        'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ',
        'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π',
        'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω', '∞', '∫', '∑', '∏', '√', '∆', '∇', '∂'
    ];
    const container = document.getElementById('specialCharsContainer');
    container.innerHTML = '';
    chars.forEach(char => {
        const span = document.createElement('span');
        span.className = 'special-char';
        span.textContent = char;
        span.onclick = () => {
            document.execCommand('insertText', false, char);
            saveState();
        };
        container.appendChild(span);
    });
}

function showHtmlModal() {
    document.getElementById('htmlSource').value = document.getElementById('editor').innerHTML;
    showModal('htmlModal');
}

function updateFromHtml() {
    const html = document.getElementById('htmlSource').value;
    const temp = document.createElement('div');
    temp.innerHTML = html;
    temp.querySelectorAll('script, [onclick], [onerror]').forEach(el => el.remove());
    document.getElementById('editor').innerHTML = temp.innerHTML;
    closeModal('htmlModal');
    updateWordCount();
    saveState();
}

// Image Resizing Logic
function setupImageResizing() {
    const editor = document.getElementById('editor');
    const resizer = document.getElementById('imageResizer');
    const handle = resizer.querySelector('.resize-handle.br');
    const editorContainer = document.querySelector('.editor-container');

    let startX, startWidth;

    const showResizer = (img) => {
        selectedImage = img;
        const editorRect = editorContainer.getBoundingClientRect();
        const imgRect = img.getBoundingClientRect();

        resizer.style.display = 'block';
        resizer.style.left = `${imgRect.left - editorRect.left + editor.scrollLeft}px`;
        resizer.style.top = `${imgRect.top - editorRect.top + editor.scrollTop}px`;
        resizer.style.width = `${img.offsetWidth}px`;
        resizer.style.height = `${img.offsetHeight}px`;
    };

    window.hideResizer = () => {
        if (selectedImage) selectedImage = null;
        if (resizer) resizer.style.display = 'none';
    };

    const doResize = (e) => {
        if (!selectedImage) return;
        const newWidth = startWidth + (e.clientX - startX);
        if (newWidth > 20) { // Minimum width
            selectedImage.style.width = `${newWidth}px`;
            selectedImage.style.height = 'auto'; // Maintain aspect ratio
            showResizer(selectedImage);
        }
    };

    const stopResize = () => {
        window.removeEventListener('mousemove', doResize);
        window.removeEventListener('mouseup', stopResize);
        saveState();
    };

    handle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        startX = e.clientX;
        startWidth = selectedImage.offsetWidth;
        window.addEventListener('mousemove', doResize);
        window.addEventListener('mouseup', stopResize);
    });

    editor.addEventListener('click', (e) => {
        if (e.target.tagName === 'IMG') {
            e.stopPropagation();
            showResizer(e.target);
        } else {
            hideResizer();
        }
    });
    editor.addEventListener('scroll', hideResizer);
}

// --- Other Functions (History, Keyboard, UI, etc.) ---
function saveState() {
    const editor = document.getElementById('editor');
    const currentState = editor.innerHTML;
    if (historyIndex < editorHistory.length - 1) {
        editorHistory = editorHistory.slice(0, historyIndex + 1);
    }
    editorHistory.push(currentState);
    historyIndex++;
    if (editorHistory.length > 50) {
        editorHistory.shift();
        historyIndex--;
    }
}
function undo() { if (historyIndex > 0) { historyIndex--; document.getElementById('editor').innerHTML = editorHistory[historyIndex]; } }
function redo() { if (historyIndex < editorHistory.length - 1) { historyIndex++; document.getElementById('editor').innerHTML = editorHistory[historyIndex]; } }

function handleKeydown(e) {
    if (e.ctrlKey || e.metaKey) {
        switch (e.key.toLowerCase()) {
            case 'b': e.preventDefault(); toggleFormat('bold'); break;
            case 'i': e.preventDefault(); toggleFormat('italic'); break;
            case 'u': e.preventDefault(); toggleFormat('underline'); break;
            case 'k': e.preventDefault(); showLinkModal(); break;
            case 'z': e.preventDefault(); e.shiftKey ? redo() : undo(); break;
            case 'y': e.preventDefault(); redo(); break;
            case 's': e.preventDefault(); saveContent(); break;
        }
    }
}

function updateToolbar() {
    ['bold', 'italic', 'underline', 'strikethrough'].forEach(cmd => {
        const btn = document.querySelector(`button[onclick="toggleFormat('${cmd}')"]`);
        if (btn) btn.classList.toggle('active', document.queryCommandState(cmd));
    });
    try {
        const foreColorVal = document.queryCommandValue('foreColor');
        document.getElementById('textColorIndicator').style.backgroundColor = foreColorVal || 'transparent';
        const backColorVal = document.queryCommandValue('backColor');
        document.getElementById('bgColorIndicator').style.backgroundColor = backColorVal || 'transparent';
    } catch (e) { }
}

function handleSelectionChange() {
    if (balloonMode) {
        const selection = window.getSelection();
        const balloonToolbar = document.getElementById('balloonToolbar');
        if (selection.rangeCount > 0 && !selection.isCollapsed) {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            balloonToolbar.style.left = `${rect.left + (rect.width / 2) - (balloonToolbar.offsetWidth / 2)}px`;
            balloonToolbar.style.top = `${rect.top - balloonToolbar.offsetHeight - 10 + window.scrollY}px`;
            balloonToolbar.classList.add('active');
        } else {
            balloonToolbar.classList.remove('active');
        }
    }
}

function updateWordCount() {
    const text = document.getElementById('editor').innerText || '';
    const words = text.trim().split(/\s+/).filter(Boolean).length;
    document.getElementById('wordCount').textContent = `Words: ${words}`;
    document.getElementById('charCount').textContent = `Characters: ${text.length}`;
}
function toggleTheme() {
    isDarkMode = !isDarkMode;
    const editorContainer = document.getElementById('velionEditorContainer');
    if (editorContainer) {
        editorContainer.dataset.theme = isDarkMode ? 'dark' : '';
    }
    document.querySelector('.theme-toggle').innerHTML = isDarkMode ? '<i class="fas fa-sun"></i> Light Mode' : '<i class="fas fa-moon"></i> Dark Mode';
}
function toggleEditMode() { balloonMode = !balloonMode; document.querySelector('.toolbar').style.display = balloonMode ? 'none' : 'flex'; document.querySelector('.mode-toggle').innerHTML = balloonMode ? '<i class="fas fa-bars"></i> Normal Mode' : '<i class="fas fa-edit"></i> Balloon Mode'; if (!balloonMode) document.getElementById('balloonToolbar').classList.remove('active'); }
// FIND this function
function saveContent() {
    // REPLACE the old content with this:
    syncEditorToTextarea();
    const mainForm = document.querySelector('form'); // Find the main <form> on the page
    if (mainForm) {
        // This is just for feedback, the real submission happens when the user clicks the main "Publish/Update" button
        showToast('Content synchronized for saving!', 'success');
    }
}
function autoSave() { console.log('Auto-saving content...'); }
function showToast(message, type = 'info') { const toast = document.createElement('div'); toast.className = `toast ${type}`; toast.textContent = message; document.body.appendChild(toast); setTimeout(() => toast.classList.add('show'), 100); setTimeout(() => { toast.classList.remove('show'); setTimeout(() => document.body.removeChild(toast), 300); }, 3000); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal')) e.target.classList.remove('active'); });

// Drag & Drop / Paste are simple enough to keep their functions minimal here
function handleDragOver(e) { e.preventDefault(); document.getElementById('dragOverlay').classList.add('active'); }
function handleDrop(e) {
    e.preventDefault(); document.getElementById('dragOverlay').classList.remove('active');
    [...e.dataTransfer.files].forEach(file => { if (file.type.startsWith('image/')) { const reader = new FileReader(); reader.onload = event => document.execCommand('insertHTML', false, `<img src="${event.target.result}">`); reader.readAsDataURL(file); } });
}
function handlePaste(e) {
    const items = (e.clipboardData || window.clipboardData).items;
    for (const item of items) { if (item.type.startsWith('image/')) { e.preventDefault(); const file = item.getAsFile(); const reader = new FileReader(); reader.onload = event => document.execCommand('insertHTML', false, `<img src="${event.target.result}">`); reader.readAsDataURL(file); return; } }
}
function exportContent(format) {
    hideResizer();
    const editor = document.getElementById('editor');
    let content = format === 'html' ? editor.innerHTML : htmlToMarkdown(editor.innerHTML);
    downloadFile(content, `document.${format}`, `text/${format}`);
}
function htmlToMarkdown(html) {
    let tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    // Basic conversion, can be improved
    tempDiv.querySelectorAll('h1').forEach(el => el.outerHTML = `# ${el.textContent}\n\n`);
    tempDiv.querySelectorAll('h2').forEach(el => el.outerHTML = `## ${el.textContent}\n\n`);
    tempDiv.querySelectorAll('strong, b').forEach(el => el.outerHTML = `**${el.textContent}**`);
    tempDiv.querySelectorAll('em, i').forEach(el => el.outerHTML = `*${el.textContent}*`);
    tempDiv.querySelectorAll('li').forEach(el => el.outerHTML = `* ${el.textContent}\n`);
    tempDiv.querySelectorAll('p').forEach(el => el.outerHTML = `${el.innerHTML}\n\n`);
    return tempDiv.textContent.replace(/ /g, ' ').trim();
}
function downloadFile(content, filename, mimeType) { const blob = new Blob([content], { type: mimeType }); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename; a.click(); URL.revokeObjectURL(a.href); }

// IN admin/assets/js/velion-editor.js

// ADD THIS NEW FUNCTION
function syncEditorToTextarea() {
    const editor = document.getElementById('editor');
    const hiddenTextarea = document.getElementById('hiddenContent');
    if (editor && hiddenTextarea) {
        hiddenTextarea.value = editor.innerHTML;
    }
}