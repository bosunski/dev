#!/usr/bin/env bash
set -euo pipefail

DEV_BINARY="${1:?}"
PLATFORM="${2:?}"
PROJECT_ROOT="${3:?}"
SERVE_LOG="$PROJECT_ROOT/serve.log"

cleanup() {
  if [[ -n "${SERVE_PID:-}" ]]; then kill "$SERVE_PID" >/dev/null 2>&1 || true; fi
  rm -rf "$(dirname "$PROJECT_ROOT")"
}
trap cleanup EXIT

(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
[[ -f "$PROJECT_ROOT/script.ok" ]]
[[ -f "$PROJECT_ROOT/nested/command.ok" ]]

(cd "$PROJECT_ROOT" && exec "$DEV_BINARY" serve workers >"$SERVE_LOG" 2>&1) &
SERVE_PID=$!
for _ in {1..30}; do
  [[ -f "$PROJECT_ROOT/always.out" && -f "$PROJECT_ROOT/nested/worker-one.out" && -f "$PROJECT_ROOT/worker-two.out" ]] && break
  sleep 0.2
done
[[ "$(cat "$PROJECT_ROOT/always.out")" == configured ]]
[[ "$(cat "$PROJECT_ROOT/nested/worker-one.out")" == configured:from-test-env ]]
[[ "$(cat "$PROJECT_ROOT/worker-two.out")" == unset ]]
kill -TERM "$SERVE_PID"
wait "$SERVE_PID"
unset SERVE_PID

# A second up proves the met? predicate and cached state are repeatable.
(cd "$PROJECT_ROOT" && "$DEV_BINARY" up --self)
[[ "$PLATFORM" == linux || "$PLATFORM" == darwin ]]
