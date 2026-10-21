# Decisions

Choices made where `PLAN.md` was ambiguous or silent. One line each.

- The site is assumed to live at the web root (as on Domeneshop's `www/`); URLs are root-relative and no base-path detection is done.
- `robots.txt` is served directly by `.htaccess` as well, otherwise it would fall through to `index.php` and give 404.
- Composer `platform.php` is pinned to 8.2.0 so the committed `vendor/` works on any PHP 8.2+ host; PHPUnit 11.5 is used.
- `dev/router.php` serves static files itself (instead of returning false) so the security headers are set on them too, as Apache does.
