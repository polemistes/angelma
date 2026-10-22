#!/bin/sh
# Publish the current version on GitHub so installed sites can update themselves
# (PLAN.md section 6.16): build the zip and its checksum, tag v<version>, push, and
# create the release with both files and the version's section of CHANGELOG.md.
#
# Needs the GitHub command line tool (gh) logged in to an account that may push to
# the repository. Run from a clean, committed working tree.
set -e
cd "$(dirname "$0")/.."
VERSION=$(php -r 'echo require "app/version.php";')
TAG="v$VERSION"

if [ -n "$(git status --porcelain)" ]; then
    echo "Commit or stash your changes first." >&2
    exit 1
fi
NOTES=$(awk -v v="$VERSION" '$0 == "## " v {flag=1; next} /^## /{flag=0} flag' CHANGELOG.md)
if [ -z "$NOTES" ]; then
    echo "CHANGELOG.md has no section '## $VERSION'. Add one first." >&2
    exit 1
fi

dev/build.sh
git tag -a "$TAG" -m "Angelma $VERSION" 2>/dev/null || echo "Tag $TAG already exists, reusing it."
git push origin HEAD "$TAG"
gh release create "$TAG" "dist/angelma-$VERSION.zip" "dist/angelma-$VERSION.zip.sha256" \
    --title "Angelma $VERSION" --notes "$NOTES"
echo "Released $TAG. Sites will see it on their next update check."
