#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
export SMOKE_STATE_DIR="$PROJECT_ROOT/.smoke-state"
mkdir -p "$SMOKE_STATE_DIR"
export PATH="$PROJECT_ROOT/bin:$PATH"
export DEV_ELEVATION_TOOL=sudo
trap 'rm -rf "$(dirname "$PROJECT_ROOT")"' EXIT

if [[ "$PLATFORM" == linux ]]; then
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
  grep -Fq 'resolvectl dns smoke0 127.0.0.53' "$SMOKE_STATE_DIR/calls.log"
  grep -Fq 'resolvectl domain smoke0 ~app.smoke.test ~api.smoke.test' "$SMOKE_STATE_DIR/calls.log"
  grep -Fq 'resolvectl default-route smoke0 no' "$SMOKE_STATE_DIR/calls.log"
else
  if output="$(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self 2>&1)"; then
    echo 'DNS unexpectedly succeeded on macOS.' >&2
    exit 1
  fi
  grep -Fq 'DNS step currently supports Linux with systemd-resolved only.' <<<"$output"
fi
