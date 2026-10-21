# Angelma

Angelma is a small content management system for simple websites. A site is a set of
pages; each page is a stack of rows, and each row holds boxes with text, an image, a blog
or an embedded video or map. It runs on ordinary shared web hosting with PHP and SQLite,
and needs no console, Composer or Node on the server.

To install, upload the release archive to your web host, extract it into the web root
(or into a subfolder of it), open the site in a browser and follow the installer. The
steps for Domeneshop are below; other hosts with Apache and PHP 8.2 or newer work the
same way.

## Installing on Domeneshop

You need the release file `angelma-<version>.zip`, your Domeneshop login, and an FTP
program such as FileZilla (or the file manager in the control panel, if you prefer).

1. **Choose PHP 8.2 or newer.** Log in to the Domeneshop control panel, open the web
   hotel for your domain and set the PHP version to 8.2 or newer. Save.

2. **Put the files in `www/`.** Your website lives in the folder called `www` on the web
   hotel. It should be empty (remove any placeholder page that is there).
   *Want the site in a subfolder instead, such as `https://www.example.no/club/`?* Then
   create the folder `www/club/` and put the files there instead of directly in `www/`;
   everywhere below, read `www/club/` for `www/` and add `/club` to the addresses.
   - If you can extract zip files on the server (for example in the control panel's file
     manager), upload the zip into `www/` and extract it there, so that `index.php`,
     `app/`, `install/` and the other files end up directly inside `www/`.
   - Otherwise, extract the zip on your own computer first. Then connect with your FTP
     program (the FTP details are in the control panel) and upload **everything inside**
     the extracted folder into `www/`. Make sure the hidden files called `.htaccess` are
     uploaded too; in FileZilla, turn on *Server → Force showing hidden files*.

3. **Run the installer.** Open your site's address in a browser, for example
   `https://www.example.no/`. The installer starts by itself and walks you through four
   short steps:
   - *Requirements*: every line should be green. If a folder is "not writable", give the
     folders `data` and `uploads` write permission in your FTP program (755 or 775).
   - *Site*: the site title, the page width and a colour scheme. All can be changed later.
   - *Administrator*: your username and a password of at least 10 characters.
   - *Finish*: Angelma creates the database and a first home page.

4. **Delete the `install` folder.** When the installer says it is done, delete the folder
   `www/install` with your FTP program or the file manager. The site works without it,
   and this keeps anyone from poking at the installer later.

5. **Log in.** Go to `https://www.example.no/login` (or use "Log in" at the right of the
   menu). Under *Pages* you build pages from rows and boxes, under *Images* you upload
   pictures, and under *Blogs* you write posts.

### Good to know

- **Backups:** *Backup* in the admin area downloads a zip with the database and all
  uploaded images. To restore, put `data/angelma.sqlite` and the `uploads` folder from the
  zip back in place by FTP.
- **Your data** is in `www/data/` (the database and settings) and `www/uploads/` (images).
  These folders are protected from the web; never delete them.
- **Links in your texts:** write links to your own pages starting with a slash, like
  `[About us](/about)`. Angelma adds the subfolder if the site lives in one, so the links
  keep working if you move the site.
- **Building the site on your own computer first:** you can install Angelma on your own
  computer (with Apache, or with `dev/server.sh` from the source code), build the site
  there, and then copy the whole folder, including `data/` and `uploads/`, to the web
  hotel by FTP. Stop the local server while copying, leave out `data/sessions/*` and
  `data/error.log`, delete `install/` on the web hotel, and make `data/`,
  `data/sessions/` and `uploads/` writable there. The site may be in a different
  subfolder (or none) on your computer than on the web hotel.
- **Moving or reinstalling:** the installer only runs while `data/installed.lock` does not
  exist. To start over completely, delete everything in `data/` except `.htaccess` and
  the `sessions` folder, and everything in `uploads/` except `.htaccess` and `index.html`.

## Development

Requirements: PHP 8.2 or newer with `pdo_sqlite`, `gd` and `mbstring`, and Composer.
`vendor/` is committed, so `composer install` is only needed after changing dependencies.

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

Angelma is free software, released under the GNU General Public License version 3
(GPL-3.0). See the `LICENSE` file for the full text. The bundled library
`league/commonmark` and its dependencies in `vendor/` keep their own licences.
