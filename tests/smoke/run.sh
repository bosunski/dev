#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
PLATFORM="${2:-$(uname -s | tr '[:upper:]' '[:lower:]')}"
SMOKE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUITE_ROOT="$(mktemp -d)"
export SMOKE_HOST_HOME="$HOME"
export HOME="$SUITE_ROOT/home"
mkdir -p "$HOME"
export PATH="$HOME/.dev/bin:/opt/homebrew/bin:/home/linuxbrew/.linuxbrew/bin:/usr/local/bin:/usr/bin:/bin"
export SHELL=/bin/bash
export DEV_ELEVATION_TOOL=sudo
touch "$HOME/.bashrc"
cleanup_suite() {
  if [[ "${SMOKE_KEEP_TMP:-0}" == 1 ]]; then
    echo "Smoke workspace retained at $SUITE_ROOT" >&2
  else
    rm -rf "$SUITE_ROOT"
  fi
}
trap cleanup_suite EXIT

case "$PLATFORM" in
  darwin|linux) ;;
  *) echo "Unsupported smoke-test platform: $PLATFORM" >&2; exit 2 ;;
esac

for fixture in "$SMOKE_DIR"/projects/*; do
  [[ -d "$fixture" && -f "$fixture/dev.yml" && -f "$fixture/smoke.sh" ]] || continue
  name="$(basename "$fixture")"
  worktree="$(mktemp -d "$SUITE_ROOT/project.XXXXXX")/$name"
  mkdir -p "$worktree"
  cp -R "$fixture/." "$worktree/"
  rm "$worktree/smoke.sh"
  echo "::group::Smoke project: $name ($PLATFORM)"
  bash "$fixture/smoke.sh" "$DEV_BINARY" "$PLATFORM" "$worktree"
  echo "::endgroup::"
done
