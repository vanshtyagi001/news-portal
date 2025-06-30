<?php
/**
 * Velion Editor - Reusable Component
 *
 * This file contains the complete HTML structure for the Velion WYSIWYG editor.
 * It is designed to be included in other pages like add-news.php and edit-news.php.
 *
 * It expects one optional variable to be defined before it's included:
 * @var string|null $editor_content The existing HTML content to populate the editor with (for editing).
 */
?>

<!-- ===================================================== -->
<!-- START: VELION WYSIWYG EDITOR COMPONENT                -->
<!-- ===================================================== -->
<div class="form-group">
    <label>Full Content</label>
    
    <div class="editor-container" id="velionEditorContainer">
        <div class="editor-header">
            <h1 class="editor-title">Velion WYSIWYG Editor</h1>
            <div class="editor-actions">
                <button type="button" class="theme-toggle" onclick="toggleTheme()"><i class="fas fa-moon"></i> Dark Mode</button>
                <button type="button" class="mode-toggle" onclick="toggleEditMode()"><i class="fas fa-edit"></i> Balloon Mode</button>
            </div>
        </div>

        <div class="toolbar">
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="toggleFormat('undo')" title="Undo"><i class="fas fa-undo"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('redo')" title="Redo"><i class="fas fa-redo"></i></button></div>
            <div class="toolbar-group"><select class="toolbar-select" onchange="formatBlock(this.value)"><option value="">Format</option><option value="p">Paragraph</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option></select></div>
            <div class="toolbar-group"><select class="toolbar-select" onchange="fontName(this.value)"><option value="">Font Family</option><option value="Arial, Helvetica, sans-serif">Arial</option><option value="'Times New Roman', Times, serif">Times New Roman</option><option value="Georgia, serif">Georgia</option></select><select class="toolbar-select" onchange="fontSize(this.value)"><option value="">Size</option><option value="1">8pt</option><option value="2">10pt</option><option value="3">12pt</option><option value="4">14pt</option></select></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="toggleFormat('bold')" title="Bold"><i class="fas fa-bold"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('italic')" title="Italic"><i class="fas fa-italic"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('underline')" title="Underline"><i class="fas fa-underline"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('strikethrough')" title="Strikethrough"><i class="fas fa-strikethrough"></i></button></div>
            <div class="toolbar-group"><button type="button" class="color-btn" title="Text Color" onclick="showColorModal('foreColor')"><div class="color-btn-indicator" style="background-color: #000000;" id="textColorIndicator"></div></button><button type="button" class="color-btn" title="Background Color" onclick="showColorModal('backColor')"><div class="color-btn-indicator" style="background-color: #ffffff;" id="bgColorIndicator"></div></button></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="toggleFormat('justifyLeft')" title="Align Left"><i class="fas fa-align-left"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('justifyCenter')" title="Align Center"><i class="fas fa-align-center"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('justifyRight')" title="Align Right"><i class="fas fa-align-right"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('justifyFull')" title="Justify"><i class="fas fa-align-justify"></i></button></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="toggleFormat('insertUnorderedList')" title="Bullet List"><i class="fas fa-list-ul"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('insertOrderedList')" title="Numbered List"><i class="fas fa-list-ol"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('outdent')" title="Decrease Indent"><i class="fas fa-outdent"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('indent')" title="Increase Indent"><i class="fas fa-indent"></i></button></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="insertQuote()" title="Blockquote"><i class="fas fa-quote-left"></i></button><button type="button" class="toolbar-btn" onclick="insertCode()" title="Code"><i class="fas fa-code"></i></button><button type="button" class="toolbar-btn" onclick="insertHR()" title="Horizontal Line"><i class="fas fa-minus"></i></button></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="showLinkModal()" title="Insert Link"><i class="fas fa-link"></i></button><button type="button" class="toolbar-btn" onclick="showImageModal()" title="Insert Image"><i class="fas fa-image"></i></button><button type="button" class="toolbar-btn" onclick="showTableModal()" title="Insert Table"><i class="fas fa-table"></i></button><button type="button" class="toolbar-btn" onclick="showVideoModal()" title="Insert Video"><i class="fas fa-video"></i></button></div>
            <div class="toolbar-group"><button type="button" class="toolbar-btn" onclick="showSpecialChars()" title="Special Characters"><i class="fas fa-asterisk"></i></button><button type="button" class="toolbar-btn" onclick="showHtmlModal()" title="HTML Source"><i class="fas fa-code"></i></button><button type="button" class="toolbar-btn" onclick="clearFormatting()" title="Clear Formatting"><i class="fas fa-remove-format"></i></button></div>
        </div>

        <div class="editor-content" id="editor" contenteditable="true"
             ondrop="handleDrop(event)" ondragover="handleDragOver(event)"
             onpaste="handlePaste(event)" onkeyup="updateWordCount()"
             onmouseup="updateToolbar()" onkeydown="handleKeydown(event)">
            <?php 
                if (isset($editor_content) && !empty($editor_content)) {
                    echo $editor_content;
                } else {
                    echo '<p>Start writing your article here...</p>';
                }
            ?>
        </div>

        <div id="imageResizer"><div class="resize-handle br"></div></div>

        <div class="editor-footer">
            <div class="word-count"><span id="wordCount">Words: 0</span><span id="charCount">Characters: 0</span></div>
        </div>
    </div>

    <!-- This hidden textarea is the bridge to the PHP backend -->
    <textarea name="content" id="hiddenContent" style="display: none;"></textarea>
</div>

<!-- All MODALS required by the editor -->
<div class="balloon-toolbar" id="balloonToolbar"><button type="button" class="toolbar-btn" onclick="toggleFormat('bold')" title="Bold"><i class="fas fa-bold"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('italic')" title="Italic"><i class="fas fa-italic"></i></button><button type="button" class="toolbar-btn" onclick="toggleFormat('underline')" title="Underline"><i class="fas fa-underline"></i></button><button type="button" class="toolbar-btn" onclick="showLinkModal()" title="Link"><i class="fas fa-link"></i></button></div>
<div class="modal" id="linkModal"><div class="modal-content"><div class="modal-header">Insert/Edit Link</div><div class="form-group"><label class="form-label">URL</label><input type="url" class="form-input" id="linkUrl" placeholder="https://example.com"></div><div class="form-group"><label class="form-label">Link Text</label><input type="text" class="form-input" id="linkText" placeholder="Link text"></div><div class="form-group"><label><input type="checkbox" id="linkNewTab"> Open in new tab</label></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('linkModal')">Cancel</button><button type="button" class="btn-primary" onclick="insertLink()">Insert Link</button></div></div></div>
<div class="modal" id="imageModal"><div class="modal-content"><div class="modal-header">Insert Image</div><div class="form-group"><label class="form-label">Upload Image</label><input type="file" class="form-input" id="imageFile" accept="image/*" onchange="handleImageUpload(this)"></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('imageModal')">Cancel</button></div></div></div>
<div class="modal" id="tableModal"><div class="modal-content"><div class="modal-header">Insert Table</div><div class="form-group"><label class="form-label">Rows</label><input type="number" class="form-input" id="tableRows" value="3" min="1" max="20"></div><div class="form-group"><label class="form-label">Columns</label><input type="number" class="form-input" id="tableCols" value="3" min="1" max="10"></div><div class="form-group"><label><input type="checkbox" id="tableHeader" checked> Include header row</label></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('tableModal')">Cancel</button><button type="button" class="btn-primary" onclick="insertTable()">Insert Table</button></div></div></div>
<div class="modal" id="videoModal"><div class="modal-content"><div class="modal-header">Insert Video</div><div class="form-group"><label class="form-label">Video URL (YouTube, Vimeo)</label><input type="url" class="form-input" id="videoUrl" placeholder="https://www.youtube.com/watch?v=..."></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('videoModal')">Cancel</button><button type="button" class="btn-primary" onclick="insertVideo()">Insert Video</button></div></div></div>
<div class="modal" id="specialCharsModal"><div class="modal-content"><div class="modal-header">Special Characters</div><div class="special-chars" id="specialCharsContainer"></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('specialCharsModal')">Close</button></div></div></div>
<div class="modal" id="htmlModal"><div class="modal-content"><div class="modal-header">HTML Source</div><div class="form-group"><textarea class="form-input" id="htmlSource" rows="15" style="font-family: monospace;"></textarea></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('htmlModal')">Cancel</button><button type="button" class="btn-primary" onclick="updateFromHtml()">Update Content</button></div></div></div>
<div class="modal" id="colorModal"><div class="modal-content" style="max-width: 320px;"><div class="modal-header" id="colorModalTitle">Select Color</div><div class="form-group"><label class="form-label">Predefined Colors</label><div class="color-swatches" id="colorSwatches"></div></div><div class="form-group"><label class="form-label">Custom Color</label><div class="custom-color-picker"><input type="color" id="customColorInput" value="#000000"><input type="text" class="form-input" id="customColorText" value="#000000"></div></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="closeModal('colorModal')">Cancel</button><button type="button" class="btn-primary" onclick="applyColor()">OK</button></div></div></div>
<!-- ===================================================== -->
<!-- END: EDITOR COMPONENT                                 -->
<!-- ===================================================== -->

<!-- Add this new modal to editor-component.php -->

<div class="modal" id="mediaManagerModal" style="align-items: flex-start; padding-top: 5vh;">
    <div class="modal-content" style="max-width: 90vw; width: 100%; max-height: 90vh;">
        <div class="modal-header">
            Media Library
            <button type="button" class="btn-close" aria-label="Close" onclick="closeModal('mediaManagerModal')"></button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <iframe id="mediaManagerFrame" style="width: 100%; height: 80vh; border: none;"></iframe>
        </div>
    </div>
</div>