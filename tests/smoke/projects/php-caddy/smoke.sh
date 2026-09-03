#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
PLATFORM="${2:?Platform is required}"
PROJECT_ROOT="${3:?Copied project path is required}"
SERVE_LOG="$PROJECT_ROOT/serve.log"

export DEV_CADDY_HTTP_PORT=18080
export DEV_CADDY_HTTPS_PORT=18443
export DEV_CADDY_ADMIN_PORT=12019
export DEV_CADDY_SKIP_TRUST=1
export DEV_CADDY_DISABLE_PORT_REDIRECT=1

cleanup() {
  if [[ -n "${SERVE_PID:-}" ]]; then kill "$SERVE_PID" >/dev/null 2>&1 || true; fi
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop >/dev/null 2>&1) || true
  if [[ "${SMOKE_PASSED:-0}" != '1' && -f "$SERVE_LOG" ]]; then
    echo '--- dev serve output ---' >&2
    sed -n '1,240p' "$SERVE_LOG" >&2
  fi
  rm -rf "$(dirname "$PROJECT_ROOT")"
}
trap cleanup EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
(cd "$PROJECT_ROOT" && exec "$DEV_BINARY" serve >"$SERVE_LOG" 2>&1) &
SERVE_PID=$!

response="$(curl --fail --silent --show-error --insecure --noproxy '*' --retry 30 --retry-all-errors \
  --retry-delay 1 --resolve smoke.test:18443:127.0.0.1 https://smoke.test:18443/)"
[[ "$response" == 'dev-runtime-smoke' ]]

kill -TERM "$SERVE_PID"
wait "$SERVE_PID"
unset SERVE_PID

(cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop)
for _ in {1..20}; do
  if ! curl --fail --silent --noproxy '*' http://127.0.0.1:12019/config/ >/dev/null 2>&1; then
    SMOKE_PASSED=1
    exit 0
  fi
  sleep 0.2
done

echo 'Caddy admin endpoint remained available after shutdown.' >&2
exit 1
