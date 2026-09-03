#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
export SMOKE_STATE_DIR="$PROJECT_ROOT/.smoke-state"
mkdir -p "$SMOKE_STATE_DIR" "$HOME/.dev/bin"
cp "$PROJECT_ROOT/bin/spc" "$HOME/.dev/bin/spc"
chmod +x "$HOME/.dev/bin/spc"
hash="$(printf 'pcntl,posix,mbstring,tokenizer,phar' | md5sum 2>/dev/null | cut -d' ' -f1 || true)"
if [[ -z "$hash" ]]; then hash="$(printf 'pcntl,posix,mbstring,tokenizer,phar' | md5 -q)"; fi
export SPC_BUILD_ROOT="$HOME/.dev/spc/8.3/$hash"
trap 'rm -rf "$(dirname "$PROJECT_ROOT")"' EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
[[ "$("$HOME/.dev/bin/php" -v)" == 'PHP 8.3 smoke' ]]
[[ -f "$SMOKE_STATE_DIR/doctor" && -f "$SMOKE_STATE_DIR/download" ]]
(cd "$PROJECT_ROOT" && "$DEV_BINARY" spc:combine)
[[ "$("$PROJECT_ROOT/app-bin")" == spc-combined-smoke ]]
(cd "$PROJECT_ROOT" && "$DEV_BINARY" spc:rebuild)
grep -Fq 'spc build --debug --no-strip --build-micro --build-cli --with-micro-fake-cli --rebuild' "$SMOKE_STATE_DIR/calls.log"
(cd "$PROJECT_ROOT" && "$DEV_BINARY" spc:clean)
[[ ! -e "$SPC_BUILD_ROOT" ]]
[[ "$PLATFORM" == linux || "$PLATFORM" == darwin ]]
