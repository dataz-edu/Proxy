#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$ROOT_DIR/VERSION"
DIST_DIR="$ROOT_DIR/dist"
MODULE_PATH="$ROOT_DIR/modules/servers/dataz_proxy"

if [[ ! -f "$VERSION_FILE" ]]; then
  echo "VERSION file missing, creating default 1.0.0"
  echo "1.0.0" > "$VERSION_FILE"
fi

VERSION="$(cat "$VERSION_FILE" | tr -d "\n\r")"

if [[ -z "$VERSION" ]]; then
  echo "VERSION file is empty. Please set a version string." >&2
  exit 1
fi

echo "Building WHMCS module ZIP for version $VERSION"
mkdir -p "$DIST_DIR"

OUTPUT_ZIP="$DIST_DIR/dataz-proxy-module-$VERSION.zip"
rm -f "$OUTPUT_ZIP"

if [[ ! -d "$MODULE_PATH" ]]; then
  echo "Module directory not found at $MODULE_PATH" >&2
  exit 1
fi

(cd "$ROOT_DIR" && zip -r "$OUTPUT_ZIP" "modules/servers/dataz_proxy")

echo "Created $OUTPUT_ZIP"
