#!/bin/sh
# Delete the local installation so the installer can be tested from scratch.
cd "$(dirname "$0")/.." || exit 1
rm -f data/angelma.sqlite data/angelma.sqlite-* data/config.php data/installed.lock data/error.log
find data/sessions -type f ! -name .gitkeep -delete
find uploads -mindepth 1 ! -name .htaccess ! -name index.html -exec rm -rf {} +
echo "Reset done. Open http://localhost:8080/ to run the installer."
