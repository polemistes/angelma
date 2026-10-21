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
