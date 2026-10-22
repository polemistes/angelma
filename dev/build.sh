#!/bin/sh
# Build dist/angelma-<version>.zip, ready to extract into a web root.
#
# The zip contains the application with production dependencies only. Development
# files (dev/, tests/, git files, PHPUnit config, planning notes) and any local data
# or uploads are left out; the empty data/ and uploads/ folders with their
# protection files are included.
set -e
cd "$(dirname "$0")/.."
VERSION=$(php -r 'echo require "app/version.php";')
OUT="dist/angelma-$VERSION.zip"

restore() { composer install --quiet; }
trap restore EXIT

composer install --no-dev --optimize-autoloader --quiet
mkdir -p dist
rm -f "$OUT"
zip -qr "$OUT" . \
    -x 'dev/*' 'tests/*' 'dist/*' '.git/*' '.gitignore' '.gitattributes' 'phpunit.xml' \
       '.phpunit.cache/*' 'data/*' 'uploads/*' 'CLAUDE.md' 'PLAN.md' 'DECISIONS.md' '.claude/*'
# The empty folders the installer needs, with their protection files.
zip -q "$OUT" data/.htaccess data/sessions/.gitkeep uploads/.htaccess uploads/index.html

# The checksum the updater verifies (PLAN.md section 6.16), in sha256sum's format.
(cd dist && sha256sum "angelma-$VERSION.zip" > "angelma-$VERSION.zip.sha256")

echo "Built $OUT ($(du -h "$OUT" | cut -f1)) and $OUT.sha256"
