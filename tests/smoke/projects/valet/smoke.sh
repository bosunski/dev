#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
export SMOKE_STATE_DIR="$PROJECT_ROOT/.smoke-state"
mkdir -p "$SMOKE_STATE_DIR" "$HOME/.composer/vendor/bin"
if [[ "$PLATFORM" == darwin ]]; then
  export SMOKE_VALET_DIR="$HOME/.config/valet"
else
  export SMOKE_VALET_DIR="$HOME/.valet"
fi
cp "$PROJECT_ROOT/valet" "$HOME/.composer/vendor/bin/valet"
chmod +x "$HOME/.composer/vendor/bin/valet"
trap 'rm -rf "$(dirname "$PROJECT_ROOT")"' EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
[[ -f "$PROJECT_ROOT/valet-env.ok" ]]
[[ -f "$SMOKE_VALET_DIR/Nginx/linked-smoke.conf" ]]
[[ -f "$SMOKE_VALET_DIR/Nginx/proxy-smoke.conf" ]]
grep -Fq 'valet link linked-smoke --secure' "$SMOKE_STATE_DIR/calls.log"
grep -Fq 'valet proxy proxy-smoke http://127.0.0.1:19090' "$SMOKE_STATE_DIR/calls.log"
[[ -f "$HOME/.dev/valet/sites/linked-smoke.md5" ]]
(cd "$PROJECT_ROOT" && "$DEV_BINARY" valet:restart)
[[ -f "$SMOKE_STATE_DIR/restarted" ]]
