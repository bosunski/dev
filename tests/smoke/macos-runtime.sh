#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
SMOKE_ROOT="$(mktemp -d)"
SMOKE_HOME="$SMOKE_ROOT/home"
PROJECT_ROOT="$SMOKE_ROOT/project"
PUBLIC_ROOT="$PROJECT_ROOT/public"
FPM_SOCKET="$SMOKE_ROOT/php-fpm.sock"
FPM_CONFIG="$SMOKE_ROOT/php-fpm.conf"
PHP_FPM="$(brew --prefix php)/sbin/php-fpm"

export HOME="$SMOKE_HOME"
export DEV_CADDY_HTTP_PORT=18080
export DEV_CADDY_HTTPS_PORT=18443
export DEV_CADDY_ADMIN_PORT=12019

cleanup() {
  (cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy stop >/dev/null 2>&1) || true
  if [[ -n "${FPM_PID:-}" ]]; then kill "$FPM_PID" >/dev/null 2>&1 || true; fi
  rm -rf "$SMOKE_ROOT"
}
trap cleanup EXIT

mkdir -p "$SMOKE_HOME/.dev/caddy/logs" "$PROJECT_ROOT" "$PUBLIC_ROOT"
printf 'name: macos-smoke\n' > "$PROJECT_ROOT/dev.yml"
printf '%s\n' '<?php echo "dev-macos-smoke";' > "$PUBLIC_ROOT/index.php"
# Hosted CI connects directly to the configured high ports, so record the current
# boot session without modifying the runner's privileged PF configuration.
sysctl -n kern.boottime > "$SMOKE_HOME/.dev/caddy/.pf-configured"

cat > "$FPM_CONFIG" <<EOF
[global]
daemonize = no
error_log = $SMOKE_ROOT/php-fpm.log

[www]
listen = $FPM_SOCKET
listen.mode = 0600
pm = static
pm.max_children = 1
clear_env = no
EOF

cat > "$SMOKE_HOME/.dev/caddy/Caddyfile" <<EOF
{
  admin 127.0.0.1:12019
  http_port 18080
  https_port 18443
  skip_install_trust
}

https://smoke.test:18443 {
  tls internal
  root * $PUBLIC_ROOT
  php_fastcgi unix/$FPM_SOCKET
  file_server
}
EOF

"$PHP_FPM" --nodaemonize --force-stderr --fpm-config "$FPM_CONFIG" &
FPM_PID=$!
for _ in {1..30}; do
  [[ -S "$FPM_SOCKET" ]] && break
  sleep 0.2
done
[[ -S "$FPM_SOCKET" ]]

(cd "$PROJECT_ROOT" && "$DEV_BINARY" caddy start)
response="$(curl --fail --silent --show-error --insecure --noproxy '*' --retry 20 --retry-all-errors \
  --retry-delay 1 --resolve smoke.test:18443:127.0.0.1 https://smoke.test:18443/)"
[[ "$response" == 'dev-macos-smoke' ]]

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
