# Angelma

Angelma is a small content management system for simple websites. A site is a set of
pages; each page is a stack of rows, and each row holds boxes with text, an image, a blog
or an embedded video or map. It runs on ordinary web hosting with PHP and keeps its data
in an SQLite file, so there is no database server to set up and nothing to run on a
command line.

## Requirements

- PHP 8.2 or newer with the extensions `pdo_sqlite`, `gd` and `mbstring`. Most web hosts
  have these switched on.
- Apache with `mod_rewrite`, or another web server that reads `.htaccess` files, such as
  LiteSpeed. This is what nearly all shared hosting runs.
- The folders `data` and `uploads` must be writable by PHP.

The installer checks the PHP version, the extensions and the two folders, and shows what
is missing. Three more PHP extensions are optional: `zip` for backups and one-click
updates, `curl` for one-click updates, and `dom` for HTML in text boxes.

## Installing

1. **Download** `angelma-<version>.zip` from the
   [latest release](https://github.com/polemistes/angelma/releases/latest).
2. **Extract it into the folder your site is served from.** Hosts call this folder
   `public_html`, `www`, `htdocs` or similar. Upload the zip and extract it with your
   host's file manager, or extract it on your own computer and upload the contents by FTP
   or SFTP. `index.php`, `app/`, `install/` and the other files must end up directly in
   the folder, and the hidden `.htaccess` files must come along. For an address like
   `https://example.com/club/`, put the files in a subfolder called `club` instead.
3. **Open the site in a browser.** The installer starts by itself. It checks the server,
   asks for a site title, page width and colour scheme, then for an administrator
   username and a password of at least 10 characters, and creates the site.
4. **Delete the `install` folder** when the installer says it is done.
5. **Log in** at `/login`, for example `https://example.com/login`.

If something does not work:

- **An error page, or a red line about PHP or an extension:** choose PHP 8.2 or newer,
  and switch on the extension, in your host's control panel.
- **A folder is "not writable":** give the folders `data` and `uploads` write permission
  (usually 755 or 775) in the file manager or your FTP program.
- **The front page works but other pages are "not found":** the `.htaccess` files are
  missing or ignored. Upload them again with hidden files shown, or see below.

### On your own server

With Apache, allow `.htaccess` files for the site folder (`AllowOverride All`), enable
`mod_rewrite`, and `mod_headers` for the security headers, and let the web server user
write to `data/` and `uploads/`.

Servers that do not read `.htaccess` files, such as nginx, need the rules of the three
`.htaccess` files (in the site folder, `data/` and `uploads/`) in their own
configuration: block `app/`, `data/`, `vendor/` and the listed file types, never run
scripts from `uploads/`, and send every request that is not a file under `assets/`,
`uploads/` or `install/` to `index.php`.

## Updating

*Update* in the admin area checks GitHub for a new release and installs it with one
click. The previous version is kept and can be restored from the same screen.

If the server cannot do this by itself, upload the files of the new version over the old
ones, keeping your `data` and `uploads` folders, and open the site: the database updates
itself. Delete the `install` folder again afterwards.

## Your data

Everything you make is in two folders: `data/` holds the database and settings, and
`uploads/` holds the images. Both are protected from the web. Never delete them.

- **Backup:** *Backup* in the admin area downloads a zip with the database and all
  images. To restore, put `data/angelma.sqlite` and the `uploads` folder from the zip
  back in place.
- **Moving the site:** copy the whole site folder, including `data/` and `uploads/`, to
  the new place, and make `data/`, `data/sessions/` and `uploads/` writable there. Leave
  out `data/sessions/*` and `data/error.log`. The new place may be a different host,
  folder or subfolder. A site built on your own computer goes online the same way.
- **Starting over:** the installer only runs while `data/installed.lock` does not exist.
  Delete everything in `data/` except `.htaccess` and the `sessions` folder, and
  everything in `uploads/` except `.htaccess` and `index.html`.

## Using Angelma

Under *Pages* you build pages from rows and boxes, under *Images* you upload pictures,
and under *Blogs* you write posts.

- **Text** is written in Markdown. Text boxes also take HTML where Markdown falls short:
  `<a href="https://example.com/" target="_blank">link text</a>` opens a link in a new
  tab, and tables or `<div style="text-align: center">` work too. Scripts and other parts
  that could harm visitors are removed when the page is shown.
- **Links to your own pages** start with a slash, like `[About us](/about)`. They keep
  working if the site moves into or out of a subfolder.
- **Containers:** a *Container* box holds rows of boxes of its own, so you can stack
  boxes beside a tall one. Containers can be nested two levels deep.
- **Workbench:** a hidden page for boxes that are not on any page. Build and preview
  boxes there, then use *Move…* on any box to put it in a row on any page.
- **Roles:** editors manage pages, images, blogs and comments. Administrators also manage
  users, settings, the menu and backups, and only they may paste raw HTML into an embed
  box.
- **Menu:** administrators order the pages with the arrows in the *Pages* list, and hide
  pages from the menu under *Settings*.
- **Maintenance mode:** a switch under *Settings* shows visitors a notice while you work.
  Logged-in editors and administrators still see the site.

## Development

Requirements: PHP 8.2 or newer with `pdo_sqlite`, `gd`, `mbstring` and `dom`, and
Composer. `vendor/` is committed, so `composer install` is only needed after changing
dependencies.

```
dev/server.sh          # local server on http://localhost:8080 (emulates .htaccess)
                       # ANGELMA_BASE=/site dev/server.sh would serve it from /site/ instead
dev/reset.sh           # delete the local database, config and uploads to test the installer
vendor/bin/phpunit     # run the tests
dev/build.sh           # build dist/angelma-<version>.zip
```

`PLAN.md` is the specification and build order, and `DECISIONS.md` records the choices
made where the plan left room.

## Licence

Angelma is free software: you can redistribute it and/or modify it under the terms of
the GNU General Public License as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version (`GPL-3.0-or-later`).

Angelma is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

The full text of version 3 is in the `LICENSE` file. The bundled library
`league/commonmark` and its dependencies in `vendor/` keep their own licences.
