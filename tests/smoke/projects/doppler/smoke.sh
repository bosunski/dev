#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PROJECT_ROOT="${3:?}"
export SMOKE_STATE_DIR="$PROJECT_ROOT/.smoke-state"
mkdir -p "$SMOKE_STATE_DIR"
export PATH="$PROJECT_ROOT/bin:$PATH"
trap 'rm -rf "$(dirname "$PROJECT_ROOT")"' EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
grep -Fq 'DOPPLER_SECRET="from-doppler"' "$PROJECT_ROOT/.env"
[[ -f "$SMOKE_STATE_DIR/setup" ]]
