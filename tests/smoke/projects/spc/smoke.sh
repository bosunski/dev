#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
trap 'rm -rf "$(dirname "$PROJECT_ROOT")"' EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
(cd "$PROJECT_ROOT" && "$DEV_BINARY" run verify-spc)
[[ -f "$PROJECT_ROOT/spc.ok" ]]
find "$HOME/.dev/runtimes/php/8.3/spc" -path '*/buildroot/bin/php-fpm' -type f | grep -q .
[[ "$PLATFORM" == linux || "$PLATFORM" == darwin ]]
