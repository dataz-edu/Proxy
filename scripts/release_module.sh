#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$ROOT_DIR/VERSION"
BUILD_SCRIPT="$ROOT_DIR/scripts/build_module.sh"

if [[ $# -ge 1 ]]; then
  VERSION="$1"
  echo "Updating VERSION to $VERSION"
  echo "$VERSION" > "$VERSION_FILE"
else
  if [[ ! -f "$VERSION_FILE" ]]; then
    echo "VERSION file missing, creating default 1.0.0"
    echo "1.0.0" > "$VERSION_FILE"
  fi
  VERSION="$(cat "$VERSION_FILE" | tr -d "\n\r")"
fi

if [[ -z "$VERSION" ]]; then
  echo "VERSION value is empty. Aborting." >&2
  exit 1
fi

echo "Releasing version $VERSION"

"$BUILD_SCRIPT"

cd "$ROOT_DIR"

git add dist/ VERSION
git commit -m "Release v$VERSION"
git tag -a "v$VERSION" -m "Release v$VERSION"
git push
git push --tags

if command -v gh >/dev/null 2>&1; then
  gh release create "v$VERSION" "dist/dataz-proxy-module-$VERSION.zip" \
    --title "Release v$VERSION" --notes "Automated WHMCS module release"
else
  echo "GitHub CLI not found; skipping GitHub release creation."
fi
