# Angelma: build plan

Angelma is a small content management system for simple websites. A site is a set of
pages. Each page is a stack of rows, each row holds one or more boxes, and a box shows
text, an image, a blog or an embedded video or map. The site owner uploads one archive to
an ordinary shared web host (Domeneshop is the reference host), opens the site in a
browser, and finishes the setup there. No console, Composer or Node is needed on the host.

This document is the complete specification and the step-by-step build order. It is
written to be followed one step at a time by a coding agent. Read "How to work" first,
then do the phases in order. Do not skip ahead, and do not build things the plan does not
ask for.

---

## 1. How to work

- **One step at a time.** Each step below has a "Done when" list. Finish it, verify it,
  then move on. Do not start a later step to make an earlier one pass.
- **Commit after every step.** Use git in this folder. Commit message:
  `Step 3.2: image upload and resizing`. Commit even when a step is small. The work must
  be recoverable if the session is interrupted.
- **Tick the checkbox in section 12 (Progress)** when a step is done, in the same commit.
  When resuming after an interruption, read section 12 first to see where to continue.
- **Record decisions.** When the plan is ambiguous, choose the simplest option that fits
  the rest of the plan, and append one line to `DECISIONS.md` (create it in step 1.1)
  saying what you chose and why. Do not stop to ask unless the choice would change the
  data model or the URL structure.
- **Stay inside `/home/proteus/koding/angelma`.** Do not read or modify other folders
  under `/home/proteus/koding`.
- **Keep it plain.** No framework, no JavaScript build step, no CSS preprocessor. PHP
  files, one CSS file for the site, one for the admin, small plain JavaScript files.
- **Security is not a later phase.** Every form has a CSRF token, every database call uses
  prepared statements, every piece of output goes through the escape helper. Check this
  in every step that adds a form or a template.
- **Test locally** with the PHP built-in server (section 10). Verify every "Done when"
  item by actually loading the page or running the test, not by reading the code.
- **Language.** All code, comments, identifiers and interface strings are in English.
  Interface strings live in `app/lang/en.php`, never inline in templates.

---

## 2. Fixed decisions

These were decided with the site owner and are not open for change.

| Topic | Decision |
|---|---|
| Runtime | PHP 8.2 or newer. Required extensions: `pdo_sqlite`, `gd`, `mbstring`, `json`, `session`. Optional: `pdo_mysql`, `zip`. |
| Framework | None. Hand-written front controller and router. |
| Database | SQLite through PDO. The SQL is written to be standard so MySQL can be added later. All driver-specific SQL lives in `app/Db.php` only. |
| Templates | Plain PHP files under `app/Views`. |
| Text format | Markdown, rendered server-side with `league/commonmark`. HTML in Markdown is stripped. |
| Composer | Only `league/commonmark` in production, `phpunit/phpunit` in development. `vendor/` is committed to the repository so the archive is complete and the host never runs Composer. |
| JavaScript | Small plain scripts, no libraries, no bundler. Used only in the admin: Markdown preview, image picker, confirmation dialogs. |
| Accounts | Administrators create users and set passwords. No self-registration. No email anywhere in version 1. |
| Languages | English only. Strings in `app/lang/en.php` through the `t()` helper so a second language can be added later. |
| Ordering | Rows, boxes and menu items are ordered with up and down buttons, not drag and drop. |
| Hosting layout | The archive is extracted directly into the web root (Domeneshop's `www/` folder). Protected folders are blocked with `.htaccess`. |

---

## 3. Folder layout

```
angelma/                      repository root = web root after extraction
  index.php                   front controller, the only PHP entry point
  .htaccess                   rewrites and protection rules (section 8)
  robots.txt
  app/                        PHP source, blocked from the web
    bootstrap.php             autoload, config, error handling, session, helpers
    helpers.php               e(), t(), url(), redirect(), slugify(), csrf_field() ...
    Router.php
    Db.php                    PDO wrapper, migration runner
    Auth.php                  login, logout, current user, roles, CSRF, rate limiting
    Markdown.php
    Images.php                upload validation, re-encoding, resizing
    Schemes.php               built-in colour schemes and the custom one
    Controllers/
      SiteController.php      public pages
      BlogController.php      public blog lists, posts, comment submission, RSS
      AuthController.php      login, logout
      Admin/
        DashboardController.php
        PagesController.php
        RowsController.php
        BoxesController.php
        ImagesController.php
        BlogsController.php
        PostsController.php
        CommentsController.php
        UsersController.php
        SettingsController.php
        BackupController.php
        AccountController.php
    Repos/                    one class per table, all SQL lives here
      Settings.php Users.php Pages.php Rows.php Boxes.php Images.php
      Blogs.php Posts.php Comments.php
    Views/
      layout/site.php         public page frame: header, menu, rows, footer
      layout/admin.php        admin frame
      site/                   page.php, blog_index.php, post.php, login.php, 404.php
      admin/                  one file per admin screen
      partials/               box_text.php, box_image.php, box_blog.php, box_embed.php,
                              menu.php, comment_form.php, flash.php, pagination.php
    migrations/
      001_initial.sql
    schemes/                  one JSON file per built-in colour scheme
    lang/en.php
    version.php               returns the version string, e.g. '0.1.0'
  assets/
    site.css                  public styles
    admin.css                 admin styles
    admin.js                  preview, image picker, confirmations
  uploads/                    uploaded images, public, PHP execution disabled
    .htaccess
    index.html                empty file so directory listing shows nothing
  data/                       blocked from the web
    .htaccess
    angelma.sqlite            created by the installer
    config.php                created by the installer
    sessions/                 PHP session files
    installed.lock            created by the installer, its presence disables install/
  install/
    index.php                 the browser installer
  vendor/                     Composer dependencies, committed, blocked from the web
  dev/                        developer tools, not shipped in the archive
    server.sh                 start the built-in PHP server
    router.php                emulates .htaccess for the built-in server
    build.sh                  produce dist/angelma-<version>.zip
    reset.sh                  delete data/ contents and uploads to test a fresh install
  tests/                      PHPUnit tests
  composer.json
  composer.lock
  phpunit.xml
  PLAN.md  DECISIONS.md  README.md  CLAUDE.md  .gitignore
```

`.gitignore` contains `data/*` except `data/.htaccess` and `data/sessions/.gitkeep`,
`uploads/*` except `uploads/.htaccess` and `uploads/index.html`, and `dist/`.

---

## 4. Data model

All tables have an integer primary key `id`. Timestamps are stored as UTC ISO 8601 text
(`2026-10-21T14:03:00Z`). Booleans are integers 0 and 1.

### settings
`key TEXT PRIMARY KEY, value TEXT`. Keys used:

| key | meaning | default |
|---|---|---|
| site_title | shown in the header and in `<title>` | from installer |
| site_tagline | optional line under the title | empty |
| logo_image_id | optional image shown left of the title | empty |
| page_width | max width of the page area in pixels | 960 |
| scheme | id of the active colour scheme, or `custom` | `paper` |
| custom_scheme | JSON object of colour values, same keys as a scheme file | copy of `paper` |
| footer_markdown | footer text | empty |
| home_page_id | id of the home page | set by installer |
| moderate_anonymous | 1 if anonymous comments wait for approval | 1 |
| timezone | PHP timezone name for displaying dates | `Europe/Oslo` |
| date_format | PHP date format for posts and comments | `j F Y` |

### users
`username TEXT UNIQUE, display_name TEXT, password_hash TEXT, role TEXT` (`admin`,
`editor`, `user`), `is_active INTEGER, created_at, last_login_at`.

### pages
`slug TEXT UNIQUE, name TEXT` (menu label), `title TEXT, meta_description TEXT,
menu_order INTEGER, show_in_menu INTEGER, owner_id INTEGER, created_at, updated_at`.
The home page is the one whose id equals the `home_page_id` setting. The home page is
also reachable as `/`. Slugs may not be any of the reserved words in section 5.

### rows
`page_id INTEGER, position INTEGER, alignment TEXT` (`left`, `center`, `right`),
`parent_box_id INTEGER NULL` (added in 1.2.0).
Alignment matters only when the boxes in the row do not fill the full width.
`parent_box_id` is null for the rows of the page itself, or the id of the container box
the row lives in (section 6.13). Nested rows keep `page_id` set, so every query by page
still finds them. `position` counts within the same parent.

### boxes
`row_id INTEGER, position INTEGER, type TEXT` (`text`, `image`, `blog`, `embed`,
`container` since 1.2.0),
`width INTEGER` (columns out of 12: one of 3, 4, 6, 8, 9, 12), `height INTEGER NULL`
(fixed height in pixels, null means height follows content), `content TEXT` (JSON).

Content JSON per type:

| type | fields |
|---|---|
| text | `markdown` |
| image | `image_id`, `alt`, `caption` (may be empty), `fit` (`cover` or `contain`, used only when height is fixed) |
| blog | `blog_id`, `count` (number of newest posts to show, default 5), `show_excerpts` (1 or 0) |
| embed | `url` (a URL from a supported provider, section 6.6), or `html` (raw HTML, only administrators may set this) |
| container | no fields: the box's content is the rows whose `parent_box_id` is the box's id (section 6.13) |

The sum of widths in a row may not exceed 12. The admin validates this.

### images
`filename TEXT` (generated, e.g. `a1b2c3d4e5f6.jpg`), `original_name TEXT, mime TEXT,
width INTEGER, height INTEGER, size INTEGER, alt TEXT, uploader_id INTEGER, created_at`.
Derived files follow a naming convention and are not stored in the database:
`uploads/a1b2c3d4e5f6.jpg` (large, long side at most 2000 px),
`uploads/a1b2c3d4e5f6-m.jpg` (medium, width 1000 px),
`uploads/a1b2c3d4e5f6-s.jpg` (small, width 400 px). PNG uploads keep the `.png`
extension so transparency survives. Everything else is re-encoded to JPEG.

### blogs
`slug TEXT UNIQUE, title TEXT, description TEXT, owner_id INTEGER, created_at`.

### posts
`blog_id INTEGER, slug TEXT, title TEXT, body_markdown TEXT, status TEXT` (`draft`,
`published`), `comments_mode TEXT` (`closed`, `users`, `anyone`), `author_id INTEGER,
published_at TEXT NULL, created_at, updated_at`. `slug` is unique within a blog.

### comments
`post_id INTEGER, user_id INTEGER NULL, author_name TEXT, body TEXT, status TEXT`
(`pending`, `approved`), `ip_hash TEXT, created_at`. `body` is plain text, rendered
with line breaks only, no Markdown. Comments are a flat list in time order.

### login_attempts
`key TEXT, attempted_at TEXT`. `key` is a hash of the IP address or of the username. Rows
older than one hour are deleted on each login attempt.

### schema_version
`version INTEGER`. One row. The migration runner applies every `app/migrations/NNN_*.sql`
with a number higher than the stored version, in order, inside a transaction, then
updates the row.

---

## 5. URLs

Public:

| URL | shows |
|---|---|
| `/` | the home page |
| `/{page-slug}` | a page |
| `/blog/{blog-slug}` | all published posts of a blog, newest first, 10 per page, `?page=2` |
| `/blog/{blog-slug}/feed` | RSS 2.0 feed of the 20 newest published posts |
| `/blog/{blog-slug}/{post-slug}` | one post with its comments and comment form |
| `/blog/{blog-slug}/{post-slug}/comment` | POST target for new comments |
| `/login`, `/logout` | login form (GET and POST), logout (POST only) |
| `/uploads/...`, `/assets/...` | static files served by Apache directly |
| `/install/` | the installer, only until `data/installed.lock` exists |

Admin, all under `/admin`, all require login, role checks per section 7:

| URL | screen |
|---|---|
| `/admin` | dashboard: counts, recent posts, pending comments, quick links |
| `/admin/pages` | list pages; new page form |
| `/admin/pages/{id}` | edit page settings and its layout (rows and boxes) |
| `/admin/pages/{id}/rows` | POST: add row |
| `/admin/rows/{id}/move`, `/admin/rows/{id}/delete`, `/admin/rows/{id}/alignment` | POST |
| `/admin/rows/{id}/boxes` | POST: add box of a given type and width |
| `/admin/boxes/{id}` | edit box (type-specific form) |
| `/admin/boxes/{id}/move`, `/admin/boxes/{id}/delete` | POST |
| `/admin/boxes/{id}/rows` | POST: add row inside a container box (1.2.0) |
| `/admin/preview` | POST Markdown, returns rendered HTML (used by admin.js) |
| `/admin/images` | library with upload form, delete, edit alt text |
| `/admin/images/picker` | small list used inside the image box form |
| `/admin/blogs`, `/admin/blogs/{id}` | list and edit blogs, list posts |
| `/admin/blogs/{id}/posts/new`, `/admin/posts/{id}` | create and edit posts |
| `/admin/comments` | pending comments to approve or delete, recent approved comments |
| `/admin/users`, `/admin/users/{id}` | administrators only |
| `/admin/settings` | administrators only: title, scheme, custom scheme, width, footer, home page, menu order and visibility |
| `/admin/backup` | administrators only: download a zip of the database and uploads |
| `/admin/account` | change own password and display name |

Reserved first path segments that a page slug may not use: `admin`, `blog`, `login`,
`logout`, `install`, `uploads`, `assets`, `data`, `app`, `vendor`, `dev`, `tests`.

Every state-changing action is a POST with a CSRF token. There are no GET actions that
change data.

---

## 6. Behaviour

### 6.1 Public page frame
Header with optional logo, site title (links to home) and tagline. Under it a menu bar
with one item per page that has `show_in_menu` set, in `menu_order`, the current page
marked. At the right end of the menu bar: "Log in" when logged out, or the display name
with a logout button when logged in. Then the rows of the page. Then a footer with the
footer Markdown and a small "Powered by Angelma" line.

`<title>` is `{page title} - {site title}`, or just the site title on the home page.
`<meta name="description">` from the page when set.

### 6.2 Layout rules
- The page area has `max-width: {page_width}px`, centered, with the scheme's page
  background. The body outside it has the scheme's outside background.
- A row is a 12-column CSS grid. A box with width 6 spans 6 columns. When the widths sum
  to less than 12, the row's `alignment` decides where the boxes sit, using
  `justify-content` on the grid.
- Below 700 px viewport width, every box becomes full width and rows stack. Fixed heights
  are kept on narrow screens for image boxes and dropped for text boxes.
- A box with fixed `height` gets that height in pixels and `overflow: hidden` for text and
  blog boxes.
- Boxes have the scheme's box background and border. Padding is consistent for all types.

### 6.3 Text box
Markdown rendered to HTML. Supported: headings (h2 to h4 inside a box, h1 is rendered as
h2 so the page keeps one h1), paragraphs, bold, italic, links, ordered and unordered
lists, blockquotes, horizontal rules, inline code and code blocks, images by URL. Raw HTML
is stripped. Links to other sites get `rel="noopener"`.

### 6.4 Image box
The image fills the box width. Without a fixed height, the height follows the image's
aspect ratio. With a fixed height, the image is `object-fit: cover` or `contain` as set.
The `srcset` offers the small, medium and large files. Caption under the image when set,
styled smaller. `alt` from the box, falling back to the image's own alt text.

### 6.5 Blog box
Shows the `count` newest published posts of the blog: title linking to the post, date,
author display name, and when `show_excerpts` is set, the first paragraph of the body as
plain text. Below the list a link "All posts" to `/blog/{slug}`. If the blog has no
published posts, show "No posts yet".

### 6.6 Embed box
The editor pastes a URL. The renderer recognises YouTube (`youtube.com/watch?v=`,
`youtu.be/`), Vimeo, Google Maps embed URLs and OpenStreetMap export URLs, and emits a
responsive iframe for them with a fixed sandbox and `loading="lazy"`. Unrecognised URLs
are shown as a plain link. Administrators may instead paste raw HTML into `html`, which is
output unchanged. The form only shows the raw HTML field to administrators, and the server
rejects `html` from anyone else.

### 6.7 Blog pages
The blog index lists published posts, newest first, with pagination. The post page shows
title, date, author, rendered body, then comments, then the comment form if allowed:

| comments_mode | logged out | logged in (any role) |
|---|---|---|
| closed | no form, text "Comments are closed" | same |
| users | text "Log in to comment" with link | form |
| anyone | form with a name field | form |

Anonymous comments are `pending` when `moderate_anonymous` is set, otherwise `approved`.
Comments from logged-in users are always `approved`. Only approved comments are shown.
The post author and administrators see pending comments on the post page, marked, with
approve and delete buttons. A logged-in user may delete their own comment.

Spam control for the comment form: a hidden honeypot field that must stay empty, a hidden
timestamp field that must be at least 3 seconds old, at most 5 comments per IP hash per
10 minutes, body at most 4000 characters, at most 2 URLs in an anonymous comment.

### 6.8 Login
Username and password. Five failed attempts for a username or an IP hash within 15 minutes
blocks further attempts for that key for 15 minutes. Session id is regenerated on login.
Session cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` when the request is HTTPS.
Sessions are stored in `data/sessions`. After login, redirect to `/admin` for admins and
editors, to the previous page for users.

### 6.9 Colour schemes
A scheme is a JSON object with these keys, each a CSS colour:
`outside_bg, page_bg, text, heading, link, link_hover, menu_bg, menu_text,
menu_active_bg, menu_active_text, box_bg, box_border, caption, footer_bg, footer_text`.
Built-in schemes in `app/schemes/`: `paper` (light, warm), `slate` (light, cool grey),
`night` (dark), `forest` (greens), `ocean` (blues). The custom scheme is edited in
settings with one colour input per key, starting from a copy of the active scheme. The
active scheme is written into the page as a `<style>:root{--outside-bg:...}</style>`
block and `site.css` uses only the variables.

### 6.10 Installer
`/install/` is served while `data/installed.lock` does not exist. Steps, each a form:

1. Requirements: PHP version at least 8.2, required extensions present, `data/` and
   `uploads/` writable, `mod_rewrite` likely working (test by fetching `/install/rewrite-check`
   with a relative request via JavaScript, report a warning if it fails). Show green or
   red for each. Continue only when all required checks pass.
2. Site: site title, page width (default 960), colour scheme (pick from built-ins).
3. Administrator: username, display name, password (minimum 10 characters), repeated.
4. Finish: create `data/config.php` with a random 32-byte secret and the database path,
   create the database, run migrations, write settings, create the admin user, create a
   page with slug `home`, name `Home`, title `Welcome`, one row with one full-width text
   box containing a short welcome Markdown, set it as home page, write
   `data/installed.lock`. Show a success page linking to `/` and `/admin`, and advise
   deleting the `install/` folder.

If the lock exists, `/install/` shows "Angelma is already installed" and nothing else.

### 6.11 Backup
`/admin/backup` POST produces `angelma-backup-{date}.zip` containing `data/angelma.sqlite`
(copied with the SQLite backup API or after a `BEGIN IMMEDIATE` to get a consistent copy)
and the `uploads/` folder. Uses the `zip` extension when present; if absent, shows a
message telling the administrator to download `data/` and `uploads/` by FTP instead.

### 6.12 Deleting things
- Deleting a page deletes its rows and boxes. The home page cannot be deleted; the
  administrator must first make another page the home page.
- Deleting a row deletes its boxes.
- Deleting an image is refused while any box or the logo uses it; the message lists the
  pages that use it.
- Deleting a blog is refused while it has posts or is used by a box.
- Deleting a post deletes its comments.
- Every delete button opens a browser confirm dialog (admin.js) and is a POST.

### 6.13 Container box (1.2.0)
A container box holds rows of boxes, exactly like a page does, so several boxes can be
stacked beside one tall box. Rows are flat, so without containers that layout is
impossible; with them, any layout that can be made by repeatedly cutting the page into
strips and columns is possible.

- Widths inside a container are shares of the container: an inner row is a 12-column
  grid of the container's width, with the same widths, the same "at most 12" rule and
  the same alignment setting as a page row.
- The container draws nothing: no background, border or padding. A container holding one
  full-width box therefore looks exactly like that box on its own.
- Containers have no fixed height; the boxes inside keep their own.
- Containers can be nested, but at most two deep: a container in a page row may hold
  containers, and those may not. The server refuses deeper nesting with a message.
- Deleting a container deletes its rows and boxes, recursively. Deleting a row or a page
  deletes the containers in it the same way. Image and blog usage checks (6.12) see boxes
  inside containers, because nested rows keep `page_id`.
- Below 700 px, inner rows stack like page rows, in reading order.
- In the layout editor a container shows its own rows inside the box, with the same row
  toolbars and "Add box" forms, and an "Add row" button of its own. The box edit form of
  a container has only the width.

---

## 7. Roles and permissions

| Action | admin | editor | user | anonymous |
|---|---|---|---|---|
| View site | yes | yes | yes | yes |
| Comment on `anyone` posts | yes | yes | yes | yes, name required |
| Comment on `users` posts | yes | yes | yes | no |
| Delete own comment | yes | yes | yes | no |
| Create pages | yes | yes, becomes owner | no | no |
| Edit or delete a page, its rows and boxes | yes, any | own pages only | no | no |
| Set home page, menu order, menu visibility | yes | no | no | no |
| Upload images | yes | yes | no | no |
| Delete images | yes, any unused | own unused only | no | no |
| Edit image alt text | yes, any | own only | no | no |
| Create blogs | yes | yes, becomes owner | no | no |
| Edit or delete a blog | yes, any | own only | no | no |
| Create posts | yes, any blog | own blogs only | no | no |
| Edit or delete posts | yes, any | posts in own blogs | no | no |
| Approve or delete comments | yes, any | on posts in own blogs | no | no |
| Raw HTML in embed boxes | yes | no | no | no |
| Users, settings, backup | yes | no | no | no |
| Change own password and display name | yes | yes | yes | no |

Rules are enforced in the controllers through two helpers: `Auth::requireRole('editor')`
(admin satisfies every role requirement) and `Auth::requireOwner($ownerId)` (admin always
passes). A user with role `user` who opens `/admin` is sent to `/admin/account`, the
only admin screen they may use. The last active administrator cannot be deactivated,
deleted or demoted.

---

## 8. Web server rules

`.htaccess` at the root:

- `RewriteEngine On`; requests for existing files and directories under `assets/`,
  `uploads/`, `install/` are served directly; everything else goes to `index.php`.
- Block `app/`, `data/`, `vendor/`, `dev/`, `tests/` and any file ending in `.sqlite`,
  `.lock`, `.md`, `.json`, `.sh`, `.xml`, `.dist`, `composer.*` with a 403.
- Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`.

`uploads/.htaccess`: deny every file matching `\.(php|phtml|phar|pl|py|cgi|sh)$`, and set
`Header set Content-Disposition inline` for images. Also set a long `Cache-Control` for
images.

`data/.htaccess` and `app/.htaccess`: `Require all denied` (with an `IfModule` fallback
to `Deny from all` for Apache 2.2).

PHP sets the Content-Security-Policy header from `bootstrap.php`:
`default-src 'self'; img-src 'self' data: https:; frame-src https://www.youtube.com
https://player.vimeo.com https://www.google.com https://www.openstreetmap.org;
style-src 'self' 'unsafe-inline'; script-src 'self'`. The scheme `<style>` block is why
`unsafe-inline` is needed for styles; scripts never are inline.

`dev/router.php` reproduces the rewrite and blocking rules for the built-in server so
local behaviour matches Apache.

---

## 9. Code conventions

- `declare(strict_types=1);` in every PHP file. Namespaces `Angelma\...` matching the
  folder, autoloaded with Composer's PSR-4 from `app/`.
- Controllers are classes with one public method per route, receiving the `Request`
  object (a tiny class wrapping method, path, query, post, files, and the current user)
  and returning a `Response` (status, headers, body) or using `redirect()`.
- Repos return associative arrays, not objects. Repos contain all SQL. Controllers
  contain no SQL.
- Views are rendered with `view('admin/pages_edit', ['page' => $page])`, which extracts
  variables and includes the file inside the chosen layout. Every variable output in a
  view goes through `e()`. The only exception is rendered Markdown and the admin's raw
  HTML embed, which are output with a clearly named `raw()` helper.
- Flash messages: `flash('success', t('page_saved'))` stored in session, displayed once
  by `partials/flash.php`.
- Forms: `csrf_field()` in every form; `Auth::verifyCsrf()` in `Router` for every POST
  before the controller runs. Invalid token gives a 403 page.
- Errors: in development (`config['debug'] = true`) show the exception; in production log
  to `data/error.log` and show a plain 500 page. Never show paths or SQL to visitors.
- Slugs: `slugify()` lowercases, transliterates common Latin letters including
  æ ø å to ae oe aa, replaces runs of non-alphanumerics with `-`, trims, and appends `-2`,
  `-3` on collision.
- Dates: stored UTC, displayed in the configured timezone with the configured format.
- Tests: PHPUnit in `tests/`, run with `vendor/bin/phpunit`. Unit tests for helpers,
  Markdown, Schemes, Images (with a tiny generated image), Auth rate limiting and role
  helpers, and the Router. Integration tests use an in-memory SQLite database through
  `Db` and the Repos.

---

## 10. Local development

```
dev/server.sh          # php -S localhost:8080 dev/router.php, from the repo root
dev/reset.sh           # removes data/angelma.sqlite, data/config.php, data/installed.lock,
                       # data/sessions/*, uploads/* (keeps .htaccess and index.html)
dev/build.sh           # composer install --no-dev, then zips everything except
                       # dev/ tests/ data/* uploads/* .git .gitignore phpunit.xml
                       # into dist/angelma-<version>.zip, then composer install (with dev)
vendor/bin/phpunit     # tests
```

The local machine has PHP 8.5, Composer, GD and SQLite. Check the installed version with
`php -v` before starting; if it is below 8.2, stop and report.

---

## 11. Build steps

Each step: what to build, then "Done when" checks. Commit after each.

### Phase 1: Skeleton and installer

**1.1 Repository and tooling**
Create `.gitignore`, `README.md` (two paragraphs: what Angelma is, how to install),
`DECISIONS.md` (empty heading), `composer.json` with PSR-4 autoload `Angelma\` to `app/`,
`league/commonmark` and dev `phpunit/phpunit`. Run `composer install`. Create
`phpunit.xml`, `dev/server.sh`, `dev/router.php`, `dev/reset.sh`, `dev/build.sh`,
`app/version.php` returning `0.1.0`. Create empty `uploads/`, `data/sessions/` with
`.gitkeep`, and the `.htaccess` files from section 8. `git init` and first commit.
Done when: `composer install` succeeds, `vendor/bin/phpunit` runs with zero tests,
`dev/server.sh` starts and `curl localhost:8080/app/bootstrap.php` returns 403.

**1.2 Bootstrap, helpers, router**
`index.php` requires `app/bootstrap.php`, which loads Composer autoload, `data/config.php`
if present, sets error handling, timezone, session settings, security headers, and
dispatches through `Router`. The `Router` matches method and path patterns like
`/admin/pages/{id}` to controller methods, verifies CSRF on POST, and returns 404 for
unmatched routes. Implement `helpers.php` (`e`, `raw`, `t`, `url`, `redirect`, `view`,
`flash`, `slugify`, `csrf_field`, `now`, `format_date`). Implement `app/lang/en.php`
with the strings needed so far. If `data/installed.lock` is missing, every request except
`/install/*` and `/assets/*` redirects to `/install/`.
Done when: unit tests for `slugify` (including `Blåbærsyltetøy` to `blabaersyltetoy`),
`e`, and router matching pass; `curl localhost:8080/` redirects to `/install/`.

**1.3 Database and migrations**
`Db` wraps PDO with `query`, `one`, `all`, `insert` (returns id), `execute`, transactions,
and `migrate()` as described in section 4. Write `001_initial.sql` with every table in
section 4 plus indexes on foreign keys and slugs. Write all Repos with the methods the
later steps will need: find by id, find by slug, list, create, update, delete, plus
specific queries (`Pages::menu()`, `Posts::publishedForBlog()`, `Comments::pendingFor()`
and so on). Add them as you go in later steps if something is missing, but keep SQL here.
Done when: integration tests create an in-memory database, run migrations, insert a page
with a row and a box, and read them back; running `migrate()` twice is harmless.

**1.4 Installer**
Build `install/index.php` as a small self-contained flow using the app's classes
(section 6.10). Use a session to carry data between steps. Validate everything server-side.
Done when: `dev/reset.sh`, then completing the installer in a browser produces a
working `data/config.php`, database, lock file, one admin user and a home page; visiting
`/install/` afterwards shows the already-installed message; `/` no longer redirects.

**1.5 Public page rendering, schemes, menu**
`SiteController::page()` resolves `/` and `/{slug}`, loads rows and boxes, renders
`layout/site.php` with the menu, scheme style block and footer. Implement `Schemes`
with the five JSON files and the custom scheme. Implement `partials/box_text.php` with
`Markdown`. Write `assets/site.css` implementing section 6.2 with CSS variables only.
Implement the 404 page.
Done when: the installed home page renders with the welcome text, the menu shows "Home"
marked active, unknown slugs give 404, switching the `scheme` setting directly in the
database changes the colours, and the page stacks correctly at 600 px width.

### Phase 2: Authentication and admin shell

**2.1 Login and sessions**
`Auth` and `AuthController` per section 6.8 including rate limiting with
`login_attempts`. Login form view. Login and logout links in the menu bar.
Done when: wrong password five times blocks the sixth attempt with a clear message; a
correct login redirects an admin to `/admin`; logout is POST only; unit tests cover the
rate limiter and role helpers.

**2.2 Admin layout and dashboard**
`layout/admin.php` with a left navigation (Pages, Images, Blogs, Comments, Users,
Settings, Backup, Account, View site, Log out), showing only what the role allows.
`assets/admin.css`. `DashboardController` with counts and pending comments. `/admin`
requires at least editor, users are sent to `/admin/account`.
Done when: admin and editor see the right navigation items; a `user` sees only Account;
an anonymous visitor is redirected to `/login` with a return URL.

**2.3 Account**
Change display name and password (current password required).
Done when: an editor can change their password and log in with the new one.

### Phase 3: Pages, rows, boxes, images

**3.1 Pages list and settings**
List pages (admins see all, editors see their own) with edit and delete. New page form:
name, title, slug (auto-filled from name by admin.js, editable), meta description.
Edit form for the same fields. Slug validation against reserved words and uniqueness.
Delete rules from 6.12.
Done when: an editor creates a page, it appears in the menu for everyone (new pages
default to `show_in_menu` = 1, appended last), the editor cannot open another editor's
page (403), the admin can.

**3.2 Layout editor: rows and boxes**
On `/admin/pages/{id}`, under the settings form, show the rows as a vertical list. Each
row shows its alignment selector, up, down and delete buttons, and its boxes side by side
with their type, width, a short content preview, and up, down, edit, delete buttons.
An "Add row" button. In each row an "Add box" form with type and width selects; the
server refuses a width that would exceed 12. Moving uses POST to `/move` with
`direction=up|down` and renumbers positions.
Done when: an editor can build a page with three rows, one full width, one with two half
boxes, one with a single 4-wide box aligned right, and the public page renders exactly
that; widths over 12 are refused with a message.

**3.3 Box editing: text and embed**
`/admin/boxes/{id}` shows a form by type. Common fields: width, fixed height (empty for
automatic). Text: a Markdown textarea with a live preview panel (admin.js posts to
`/admin/preview` with a 500 ms debounce). Embed: URL field, and for admins the raw HTML
field. Implement the embed renderer (6.6) and `partials/box_embed.php`.
Done when: Markdown preview matches the public rendering; a YouTube URL renders as a
responsive iframe; an editor posting `html` gets 403; `h1` in Markdown renders as `h2`;
a `<script>` tag in Markdown is stripped.

**3.4 Image library and upload**
`Images` class: accept JPEG, PNG, GIF and WebP up to the smaller of 20 MB and PHP's
`upload_max_filesize`; verify with `getimagesize` and `finfo`; re-encode with GD into
the three sizes of section 4; EXIF orientation respected for JPEG; strip everything else.
Library screen with upload form, grid of thumbnails, alt text edit, delete with the usage
check of 6.12. Admins see all images, editors their own.
Done when: uploading a 4000 px wide photo produces three files with the expected widths,
a PHP file renamed to `.jpg` is rejected, deleting an image in use is refused and names
the page, unit tests cover resizing with a generated image.

**3.5 Image box**
Image box form: image picker (a modal or inline list from `/admin/images/picker` with
search by original name, plus an upload field right there), alt, caption, fit.
`partials/box_image.php` per 6.4 with `srcset`.
Done when: an image box without fixed height shows the full image at box width; with a
fixed height of 300 px it crops with `cover` or letterboxes with `contain`; caption appears
only when set.

### Phase 4: Blog

**4.1 Blogs**
Admin list and create and edit of blogs (title, slug, description). Public
`/blog/{slug}` index with pagination and `/blog/{slug}/feed` RSS.
Done when: a blog with 12 published posts shows 10 then 2 across two pages; the feed
validates as RSS 2.0 (correct dates, absolute links, escaped content).

**4.2 Posts**
Create and edit posts: title, slug (auto from title), Markdown body with preview, status,
comments mode, published date (defaults to now when first published). Drafts are visible
only in admin. Public post page per 6.7 without comments yet.
Done when: a draft is not reachable publicly (404), publishing it makes it appear in the
blog index, the blog box on a page, and the feed; `published_at` is set once and kept on
later edits unless changed.

**4.3 Blog box**
Blog box form (blog, count, show excerpts) and `partials/box_blog.php` per 6.5.
Done when: a half-width blog box lists the newest posts with dates and "All posts" link,
and a blog with no posts shows "No posts yet".

### Phase 5: Comments and users

**5.1 Comment submission**
Comment form and POST handler per 6.7 including all spam controls, status rules and
moderation setting. Approved comments listed under the post with author, date, body.
Done when: anonymous comment on an `anyone` post lands as pending and is invisible to
visitors but visible and marked for the author; a logged-in user's comment appears at
once; a `users` post shows the login link when logged out; the honeypot and the 3 second
rule reject bots; the sixth comment from the same IP within 10 minutes is refused.

**5.2 Moderation**
`/admin/comments` with pending list (approve, delete) and recent approved (delete). Approve
and delete buttons on the post page for the author and admins. Users may delete their own
comments from the post page.
Done when: an editor sees only comments on posts in their own blogs; the admin sees all;
each action is POST with CSRF.

**5.3 Users**
Admin screens: list, create (username, display name, role, password), edit (display name,
role, active flag, set new password), delete. Protect the last active admin.
Done when: an admin creates an editor and a user, each can log in and sees the right
screens; demoting the only admin is refused.

### Phase 6: Settings, backup, packaging

**6.1 Settings**
Site title, tagline, logo (image picker), page width (600 to 1600), scheme selector with
a preview swatch per scheme, custom scheme editor (one `<input type="color">` per key,
"Start from current scheme" button), footer Markdown, home page selector, timezone,
date format, moderate anonymous comments. Menu: table of pages with up and down buttons
and a show-in-menu checkbox.
Done when: every setting takes effect on the public site after save; the custom scheme
shows live in a preview area on the settings page (admin.js updates CSS variables on the
preview box only).

**6.2 Backup**
Per 6.11.
Done when: the downloaded zip contains the database and all uploads and the database copy
opens with `sqlite3`.

**6.3 Build and install test**
Finish `dev/build.sh`. Build the zip, extract it into a fresh empty folder, start the
built-in server there, run the installer, create a page with each box type, a blog, a post
and a comment. Fix anything that only shows up in the packaged copy (missing files,
wrong paths). Write the install instructions in `README.md` for Domeneshop specifically:
upload and extract the zip into `www/`, open the site, follow the installer, delete
`install/`, make sure PHP 8.2 or newer is selected in the control panel.
Done when: the packaged copy installs and works without touching the console; the zip
contains no `dev/`, `tests/`, `.git` or data files.

**6.4 Review pass**
Go through the checklist in section 13 and fix what fails. Run all tests. Update
`app/version.php` to `1.0.0`. Tag the commit `v1.0.0`.

### Phase 7: Container boxes (1.2.0, requested by the owner after 1.1.1)

**7.1 Container box: data model, repos and public rendering**
Migration `002_container_rows.sql` adds `parent_box_id` to `rows` with an index. `Rows`
gets a parent box for `create`, moves within the same parent, deletes recursively, and
`forPageByParent()` returns the rows of a page grouped by parent box id (0 for the page's
own rows). `Rows::depth()` tells how deep a row is. `Boxes::TYPES` gains `container` and
`Boxes::delete()` removes the rows inside. `site/page.php` renders rows through a
recursive `partials/rows.php`; `partials/box_container.php` renders the rows inside a
box; `site.css` makes the container box invisible (section 6.13).
Done when: integration tests cover rows inside a container (positions, moves, grouping,
depth), recursive delete through box, row and page, image usage inside a container, and
the rendered HTML of a page with a 6-wide image box beside a 6-wide container holding two
full-width text boxes; `migrate()` reports version 2 and running it twice is harmless.

**7.2 Container box: layout editor**
`POST /admin/boxes/{id}/rows` adds a row inside a container. The layout editor renders
rows through a recursive `admin/layout_rows.php`: a container box shows its rows, their
toolbars and an "Add row" button. The "Add box" type list leaves out `container` where
it would nest too deep, and the server checks again. The box edit form shows only the
width for a container, and the server ignores a height for it.
Done when: an editor builds the layout from 7.1 in the browser; the public page shows two
stacked texts beside the image and stacks in reading order at 600 px; adding a container
three deep is refused with a message; all tests pass; `app/version.php` is `1.2.0`,
tagged `v1.2.0`.

### Later, not in version 1
MySQL driver option in the installer. Norwegian translation. Password reset by email with
SMTP settings. A gallery box. Scheduled publishing. These are listed so they are not built
early by accident.

---

## 12. Progress

Tick when done and committed.

- [x] 1.1 Repository and tooling
- [x] 1.2 Bootstrap, helpers, router
- [x] 1.3 Database and migrations
- [x] 1.4 Installer
- [x] 1.5 Public page rendering, schemes, menu
- [x] 2.1 Login and sessions
- [x] 2.2 Admin layout and dashboard
- [x] 2.3 Account
- [x] 3.1 Pages list and settings
- [x] 3.2 Layout editor: rows and boxes
- [x] 3.3 Box editing: text and embed
- [x] 3.4 Image library and upload
- [x] 3.5 Image box
- [x] 4.1 Blogs
- [x] 4.2 Posts
- [x] 4.3 Blog box
- [x] 5.1 Comment submission
- [x] 5.2 Moderation
- [x] 5.3 Users
- [x] 6.1 Settings
- [x] 6.2 Backup
- [x] 6.3 Build and install test
- [x] 6.4 Review pass
- [x] 7.1 Container box: data model, repos and public rendering
- [ ] 7.2 Container box: layout editor

---

## 13. Final checklist

Security
- [x] Every POST route verifies the CSRF token; a request without one gets 403.
- [x] No SQL is built by string concatenation with user input.
- [x] Every template variable goes through `e()`; `raw()` is used only for rendered
      Markdown and the admin raw HTML embed.
- [x] Uploaded files are re-encoded; a file with PHP content and an image extension is
      rejected; `uploads/.htaccess` blocks script execution.
- [x] `data/`, `app/`, `vendor/` return 403 through the web server.
- [x] Session cookie flags are set; session id regenerates on login.
- [x] Login rate limiting works for both username and IP.
- [x] Passwords are hashed with `password_hash` default algorithm.
- [x] Error pages in production show no paths, SQL or stack traces.
- [x] Security headers and CSP are present on every response.

Permissions
- [x] Every row of the table in section 7 has been tried with each role.
- [x] Editors get 403 on another editor's page, blog, post, image and comments.
- [x] A `user` role cannot reach any admin screen except Account.

Behaviour
- [x] Home page cannot be deleted; last admin cannot be removed.
- [x] Images in use cannot be deleted; blogs with posts cannot be deleted.
- [x] Row widths never exceed 12; alignment works for partial rows.
- [x] Site renders correctly at 360 px, 700 px and 1400 px widths.
- [x] RSS feed is valid; post dates display in the configured timezone.
- [x] Installer refuses to run twice; packaged zip installs cleanly.

Quality
- [x] `vendor/bin/phpunit` passes.
- [x] No interface string is hard-coded outside `app/lang/en.php`.
- [x] `README.md` explains installation on Domeneshop in plain language.
- [x] `DECISIONS.md` lists every choice made outside this plan.
