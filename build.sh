#!/usr/bin/env bash
# Build a versioned release zip of the plugin.
# Usage: ./build.sh [version]
# If no version is passed, it is read from the plugin header.
set -euo pipefail

cd "$(dirname "$0")"

SLUG="maintenance-by-btgn-media"
MAIN="$SLUG/$SLUG.php"

if [ -n "${1:-}" ]; then
    VERSION="$1"
else
    VERSION="$(grep -m1 -E '^\s*\*\s*Version:' "$MAIN" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')"
fi

if [ -z "$VERSION" ]; then
    echo "Could not determine version." >&2
    exit 1
fi

# Syntax check every PHP file before packaging.
find "$SLUG" -name '*.php' -print0 | while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null
done

mkdir -p releases
OUT="releases/${SLUG}-${VERSION}.zip"
rm -f "$OUT" "${SLUG}.zip"
zip -rq "$OUT" "$SLUG" -x '*.DS_Store'
cp "$OUT" "${SLUG}.zip"

echo "Built $OUT"
