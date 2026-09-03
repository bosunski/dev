#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
PLATFORM="${2:-$(uname -s | tr '[:upper:]' '[:lower:]')}"
SMOKE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export SMOKE_HOST_HOME="$HOME"
SUITE_ROOT="$(mktemp -d "$SMOKE_HOST_HOME/dev-smoke.XXXXXX")"
BASE_PATH="/opt/homebrew/bin:/home/linuxbrew/.linuxbrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"
export SHELL=/bin/bash
export DEV_ELEVATION_TOOL=sudo
export COMPOSER_NO_INTERACTION=1
export DEBIAN_FRONTEND=noninteractive
export GIT_TERMINAL_PROMPT=0
export HOMEBREW_NO_AUTO_UPDATE=1
export HOMEBREW_NO_ENV_HINTS=1
export SSH_ASKPASS=/bin/false
export SUDO_ASKPASS=/bin/false
SMOKE_SCENARIO_TIMEOUT_SECONDS="${SMOKE_SCENARIO_TIMEOUT_SECONDS:-300}"
cleanup_suite() {
  if [[ "${SMOKE_KEEP_TMP:-0}" == 1 ]]; then
    echo "Smoke workspace retained at $SUITE_ROOT" >&2
  else
    rm -rf "$SUITE_ROOT"
  fi
}
trap cleanup_suite EXIT

terminate_tree() {
  local signal="$1"
  local parent="$2"
  local children
  children="$(pgrep -P "$parent" 2>/dev/null || true)"
  for child in $children; do
    terminate_tree "$signal" "$child"
  done
  kill "-$signal" "$parent" 2>/dev/null || true
}

run_scenario() {
  local fixture="$1"
  local name="$2"
  local worktree="$3"
  local marker="$SUITE_ROOT/timeout-$name"
  local scenario_pid watchdog_pid status

  bash "$fixture/smoke.sh" "$DEV_BINARY" "$PLATFORM" "$worktree" </dev/null &
  scenario_pid=$!
  (
    sleep "$SMOKE_SCENARIO_TIMEOUT_SECONDS"
    if kill -0 "$scenario_pid" 2>/dev/null; then
      touch "$marker"
      terminate_tree TERM "$scenario_pid"
      sleep 5
      terminate_tree KILL "$scenario_pid"
    fi
  ) &
  watchdog_pid=$!

  if wait "$scenario_pid"; then
    status=0
  else
    status=$?
  fi
  kill "$watchdog_pid" 2>/dev/null || true
  wait "$watchdog_pid" 2>/dev/null || true

  if [[ -f "$marker" ]]; then
    echo "::error::Smoke project '$name' exceeded ${SMOKE_SCENARIO_TIMEOUT_SECONDS} seconds" >&2
    return 124
  fi
  return "$status"
}

case "$PLATFORM" in
  darwin|linux) ;;
  *) echo "Unsupported smoke-test platform: $PLATFORM" >&2; exit 2 ;;
esac

for fixture in "$SMOKE_DIR"/projects/*; do
  [[ -d "$fixture" && -f "$fixture/dev.yml" && -f "$fixture/smoke.sh" ]] || continue
  name="$(basename "$fixture")"
  export HOME="$SUITE_ROOT/home-$name"
  export PATH="$HOME/.dev/bin:$BASE_PATH"
  mkdir -p "$HOME"
  touch "$HOME/.bashrc"
  worktree="$(mktemp -d "$SUITE_ROOT/project.XXXXXX")/$name"
  mkdir -p "$worktree"
  cp -R "$fixture/." "$worktree/"
  rm "$worktree/smoke.sh"
  echo "::group::Smoke project: $name ($PLATFORM)"
  run_scenario "$fixture" "$name" "$worktree"
  echo "::endgroup::"
done
