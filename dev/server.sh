#!/bin/sh
# Start the PHP built-in server for local development.
# Usage: dev/server.sh   (HOST and PORT may be set in the environment)
cd "$(dirname "$0")/.." || exit 1
exec php \
    -d upload_max_filesize=20M \
    -d post_max_size=24M \
    -d memory_limit=256M \
    -S "${HOST:-localhost}:${PORT:-8080}" dev/router.php
