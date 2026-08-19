#!/usr/bin/env bash
# Reproduces the "e2e-tests" CI job locally: creates a throwaway symfony/skeleton
# project, requires this bundle through it via a Composer path repository (so the
# real, merged symfony/recipes-contrib Flex recipe is resolved), overlays the
# tests_e2e/basic fixtures, boots the PHP built-in server and asserts the rendered
# page carries the markers the bundle is supposed to wire up automatically.
set -euo pipefail

BUNDLE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEEP=0
PORT="${PGI_E2E_PORT:-8791}"

for arg in "$@"; do
    case "$arg" in
        --keep) KEEP=1 ;;
        *) echo "Unknown argument: $arg" >&2; exit 2 ;;
    esac
done

WORK_DIR="$(mktemp -d -t pgi-e2e-XXXXXX)"
PROJECT_DIR="$WORK_DIR/dummy-project"
SERVER_PID=""

log() { echo "==> $*"; }
fail() { echo "FAIL: $*" >&2; exit 1; }

cleanup() {
    if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    if [[ "$KEEP" -eq 1 ]]; then
        log "Leaving project in place (--keep): $PROJECT_DIR"
    else
        rm -rf "$WORK_DIR"
    fi
}
trap cleanup EXIT

BRANCH="$(git -C "$BUNDLE_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
[[ "$BRANCH" == "HEAD" ]] && BRANCH="main"

log "Creating dummy Symfony project"
composer create-project symfony/skeleton "$PROJECT_DIR" --no-interaction --no-progress

cd "$PROJECT_DIR"

log "Wiring bundle as a local path repository (branch: dev-$BRANCH)"
composer config repositories.local '{"type": "path", "url": "'"$BUNDLE_DIR"'", "canonical": false, "options": {"symlink": false}}'
composer config extra.symfony.allow-contrib true

log "Installing AssetMapper / Twig / Stimulus"
composer require symfony/asset-mapper symfony/twig-bundle symfony/stimulus-bundle --no-interaction --no-progress

log "Requiring the bundle from the local path repository (real Flex recipe)"
composer require "tito10047/progressive-image-bundle:dev-$BRANCH" --no-interaction --no-progress

if ! grep -q '"tito10047/progressive-image-bundle"' symfony.lock; then
    fail "symfony.lock has no entry for the bundle — the Flex recipe was not applied at all."
fi

log "Overlaying tests_e2e/basic fixtures"
cp -R "$BUNDLE_DIR/tests_e2e/basic/"* .

log "Starting PHP built-in server on 127.0.0.1:$PORT"
php -S "127.0.0.1:$PORT" -t public > "$WORK_DIR/server.log" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 20); do
    if curl -s -o /dev/null "http://127.0.0.1:$PORT/test-bundle"; then
        break
    fi
    sleep 0.5
done

RESPONSE_FILE="$WORK_DIR/response.html"
HTTP_CODE="$(curl -s -o "$RESPONSE_FILE" -w '%{http_code}' "http://127.0.0.1:$PORT/test-bundle")"
RESPONSE="$(cat "$RESPONSE_FILE")"

if [[ "$HTTP_CODE" != "200" ]]; then
    cat "$WORK_DIR/server.log" >&2
    cp "$RESPONSE_FILE" "$BUNDLE_DIR/tests_e2e/last-failure.html" 2>/dev/null || true
    fail "GET /test-bundle returned HTTP $HTTP_CODE (expected 200) — response saved to tests_e2e/last-failure.html"
fi

check() {
    local needle="$1" label="$2"
    if [[ "$RESPONSE" == *"$needle"* ]]; then
        log "OK: $label"
    else
        cp "$RESPONSE_FILE" "$BUNDLE_DIR/tests_e2e/last-failure.html"
        fail "$label — response saved to tests_e2e/last-failure.html"
    fi
}

check "progressive-image-container" "Component rendered"
check "tito10047--progressive-image-bundle--progressive-image" "Stimulus controller wired"
check "/media/pgi/" "Generated variant URL found in response"
check '<script type="importmap">' "AssetMapper importmap rendered"

log "All checks passed."
