#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
SMOKE_PASSED=0
cleanup() {
  if [[ "$SMOKE_PASSED" != 1 && "$PLATFORM" == linux ]]; then
    sudo nginx -t >&2 || true
    sudo systemctl status nginx --no-pager >&2 || true
    sudo journalctl -u nginx --no-pager -n 80 >&2 || true
  fi
  valet_bin="$HOME/.composer/vendor/bin/valet"
  [[ -x "$valet_bin" ]] || valet_bin="$HOME/.config/composer/vendor/bin/valet"
  "$valet_bin" unlink linked-smoke >/dev/null 2>&1 || true
  "$valet_bin" unproxy proxy-smoke >/dev/null 2>&1 || true
  "$valet_bin" stop >/dev/null 2>&1 || true
  rm -rf "$(dirname "$PROJECT_ROOT")"
}
trap cleanup EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
response="$(curl --fail --silent --show-error --insecure --noproxy '*' --retry 20 --retry-all-errors \
  --retry-delay 1 --resolve linked-smoke.test:443:127.0.0.1 https://linked-smoke.test/)"
[[ "$response" == real-valet-smoke ]]
[[ -f "$HOME/.dev/valet/sites/linked-smoke.md5" ]]
(cd "$PROJECT_ROOT" && "$DEV_BINARY" valet:restart)
SMOKE_PASSED=1
