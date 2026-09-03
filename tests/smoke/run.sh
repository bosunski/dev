#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
PLATFORM="${2:-$(uname -s | tr '[:upper:]' '[:lower:]')}"
SMOKE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

case "$PLATFORM" in
  darwin|linux) ;;
  *) echo "Unsupported smoke-test platform: $PLATFORM" >&2; exit 2 ;;
esac

for scenario in "$SMOKE_DIR"/scenarios/*.sh; do
  name="$(basename "$scenario" .sh)"
  echo "::group::Smoke scenario: $name ($PLATFORM)"
  bash "$scenario" "$DEV_BINARY" "$PLATFORM"
  echo "::endgroup::"
done
