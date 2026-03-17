# Raj News Portal

A full-stack PHP news publishing platform with a public news site, user accounts, and a role-based admin panel.

## Overview

This project is a classic PHP + MySQL application organized into three layers:

1. Public website for browsing news by category, tag, and search.
2. User area for registration, login, profile, bookmarks, and comments.
3. Admin dashboard for content, categories, comments, media, ads, and settings.

The app uses clean URL rewriting (Apache `.htaccess`) and stores content in MySQL.

## Tech Stack

- Backend: PHP (procedural), MySQLi, sessions
- Database: MySQL / MariaDB
- Frontend: Bootstrap 5, custom CSS, vanilla JavaScript
- Rich text editor: custom Velion editor (in admin)
- Media processing: PHP GD (`imagejpeg`, `imagewebp`, resize/compress)

## Key Features

- Homepage with:
  - top headlines carousel
  - configurable breaking-news ticker
  - category-wise latest posts with Load More (AJAX)
- Article page with:
  - SEO metadata (Open Graph + JSON-LD)
  - view count tracking
  - likes and bookmarks via AJAX
  - related posts and trending posts
  - moderated comments
- User account system:
  - register/login/logout
  - profile and avatar
  - bookmarked articles
  - password and profile management
- Admin system with RBAC:
  - create/edit/delete posts
  - category management
  - comment moderation
  - admin account management
  - ad management and ad placements
  - site settings (name, logo, favicon, ticker speed, feature toggles)

## Project Structure

- `index.php`: homepage
- `news.php`: single article page
- `category.php`, `tag.php`, `search.php`: archive and search pages
- `ajax-handler.php`: like/bookmark actions
- `ajax-load-more.php`: homepage pagination by category
- `sitemap.php`: XML sitemap output
- `article-redirect.php`: short URL (`/article/{id}`) redirect to slug URL
- `includes/`: public header/footer and ad renderer
- `admin/`: dashboard and all admin modules
- `user/`: user authentication and profile pages
- `uploads/`: uploaded media and generated image formats

## URL Routing

Defined in `.htaccess` (Apache `mod_rewrite`):

- `/news/{slug}` -> `news.php?slug={slug}`
- `/category/{slug}` -> `category.php?slug={slug}`
- `/tag/{slug}` -> `tag.php?slug={slug}`
- `/article/{id}` -> `article-redirect.php?id={id}` (redirect to slug URL)
- `/sitemap.xml` -> `sitemap.php`

## Important Runtime Assumptions

- Base path is hardcoded in many places as `/raj-news/`.
- Database credentials are hardcoded in `admin/includes/db.php`.
- Apache rewrite rules are required for pretty URLs.
- `uploads/` and `assets/images/` must be writable by the web server.

## Prerequisites

- PHP 7.1+ recommended
- MySQL 5.7+ (or compatible MariaDB)
- Apache with `mod_rewrite` enabled
- PHP extensions:
  - `mysqli`
  - `gd`
  - `session`
  - `filter`

## Installation (Local)

1. Copy project to your web root.
2. Keep the folder name aligned with rewrite/base-path usage (`raj-news`) or update all hardcoded `/raj-news/` paths.
3. Create database:

```sql
CREATE DATABASE raj_news_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. Configure DB credentials in `admin/includes/db.php`:

```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'raj_news_db');
```

5. Create required folders and permissions:
- `uploads/` writable
- `assets/images/` writable

6. Add placeholder assets used by image fallback logic:
- `assets/images/placeholder.jpg`
- `assets/images/placeholder.webp`

7. Start Apache/MySQL and open:
- Public site: `http://localhost/raj-news/`
- Admin login: `http://localhost/raj-news/admin/`

## Database Schema (Inferred)

No SQL dump is included in the repository. The following tables are required by the code:

- `admins`
  - `id`, `username`, `password`, `full_name`, `role`, `created_at`
- `users`
  - `id`, `username`, `email`, `password`, `avatar`, `created_at`
- `posts`
  - `id`, `title`, `slug`, `summary`, `content`, `featured_image`, `author_id`, `view_count`, `likes_count`, `created_at`, `updated_at`
- `categories`
  - `id`, `name`, `slug`
- `post_categories`
  - `post_id`, `category_id`
- `tags`
  - `id`, `name`, `slug`
- `post_tags`
  - `post_id`, `tag_id`
- `comments`
  - `id`, `post_id`, `name`, `comment`, `is_approved`, `created_at`
- `post_likes`
  - `id`, `user_id`, `post_id`
- `user_bookmarks`
  - `id` (optional in some designs), `user_id`, `post_id`, `created_at`
- `ads`
  - `id`, `ad_name`, `ad_type`, `ad_content`, `ad_link`, `hook_name`, `is_active`, `display_order`, `created_at`
- `ad_hooks`
  - `hook_name`, `description`
- `media`
  - `id`, `filename`, `file_type`, `file_size`, `title`, `alt_text`, `caption`, `description`, `uploader_id`, `is_image_optimized`, `created_at`
- `settings`
  - `setting_name`, `setting_value`

## Roles and Access Control

Admin roles detected in code:

- `super_admin`
  - full access to all admin modules
- `editor`
  - news + comment moderation access
- `author`
  - role exists in signup options; access depends on page-level checks (primarily news flow)

Super admin-only modules include:

- manage categories
- manage admins
- manage ads and placements
- site settings

## Media and Image Pipeline

Image uploads are optimized through `optimize_image()` in `admin/includes/db.php`:

- supports JPEG, PNG, GIF source uploads
- resizes to max width (default 1200)
- generates both `.jpg` and `.webp`
- stores base image name in DB
- frontend resolves paths through `getImagePaths()`

Expected output example:

- `uploads/post_12345.jpg`
- `uploads/post_12345.webp`

## Default Admin Signup Secret

`admin/signup.php` contains a hardcoded secret gate:

- `ADMIN_SIGNUP_SECRET = '707875'`

Change this immediately before production use.

## Security Notes

What is already good:

- prepared statements used in many queries
- password hashing via `password_hash()` and verification via `password_verify()`
- output escaping with `htmlspecialchars()` in many templates
- role checks for admin pages

What should be improved before production:

- add CSRF protection for POST forms and AJAX endpoints
- add login rate limiting / brute-force protection
- enforce HTTPS redirection
- move DB credentials to environment-based config
- harden upload validation and ensure execution is blocked in upload directories
- add robust audit logs for admin actions

## Troubleshooting

- Pretty URLs not working:
  - ensure Apache `mod_rewrite` is enabled
  - ensure `AllowOverride All` is enabled for project directory
- Images not showing:
  - verify `featured_image` values and files in `uploads/`
  - ensure placeholder files exist in `assets/images/`
- Like/bookmark not working:
  - user must be logged in
  - check browser console/network for `ajax-handler.php` response
- Load More not working:
  - verify JS loads from `assets/js/script.js`
  - verify `ajax-load-more.php` can access DB and category IDs are valid

## Recommended Next Improvement

Create and commit a versioned SQL schema file, for example:

- `database/schema.sql`

This will make first-time setup and deployment predictable for all environments.

## License

This repository includes `LICENSE.txt` at project root. Review it for licensing terms.
