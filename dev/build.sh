#!/bin/sh
# Build dist/angelma-<version>.zip, ready to extract into a web root.
set -e
cd "$(dirname "$0")/.."
VERSION=$(php -r 'echo require "app/version.php";')
OUT="dist/angelma-$VERSION.zip"

composer install --no-dev --optimize-autoloader --quiet
mkdir -p dist
rm -f "$OUT"
zip -qr "$OUT" . \
    -x 'dev/*' 'tests/*' 'dist/*' '.git/*' '.gitignore' 'phpunit.xml' '.phpunit.cache/*' \
       'data/*' 'uploads/*'
# Keep the protection files and empty folders the installer needs.
zip -q "$OUT" data/.htaccess data/sessions/.gitkeep uploads/.htaccess uploads/index.html
composer install --quiet

echo "Built $OUT"
