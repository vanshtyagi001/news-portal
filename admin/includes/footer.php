        </div> <!-- End .main-content -->
    </div> <!-- End .admin-wrapper -->
    
    <script src="assets/js/script.js"></script>

    <!-- REMOVE the old CKEditor initialization script -->
    <!-- 
    <script>
        if (document.querySelector('#content')) {
            CKEDITOR.replace('content');
        }
    </script> 
    -->

    <!-- Load the new editor's javascript -->
    <script src="assets/js/velion-editor.js"></script>

    <?php if (basename($_SERVER['PHP_SELF']) == 'manage-media.php'): ?>
    <!-- ADD THIS NEW SCRIPT FOR THE MEDIA MANAGER -->
    <script src="assets/js/media-manager.js"></script>
<?php endif; ?>
    
</body>
</html>