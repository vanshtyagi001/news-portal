/**
 * Express News — Main Frontend JavaScript
 * Handles: sticky header, search, profile dropdown,
 *          smooth scroll, card entrance animations,
 *          like/bookmark AJAX, load-more AJAX.
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ─────────────────────────────────────────────
       1. SMOOTH SCROLL — all anchor links on page
    ───────────────────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            const headerH = document.getElementById('main-header')?.offsetHeight || 70;
            const top = target.getBoundingClientRect().top + window.pageYOffset - headerH - 16;
            window.scrollTo({ top, behavior: 'smooth' });
        });
    });


    /* ─────────────────────────────────────────────
       2. CARD ENTRANCE ANIMATIONS (Intersection Observer)
       Cards fade + slide up as they enter the viewport.
    ───────────────────────────────────────────── */
    const observerOptions = {
        threshold: 0.08,
        rootMargin: '0px 0px -40px 0px'
    };

    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('card-visible');
                cardObserver.unobserve(entry.target); // animate once
            }
        });
    }, observerOptions);

    // Observe every news card, hero card, and related article card
    document.querySelectorAll(
        '.news-card, .hero-main-card, .hero-side-card, .related-article-card, .trending-widget, .category-section'
    ).forEach(el => {
        el.classList.add('card-animate');
        cardObserver.observe(el);
    });


    /* ─────────────────────────────────────────────
       3. SEARCH BAR TOGGLE
    ───────────────────────────────────────────── */
    const searchIcon        = document.getElementById('search-icon');
    const searchBarContainer = document.getElementById('search-bar-container');
    if (searchIcon && searchBarContainer) {
        searchIcon.addEventListener('click', function () {
            searchBarContainer.classList.toggle('active');
            if (searchBarContainer.classList.contains('active')) {
                searchBarContainer.querySelector('input')?.focus();
            }
        });
        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') searchBarContainer.classList.remove('active');
        });
    }


    /* ─────────────────────────────────────────────
       4. PROFILE DROPDOWN
    ───────────────────────────────────────────── */
    const profileIcon     = document.getElementById('profile-icon');
    const profileDropdown = document.getElementById('profile-dropdown');
    if (profileIcon && profileDropdown) {
        profileIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });
        document.addEventListener('click', function (e) {
            if (!profileDropdown.contains(e.target) && !profileIcon.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    }


    /* ─────────────────────────────────────────────
       5. STICKY HEADER
    ───────────────────────────────────────────── */
    const mainHeader = document.getElementById('main-header');
    if (mainHeader) {
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const current = window.pageYOffset;
            if (current > 10) {
                mainHeader.classList.add('is-sticky');
            } else {
                mainHeader.classList.remove('is-sticky');
            }
            lastScroll = current;
        }, { passive: true });
    }


    /* ─────────────────────────────────────────────
       6. LIKE & BOOKMARK AJAX
    ───────────────────────────────────────────── */
    function handleAction(buttonId, actionName) {
        const btn = document.getElementById(buttonId);
        if (!btn) return;
        btn.addEventListener('click', function () {
            const postId = this.dataset.postId;
            const fd = new FormData();
            fd.append('action', actionName);
            fd.append('post_id', postId);

            // Disable during request to prevent double-clicks
            btn.disabled = true;

            fetch('/express-news/ajax-handler.php', { method: 'POST', body: fd })
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    btn.disabled = false;
                    if (data.status === 'error') { alert(data.message); return; }

                    if (actionName === 'toggle_like') {
                        const countEl = document.getElementById('like-count');
                        const txt     = btn.querySelector('span');
                        if (data.status === 'liked') {
                            btn.classList.remove('btn-outline-danger');
                            btn.classList.add('btn-danger');
                            if (txt) txt.textContent = 'Liked';
                        } else {
                            btn.classList.remove('btn-danger');
                            btn.classList.add('btn-outline-danger');
                            if (txt) txt.textContent = 'Like';
                        }
                        if (countEl && data.new_count !== undefined) {
                            countEl.textContent = data.new_count + ' Likes';
                        }
                    }

                    if (actionName === 'toggle_bookmark') {
                        const txt = btn.querySelector('span');
                        if (data.status === 'bookmarked') {
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-primary');
                            if (txt) txt.textContent = 'Saved';
                        } else {
                            btn.classList.remove('btn-primary');
                            btn.classList.add('btn-outline-primary');
                            if (txt) txt.textContent = 'Save for Later';
                        }
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    console.error('Action error:', err);
                });
        });
    }

    handleAction('like-btn',     'toggle_like');
    handleAction('bookmark-btn', 'toggle_bookmark');


    /* ─────────────────────────────────────────────
       7. AJAX LOAD MORE
    ───────────────────────────────────────────── */
    document.querySelectorAll('.load-more-btn').forEach(button => {
        button.addEventListener('click', function () {
            const categoryId = this.dataset.category;
            let   offset     = parseInt(this.dataset.offset);
            const container  = document.getElementById('post-container-' + categoryId);

            this.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="animation:spin .8s linear infinite"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Loading…';
            this.disabled = true;

            const fd = new FormData();
            fd.append('category_id', categoryId);
            fd.append('offset', offset);

            fetch('/express-news/ajax-load-more.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(html => {
                    if (html.trim() === 'no-more') {
                        this.style.display = 'none';
                        return;
                    }
                    // Insert new cards
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newCards = [...temp.children];
                    newCards.forEach(card => {
                        card.classList.add('card-animate');
                        container.appendChild(card);
                        // Trigger animation on next frame
                        requestAnimationFrame(() => requestAnimationFrame(() => card.classList.add('card-visible')));
                    });
                    offset += 4;
                    this.dataset.offset = offset;
                    this.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Load More';
                    this.disabled = false;
                })
                .catch(err => {
                    console.error('Load more error:', err);
                    this.innerHTML = 'Error — Try Again';
                    this.disabled = false;
                });
        });
    });

}); // end DOMContentLoaded
