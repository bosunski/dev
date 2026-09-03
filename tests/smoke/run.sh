#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?Path to the compiled DEV binary is required}"
PLATFORM="${2:-$(uname -s | tr '[:upper:]' '[:lower:]')}"
SMOKE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

case "$PLATFORM" in
  darwin|linux) ;;
  *) echo "Unsupported smoke-test platform: $PLATFORM" >&2; exit 2 ;;
esac

for fixture in "$SMOKE_DIR"/projects/*; do
  [[ -d "$fixture" && -f "$fixture/dev.yml" && -f "$fixture/smoke.sh" ]] || continue
  name="$(basename "$fixture")"
  worktree="$(mktemp -d)/$name"
  mkdir -p "$worktree"
  cp -R "$fixture/." "$worktree/"
  rm "$worktree/smoke.sh"
  echo "::group::Smoke project: $name ($PLATFORM)"
  bash "$fixture/smoke.sh" "$DEV_BINARY" "$PLATFORM" "$worktree"
  echo "::endgroup::"
done
