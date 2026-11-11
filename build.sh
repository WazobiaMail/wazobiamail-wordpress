#!/usr/bin/env bash
#
# Build a clean WordPress.org submission ZIP.
# Usage: bash build.sh
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

VERSION="$(grep -m1 '^ \* Version:' wazobiamail.php | awk '{print $3}')"
if [[ -z "$VERSION" ]]; then
  echo "Could not read Version from wazobiamail.php" >&2
  exit 1
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/wazobiamail"

if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude-from=.distignore ./ "$STAGE/wazobiamail/"
else
  tar --exclude-from=.distignore -cf - . | (cd "$STAGE/wazobiamail" && tar -xf -)
fi

mkdir -p dist
rm -f "dist/wazobiamail-$VERSION.zip"
(cd "$STAGE" && zip -qr "$DIR/dist/wazobiamail-$VERSION.zip" wazobiamail)
echo "Built dist/wazobiamail-$VERSION.zip"
unzip -l "dist/wazobiamail-$VERSION.zip" | sed -n '1,20p'
