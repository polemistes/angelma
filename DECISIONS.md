# Decisions

Choices made where `PLAN.md` was ambiguous or silent. One line each.

- The site is assumed to live at the web root (as on Domeneshop's `www/`); URLs are root-relative and no base-path detection is done.
- `robots.txt` is served directly by `.htaccess` as well, otherwise it would fall through to `index.php` and give 404.
- Composer `platform.php` is pinned to 8.2.0 so the committed `vendor/` works on any PHP 8.2+ host; PHPUnit 11.5 is used.
- `dev/router.php` serves static files itself (instead of returning false) so the security headers are set on them too, as Apache does.
- `slugify()` follows the test case in step 1.2 (`Blåbærsyltetøy` → `blabaersyltetoy`): æ → ae, ø → o, å → a. Section 9's "ae oe aa" contradicts that example; the example wins.
- `app/bootstrap.php` sets everything up; `index.php` then dispatches the request. This lets the installer include the bootstrap without dispatching.
- Small extra core classes not in the folder list: `App` (state and request cycle), `Request`, `Response`, `HttpException`, and `app/routes.php` for the route table.
- An unknown path gives 404; a known path with the wrong method (e.g. GET `/logout`) gives 405.
- Sessions start only after installation; the session lifetime is the browser session, server-side garbage collection after 24 hours of inactivity.
- The security headers other than CSP come from `.htaccess` (as section 8 says) and `dev/router.php`, not from PHP, to avoid duplicate headers.
- Referential integrity is handled in the Repos (deletes run in transactions), not with SQL foreign-key constraints, so deletes follow the rules of section 6.12 exactly.
- Table and column names follow section 4 exactly. Note for a later MySQL driver: `rows` and `key` are reserved words there and will need quoting.
- Login attempts get their own repo, `Repos/LoginAttempts.php`, because all SQL must live in a repo. `Repos/Repo.php` is a small shared base class.
- Usernames are stored and compared in lower case, so login is case-insensitive.
- Deleting a user moves their pages, blogs, images and posts to the administrator who deletes them; their comments keep the author name but lose the user link.
- Published posts are shown regardless of `published_at` being in the future (scheduled publishing is a later feature).
- The `Schemes` class and the five scheme files were created in step 1.4 instead of 1.5, because the installer's site step needs the list of built-in schemes.
- The installer logic lives in `app/Installer.php` (testable); `install/index.php` is only the page flow. It has its own `install/install.css` and `install/install.js` (no inline scripts, because of the CSP).
- The installer stores only the password hash in the session between steps, never the plain password.
- If `installed.lock` is missing but the database already contains users, the installer refuses to continue instead of overwriting the site.
- Usernames: 3 to 40 letters, digits, dots, dashes or underscores. Display names: at most 80 characters.
- The page's `title` is used for `<title>` and as a visually hidden `<h1>`; visible headings come from the boxes (the installer's welcome box already has its own heading). This keeps one `<h1>` per page.
- The home page is reachable both as `/` and as `/{its slug}`; the menu links to `/`.
- Row alignment: a row's grid gets only as many 1/12-wide columns as its boxes use, so `justify-content` can place them left, centre or right. Empty rows are not shown publicly.
- On narrow screens fixed heights are dropped for text boxes only (as the plan says); image, blog and embed boxes keep them.
- `--page-width` is written into the same `<style>` block as the scheme colours. `app/Frame.php` gathers the data for the public page frame (settings, menu, logo, footer).
- Scheme colours must be `#rgb` or `#rrggbb`; anything else falls back to the paper colour, so the custom scheme can never inject CSS.
