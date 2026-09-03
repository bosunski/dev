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
export DEV_ELEVATION_TOOL=sudo

cleanup() {
  if [[ -n "${SERVE_PID:-}" ]]; then kill "$SERVE_PID" >/dev/null 2>&1 || true; fi
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy unlink >/dev/null 2>&1) || true
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop >/dev/null 2>&1) || true
  if [[ "${SMOKE_PASSED:-0}" != '1' && -f "$SERVE_LOG" ]]; then
    echo '--- dev serve output ---' >&2
    sed -n '1,240p' "$SERVE_LOG" >&2
  fi
  rm -rf "$(dirname "$PROJECT_ROOT")"
}
trap cleanup EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
(cd "$PROJECT_ROOT" && "$DEV_BINARY" run fixture-check)
grep -Fq "SMOKE_CONFIGURED='from-dev-yml'" "$PROJECT_ROOT/.env"
grep -Fq 'SMOKE_APOSTROPHE="O'\''Reilly"' "$PROJECT_ROOT/.env"

(cd "$PROJECT_ROOT" && exec "$DEV_BINARY" serve >"$SERVE_LOG" 2>&1) &
SERVE_PID=$!

php_response="$(curl --fail --silent --show-error --insecure --noproxy '*' --retry 30 --retry-all-errors \
  --retry-delay 1 --resolve smoke.test:18443:127.0.0.1 https://smoke.test:18443/)"
[[ "$php_response" == 'dev-runtime-smoke' ]]

static_response="$(curl --fail --silent --show-error --noproxy '*' \
  --resolve static.smoke.test:18080:127.0.0.1 http://static.smoke.test:18080/health.txt)"
[[ "$static_response" == 'static-smoke-ok' ]]

headers="$PROJECT_ROOT/api-headers.txt"
api_response="$(curl --fail --silent --show-error --insecure --noproxy '*' -D "$headers" \
  --resolve api.smoke.test:18443:127.0.0.1 https://api.smoke.test:18443/api/hello?source=smoke)"
[[ "$api_response" == "from-dev-yml:O'Reilly:/hello?source=smoke:from-route" ]]
grep -Eiq '^X-Smoke-Response: present' "$headers"

fallback_response="$(curl --fail --silent --show-error --insecure --noproxy '*' \
  --resolve api.smoke.test:18443:127.0.0.1 https://api.smoke.test:18443/fallback)"
[[ "$fallback_response" == "from-dev-yml:O'Reilly:/fallback:from-caddy" ]]

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
