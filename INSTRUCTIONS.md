# Express News — Deployment Guide for InfinityFree

This guide covers every step required to take this project from your local XAMPP
environment and make it live on InfinityFree free hosting.

---

## Table of Contents

1. [InfinityFree Limitations You Must Know](#1-infinityfree-limitations-you-must-know)
2. [Step 1 — Create Your InfinityFree Account & Domain](#2-step-1--create-your-infinityfree-account--domain)
3. [Step 2 — Find & Replace All Hardcoded Paths](#3-step-2--find--replace-all-hardcoded-paths)
4. [Step 3 — Update the Database Config](#4-step-3--update-the-database-config)
5. [Step 4 — Update the .htaccess File](#5-step-4--update-the-htaccess-file)
6. [Step 5 — Create & Import the Database](#6-step-5--create--import-the-database)
7. [Step 6 — Upload Files via FTP](#7-step-6--upload-files-via-ftp)
8. [Step 7 — Set Folder Permissions](#8-step-7--set-folder-permissions)
9. [Step 8 — Create the First Super Admin](#9-step-8--create-the-first-super-admin)
10. [Step 9 — Test Everything](#10-step-9--test-everything)
11. [Known InfinityFree Quirks & Fixes](#11-known-infinityfree-quirks--fixes)
12. [Files to NEVER Upload](#12-files-to-never-upload)
13. [Quick Reference Checklist](#13-quick-reference-checklist)

---

## 1. InfinityFree Limitations You Must Know

Before you start, understand what InfinityFree does and does not support.

| Feature | InfinityFree Status |
|---|---|
| PHP version | 8.x (supported) |
| MySQL / MariaDB | ✅ Supported |
| GD image extension | ✅ Enabled (WebP works) |
| `exec()` / `shell_exec()` | ❌ Disabled — FFmpeg video thumbnails will not work |
| `chmod()` | ❌ Disabled — set permissions via FTP client |
| Max file size per file | **10 MB** — files larger than 10 MB are auto-deleted |
| Max PHP upload size | ~10 MB |
| Max execution time | 60 seconds (fixed, cannot be changed) |
| Free disk space | 5 GB |
| `.htaccess` | ✅ Supported |
| SSL (HTTPS) | ✅ Free via their panel |
| Cron jobs | ❌ Not available on free plan |
| SSH access | ❌ Not available |

**Important:** Because `exec()` is disabled, video thumbnail generation via FFmpeg
will silently skip — videos will still upload and play, they just won't have a
poster image. Everything else works normally.

---

## 2. Step 1 — Create Your InfinityFree Account & Domain

1. Go to [infinityfree.com](https://infinityfree.com) and sign up for a free account.
2. Create a new hosting account. You will get either:
   - A **free subdomain** like `yoursite.infinityfreeapp.com`
   - Or you can add your own **custom domain** (e.g. `expressnews.com`)
3. Note down your:
   - **Domain name** (e.g. `yoursite.infinityfreeapp.com`)
   - **FTP hostname** (shown in your control panel)
   - **FTP username**
   - **FTP password**
   - **MySQL hostname** (shown in control panel, e.g. `sql123.infinityfree.com`)
   - **MySQL database name** (auto-generated, e.g. `if0_12345678_express_news`)
   - **MySQL username** (same as database name usually)
   - **MySQL password**

---

## 3. Step 2 — Find & Replace All Hardcoded Paths

This is the most important step. The entire codebase uses `/express-news/` as the
base URL path because it was developed inside `htdocs/express-news/` on XAMPP.

On InfinityFree, your files go directly into the `htdocs/` root, so the site lives
at `https://yoursite.infinityfreeapp.com/` — **not** at `/express-news/`.

### What to replace

Open each file listed below and replace every occurrence of:

```
/express-news/
```

with:

```
/
```

Or if you are using a custom domain in a subfolder (e.g. `yoursite.com/news/`),
replace with `/news/` instead.

### Files that contain `/express-news/` and must be updated

**PHP files:**

| File | What to change |
|---|---|
| `admin/includes/db.php` | All `getImagePaths()` default paths and URL prefixes |
| `includes/header.php` | All `href`, `src`, `action` URLs |
| `includes/footer.php` | All `href`, `src` URLs |
| `includes/ads.php` | Any hardcoded URLs |
| `index.php` | All `href` links to categories, news, etc. |
| `news.php` | All internal links and share URLs |
| `category.php` | All `href` links |
| `search.php` | Form action and result links |
| `tag.php` | All `href` links |
| `article-redirect.php` | Redirect URLs |
| `ajax-load-more.php` | AJAX endpoint URL and card links |
| `ajax-handler.php` | AJAX endpoint URL |
| `admin/ajax-media-handler.php` | Media URL prefixes |
| `admin/media-upload-handler.php` | Media URL prefixes |
| `user/login.php` | Redirect URLs |
| `user/register.php` | Redirect URLs |
| `user/edit-profile.php` | Redirect URLs |
| `user/change-password.php` | Redirect URLs |
| `user/profile.php` | All internal links |
| `admin/editor-upload-handler.php` | Upload response URLs |
| `sitemap.php` | All sitemap URLs |

**JavaScript files:**

| File | What to change |
|---|---|
| `assets/js/script.js` | AJAX fetch URLs (`/express-news/ajax-handler.php`, etc.) |
| `admin/assets/js/script.js` | Media manager AJAX URLs |

### The fastest way to do this (find & replace in VS Code)

1. Open the project folder in VS Code
2. Press `Ctrl + Shift + H` (Find & Replace across all files)
3. Search for: `/express-news/`
4. Replace with: `/`
5. Click **Replace All**
6. Review the changes — make sure no file paths (like `__DIR__`) were affected
   (they won't be, since those don't contain `/express-news/`)

---

## 4. Step 3 — Update the Database Config

Open `admin/includes/db.php` and update these four lines at the top:

```php
define('DB_SERVER',   'sql123.infinityfree.com');  // your MySQL hostname from panel
define('DB_USERNAME', 'if0_12345678');              // your MySQL username
define('DB_PASSWORD', 'your_db_password_here');    // your MySQL password
define('DB_NAME',     'if0_12345678_express_news'); // your database name
```

Replace the values with the actual credentials from your InfinityFree control panel
under **MySQL Databases**.

---

## 5. Step 4 — Update the .htaccess File

Open `.htaccess` in the project root and change the `RewriteBase` line.

**Current (XAMPP local):**
```apache
RewriteBase /express-news/
```

**Change to (InfinityFree root domain):**
```apache
RewriteBase /
```

If your site is in a subfolder (e.g. `yoursite.com/news/`), use:
```apache
RewriteBase /news/
```

The full updated `.htaccess` for a root domain deployment:

```apache
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^article/(\d+)/?$           article-redirect.php?id=$1  [L,QSA]
RewriteRule ^news/([a-zA-Z0-9-]+)/?$    news.php?slug=$1            [L,QSA]
RewriteRule ^category/([a-zA-Z0-9-]+)/? category.php?slug=$1        [L,QSA]
RewriteRule ^tag/([a-zA-Z0-9-]+)/?$     tag.php?slug=$1             [L,QSA]
RewriteRule ^sitemap\.xml$              sitemap.php                  [L]
```

---

## 6. Step 5 — Create & Import the Database

### Create the database

1. Log in to your InfinityFree control panel
2. Go to **MySQL Databases**
3. A database is usually already created for you — note its name, username, and password
4. Click **phpMyAdmin** to open the database manager

### Import the schema

1. In phpMyAdmin, select your database from the left sidebar
2. Click the **Import** tab at the top
3. Click **Choose File** and select `database/schema.sql`
4. Click **Go** — this creates all the tables

### Run the media upgrade migration

After importing `schema.sql`, you also need to run the media upgrade:

1. Still in phpMyAdmin, click the **SQL** tab
2. Open `database/media_upgrade.sql` from your local machine
3. Copy its entire contents and paste into the SQL box
4. Click **Go**

> **Note:** `media_upgrade.sql` drops and recreates the `media` table with the
> upgraded schema. If you have existing media records you want to keep, skip this
> step and the media library will still work with the old schema.

---

## 7. Step 6 — Upload Files via FTP

Use an FTP client like **FileZilla** (free, [filezilla-project.org](https://filezilla-project.org)).

### FTP connection settings

| Field | Value |
|---|---|
| Host | Your FTP hostname from InfinityFree panel |
| Username | Your FTP username |
| Password | Your FTP password |
| Port | 21 |

### Where to upload

Connect via FTP. You will see a folder called `htdocs`. Upload **all project files
directly inside `htdocs/`** — not inside a subfolder.

```
htdocs/
├── .htaccess
├── index.php
├── news.php
├── category.php
├── search.php
├── tag.php
├── sitemap.php
├── ajax-handler.php
├── ajax-load-more.php
├── article-redirect.php
├── admin/
├── assets/
├── includes/
├── uploads/
├── user/
└── database/
```

### Files to upload via FTP (not phpMyAdmin)

Upload everything **except** the files listed in section 12 below.

### InfinityFree FTP tips

- InfinityFree FTP connections time out quickly — if a large upload stalls,
  reconnect and resume
- Upload in batches if needed (e.g. `assets/` first, then `admin/`, then root files)
- The `uploads/` folder must exist before the site can save media — create it
  manually in FTP if it does not appear after upload

---

## 8. Step 7 — Set Folder Permissions

InfinityFree disables `chmod()` from PHP, so you must set permissions via your
FTP client.

In FileZilla: right-click a folder → **File Permissions**

| Folder | Permission | Numeric |
|---|---|---|
| `uploads/` | Read + Write + Execute for all | `755` |
| `uploads/avatars/` | Read + Write + Execute for all | `755` |
| `assets/images/` | Read + Write + Execute for all | `755` |

> The `uploads/` folder is where all media files are saved. If it is not writable,
> image uploads will fail silently.

---

## 9. Step 8 — Create the First Super Admin

The admin panel is at `https://yoursite.com/admin/`

To create the first admin account:

1. Go to `https://yoursite.com/admin/signup.php`
2. Fill in username, full name, password
3. For **Role**, select **Super Admin**
4. For **Secret Code**, enter: `707875`
   (You can change this in `admin/signup.php` — find `define('ADMIN_SIGNUP_SECRET', '707875')`)
5. Submit — you will be redirected to the login page

> **Security tip:** After creating your first admin account, consider changing the
> secret code in `admin/signup.php` to something only you know, or restrict access
> to `signup.php` entirely via `.htaccess`.

---

## 10. Step 9 — Test Everything

Go through this checklist after deployment:

- [ ] Homepage loads at `https://yoursite.com/`
- [ ] A news article opens at `https://yoursite.com/news/your-slug`
- [ ] Category page works at `https://yoursite.com/category/technology`
- [ ] Search works at `https://yoursite.com/search.php?query=test`
- [ ] Admin login works at `https://yoursite.com/admin/`
- [ ] Admin dashboard loads with correct stats
- [ ] Upload an image in Media Library — it should generate WebP variants
- [ ] Add a new article with a featured image
- [ ] Article appears on homepage
- [ ] User registration works at `https://yoursite.com/user/register.php`
- [ ] User login and profile work
- [ ] Like and bookmark buttons work on articles
- [ ] Theme customization saves and applies to the frontend

---

## 11. Known InfinityFree Quirks & Fixes

### Anti-bot JavaScript challenge
InfinityFree injects a JavaScript challenge on first page load to block bots.
This is normal — real visitors pass it automatically. It does not affect your site.

### `exec()` and `shell_exec()` are disabled
The video upload handler tries to use FFmpeg for poster frames. This will silently
fail on InfinityFree — videos will still upload and play correctly, they just won't
have a thumbnail preview image. No error will be shown to the user.

### 10 MB file size limit
InfinityFree automatically deletes any file larger than 10 MB. This affects:
- Video uploads (keep videos under 10 MB, or host them on YouTube/Vimeo and embed)
- Large PHP files (unlikely to be an issue)

The upload handler already enforces a 100 MB limit in code — you should lower this
to match InfinityFree's actual limit. Open `admin/media-upload-handler.php` and
change:

```php
$max_bytes = 100 * 1024 * 1024;  // current: 100 MB
```

to:

```php
$max_bytes = 8 * 1024 * 1024;    // safe limit for InfinityFree: 8 MB
```

### PHP file size limit (1 MB per PHP file)
InfinityFree limits individual PHP files to 1 MB. All PHP files in this project
are well under that limit.

### SSL / HTTPS
InfinityFree provides free SSL. Enable it in your control panel under **SSL**.
Once enabled, add this to the top of your `.htaccess` to force HTTPS:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Session-based admin login
The admin panel uses PHP sessions. InfinityFree supports sessions normally.
No changes needed.

### Database connection error on first load
If you see `FATAL ERROR: Could not connect to the database`, double-check:
1. The MySQL hostname in `admin/includes/db.php` matches exactly what InfinityFree shows
2. The database name, username, and password are correct
3. The database was created in the InfinityFree control panel (not just in phpMyAdmin)

---

## 12. Files to NEVER Upload

These files are for local development only and should not be on the live server:

| File / Folder | Reason |
|---|---|
| `INSTRUCTIONS.md` | This file — not needed on server |
| `admin/upload_errors.log` | Local error log |
| `database/schema.sql` | Already imported — leaving it accessible is a security risk |
| `database/media_upgrade.sql` | Already imported — same reason |
| `.git/` folder | Version control data — never upload |
| `node_modules/` | Not applicable here but never upload if present |

To block direct access to the `database/` folder, add this to your `.htaccess`:

```apache
# Block direct access to database SQL files
<FilesMatch "\.(sql)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

---

## 13. Quick Reference Checklist

```
BEFORE UPLOADING
─────────────────────────────────────────────────────
[ ] Replace all /express-news/ with / in all PHP and JS files
[ ] Update DB credentials in admin/includes/db.php
[ ] Update RewriteBase in .htaccess from /express-news/ to /
[ ] Lower upload limit in media-upload-handler.php to 8 MB
[ ] Change admin signup secret code in admin/signup.php

DATABASE SETUP
─────────────────────────────────────────────────────
[ ] Create database in InfinityFree control panel
[ ] Import database/schema.sql via phpMyAdmin
[ ] Run database/media_upgrade.sql via phpMyAdmin SQL tab

FILE UPLOAD (via FTP / FileZilla)
─────────────────────────────────────────────────────
[ ] Connect to FTP using InfinityFree credentials
[ ] Upload all files into htdocs/ (not a subfolder)
[ ] Verify uploads/ folder exists and is writable (chmod 755)
[ ] Verify assets/images/ folder is writable (chmod 755)

AFTER UPLOADING
─────────────────────────────────────────────────────
[ ] Visit https://yoursite.com/ — homepage loads
[ ] Visit https://yoursite.com/admin/signup.php — create super admin
[ ] Log in to admin panel
[ ] Enable SSL in InfinityFree control panel
[ ] Add HTTPS redirect to .htaccess
[ ] Test image upload in Media Library
[ ] Test article creation with featured image
[ ] Delete or block access to database/ folder
```

---

*Last updated for Express News deployed on InfinityFree free hosting.*
