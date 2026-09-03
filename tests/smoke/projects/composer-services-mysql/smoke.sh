#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
trap cleanup EXIT

cleanup() {
  if ! docker exec dev-mysql mysqladmin ping -uroot --silent >/dev/null 2>&1; then
    docker ps -a --filter name=dev-mysql >&2 || true
    docker logs --tail 100 dev-mysql >&2 || true
  fi
  docker rm -f dev-mysql >/dev/null 2>&1 || true
  if [[ -d "$HOME/.dev/mysql" ]]; then
    sudo chown -R "$(id -u):$(id -g)" "$HOME/.dev/mysql"
  fi
  if [[ "$PLATFORM" == darwin ]]; then
    brew services stop redis >/dev/null 2>&1 || true
  else
    sudo systemctl stop redis-server >/dev/null 2>&1 || true
  fi
  rm -rf "$(dirname "$PROJECT_ROOT")"
}

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self --force)
[[ -d "$HOME/.composer/vendor/psr/log" \
  || -d "$HOME/.config/composer/vendor/psr/log" \
  || -d "$SMOKE_HOST_HOME/.composer/vendor/psr/log" \
  || -d "$SMOKE_HOST_HOME/.config/composer/vendor/psr/log" ]]
[[ "$(redis-cli ping)" == PONG ]]
docker exec dev-mysql mysql -uroot -N -e 'SHOW DATABASES;' | grep -Fxq smoke_app
docker exec dev-mysql mysql -uroot -N -e 'SHOW DATABASES;' | grep -Fxq smoke_test
grep -Fq 'DEV_MYSQL_HOST' "$PROJECT_ROOT/.shadowenv.d/000_default.lisp"
