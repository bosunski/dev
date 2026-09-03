#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
SMOKE_ROOT="$(mktemp -d)"
SMOKE_HOME="$SMOKE_ROOT/home"
PROJECT_ROOT="$SMOKE_ROOT/project"
PUBLIC_ROOT="$PROJECT_ROOT/public"
SERVE_LOG="$SMOKE_ROOT/serve.log"

export HOME="$SMOKE_HOME"
export DEV_CADDY_HTTP_PORT=18080
export DEV_CADDY_HTTPS_PORT=18443
export DEV_CADDY_ADMIN_PORT=12019
export DEV_CADDY_SKIP_TRUST=1
export SHELL=/bin/zsh

cleanup() {
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop >/dev/null 2>&1) || true
  if [[ -n "${SERVE_PID:-}" ]]; then kill "$SERVE_PID" >/dev/null 2>&1 || true; fi
  rm -rf "$SMOKE_ROOT"
}
trap cleanup EXIT

mkdir -p "$SMOKE_HOME/.dev/caddy/logs" "$PROJECT_ROOT" "$PUBLIC_ROOT"
printf '\n' > "$SMOKE_HOME/.zshrc"
printf '%s\n' '<?php echo "dev-macos-smoke";' > "$PUBLIC_ROOT/index.php"
# Hosted CI connects directly to the configured high ports, so record the current
# boot session without modifying the runner's privileged PF configuration.
sysctl -n kern.boottime > "$SMOKE_HOME/.dev/caddy/.pf-configured"

cat > "$PROJECT_ROOT/dev.yml" <<'EOF'
name: macos-smoke

runtimes:
  php:
    provider: native

steps:
  - caddy:
      sites:
        - host: smoke.test
          root: public
          runtime: php
EOF

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
(cd "$PROJECT_ROOT" && "$DEV_BINARY" serve >"$SERVE_LOG" 2>&1) &
SERVE_PID=$!
response="$(curl --fail --silent --show-error --insecure --noproxy '*' --retry 20 --retry-all-errors \
  --retry-delay 1 --resolve smoke.test:18443:127.0.0.1 https://smoke.test:18443/)"
[[ "$response" == 'dev-macos-smoke' ]]

kill -TERM "$SERVE_PID"
wait "$SERVE_PID"
unset SERVE_PID

(cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop)
for _ in {1..20}; do
  if ! curl --fail --silent --noproxy '*' http://127.0.0.1:12019/config/ >/dev/null 2>&1; then
    exit 0
  fi
  sleep 0.2
done
if curl --fail --silent --noproxy '*' http://127.0.0.1:12019/config/ >/dev/null 2>&1; then
  echo 'Caddy admin endpoint remained available after shutdown.' >&2
  exit 1
fi
