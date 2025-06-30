/**
 * Raj News - Main Frontend JavaScript File (v11.1 - Robustness Fix)
 */

document.addEventListener('DOMContentLoaded', function () {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        const currentTheme = localStorage.getItem('theme');

        function applyTheme(theme) {
            if (theme === 'dark-mode') {
                document.body.classList.add('dark-mode');
                themeToggle.checked = true;
            } else {
                document.body.classList.remove('dark-mode');
                themeToggle.checked = false;
            }
        }

        // Apply theme on initial load.
        // If a theme is saved in localStorage, use it. Otherwise, it will default to light.
        if (currentTheme) {
            applyTheme(currentTheme);
        } else {
            // No theme saved, ensure it's light mode.
            applyTheme('light-mode');
        }

        // The event listener for changing the theme remains the same.
        themeToggle.addEventListener('change', function () {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light-mode');
            }
        });
    }


    // --- Search Bar Toggle Logic ---
    const searchIcon = document.getElementById('search-icon');
    const searchBarContainer = document.getElementById('search-bar-container');
    if (searchIcon && searchBarContainer) { // Check if elements exist
        searchIcon.addEventListener('click', function () {
            searchBarContainer.classList.toggle('active');
            if (searchBarContainer.classList.contains('active')) {
                searchBarContainer.querySelector('input').focus();
            }
        });
    }


    // --- Profile Dropdown Logic ---
    const profileIcon = document.getElementById('profile-icon');
    const profileDropdown = document.getElementById('profile-dropdown');
    if (profileIcon && profileDropdown) { // Check if elements exist
        profileIcon.addEventListener('click', function (event) {
            event.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function (event) {
            if (!profileDropdown.contains(event.target) && !profileIcon.contains(event.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    }


    // --- LIKE & BOOKMARK AJAX (More Robust) ---
    function handleAction(buttonId, actionName) {
        const actionButton = document.getElementById(buttonId);
        // CRITICAL FIX: Only proceed if the button actually exists on the current page.
        if (actionButton) {
            actionButton.addEventListener('click', function () {
                const postId = this.dataset.postId;
                const formData = new FormData();
                formData.append('action', actionName);
                formData.append('post_id', postId);

                fetch('/raj-news/ajax-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) { throw new Error('Network response was not ok'); }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'error') {
                            alert(data.message);
                            return;
                        }

                        if (actionName === 'toggle_like') {
                            const likeCountSpan = document.getElementById('like-count');
                            const text = this.querySelector('span');
                            if (data.status === 'liked') {
                                this.classList.remove('btn-outline-danger');
                                this.classList.add('btn-danger');
                                text.textContent = 'Liked';
                            } else {
                                this.classList.remove('btn-danger');
                                this.classList.add('btn-outline-danger');
                                text.textContent = 'Like';
                            }
                            if (likeCountSpan) likeCountSpan.textContent = `${data.new_count} Likes`;
                        }

                        if (actionName === 'toggle_bookmark') {
                            const text = this.querySelector('span');
                            if (data.status === 'bookmarked') {
                                this.classList.remove('btn-outline-primary');
                                this.classList.add('btn-primary');
                                text.textContent = 'Saved';
                            } else {
                                this.classList.remove('btn-primary');
                                this.classList.add('btn-outline-primary');
                                text.textContent = 'Save for Later';
                            }
                        }
                    })
                    .catch(error => console.error('Error handling action:', error));
            });
        }
    }

    handleAction('like-btn', 'toggle_like');
    handleAction('bookmark-btn', 'toggle_bookmark');


    // --- AJAX Load More Logic ---
    document.querySelectorAll('.load-more-btn').forEach(button => {
        button.addEventListener('click', function () {
            const categoryId = this.dataset.category;
            let offset = parseInt(this.dataset.offset);
            const container = document.getElementById('post-container-' + categoryId);

            this.textContent = 'Loading...';
            this.disabled = true;

            const formData = new FormData();
            formData.append('category_id', categoryId);
            formData.append('offset', offset);

            fetch('/raj-news/ajax-load-more.php', { method: 'POST', body: formData })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'no-more') {
                        this.textContent = 'No More News';
                        this.style.display = 'none';
                    } else {
                        container.insertAdjacentHTML('beforeend', data);
                        offset += 4;
                        this.dataset.offset = offset;
                        this.textContent = 'Load More';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.textContent = 'Error! Try Again';
                    this.disabled = false;
                });
        });
    });
    // --- STICKY NAVIGATION BAR ---
    // --- STICKY HEADER ---
    const mainHeader = document.getElementById('main-header');
    if (mainHeader) {
        // We make it sticky almost immediately for a modern feel
        const stickyPoint = 10;

        window.onscroll = function () {
            if (window.pageYOffset > stickyPoint) {
                mainHeader.classList.add("is-sticky");
            } else {
                mainHeader.classList.remove("is-sticky");
            }
        };
    }

}); // End DOMContentLoaded