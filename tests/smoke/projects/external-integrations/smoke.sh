#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
export SMOKE_STATE_DIR="$PROJECT_ROOT/.smoke-state"
mkdir -p "$SMOKE_STATE_DIR"
export PATH="$PROJECT_ROOT/bin:$PATH"
export DEV_ELEVATION_TOOL=sudo

cleanup() {
  docker rm -f dev-mysql >/dev/null 2>&1 || true
  rm -rf "$(dirname "$PROJECT_ROOT")"
}
trap cleanup EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
[[ -f "$SMOKE_STATE_DIR/composer-auth" ]]
[[ -f "$SMOKE_STATE_DIR/composer-package" ]]
[[ -f "$SMOKE_STATE_DIR/service-smoke-worker" ]]
[[ -f "$SMOKE_STATE_DIR/mysql-running" ]]
[[ -f "$SMOKE_STATE_DIR/mysql-databases" ]]
grep -Fq 'DOPPLER_SECRET="from-doppler"' "$PROJECT_ROOT/.env"
grep -Fq 'DEV_MYSQL_HOST' "$PROJECT_ROOT/.shadowenv.d/000_default.lisp"
grep -Fq '127.0.0.1' "$PROJECT_ROOT/.shadowenv.d/000_default.lisp"
grep -Fq 'composer global require smoke/tool:' "$SMOKE_STATE_DIR/calls.log"
grep -Fq 'doppler secrets download --project smoke-project --config ci' "$SMOKE_STATE_DIR/calls.log"
if [[ "$PLATFORM" == darwin ]]; then
  grep -Fq 'brew services start redis' "$SMOKE_STATE_DIR/calls.log"
  grep -Fq 'brew services start smoke-worker' "$SMOKE_STATE_DIR/calls.log"
else
  grep -Eq 'systemctl enable --now (redis-server|valkey)' "$SMOKE_STATE_DIR/calls.log"
  grep -Fq 'systemctl enable --now smoke-worker' "$SMOKE_STATE_DIR/calls.log"
fi
[[ -f "$SMOKE_STATE_DIR/doppler-setup" ]]
