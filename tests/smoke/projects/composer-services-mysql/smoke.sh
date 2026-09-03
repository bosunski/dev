#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
trap cleanup EXIT

cleanup() {
  docker rm -f dev-mysql >/dev/null 2>&1 || true
  if [[ "$PLATFORM" == darwin ]]; then
    brew services stop redis >/dev/null 2>&1 || true
  else
    sudo systemctl stop redis-server >/dev/null 2>&1 || true
  fi
  rm -rf "$(dirname "$PROJECT_ROOT")"
}

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
composer global show psr/log --format=json | grep -Fq 'psr/log'
[[ "$(redis-cli ping)" == PONG ]]
docker exec dev-mysql mysql -uroot -N -e 'SHOW DATABASES;' | grep -Fxq smoke_app
docker exec dev-mysql mysql -uroot -N -e 'SHOW DATABASES;' | grep -Fxq smoke_test
grep -Fq 'DEV_MYSQL_HOST' "$PROJECT_ROOT/.shadowenv.d/000_default.lisp"
