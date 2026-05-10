#!/usr/bin/env bash
# Force Packagist to re-scan one or all polysource/* mirrors.
#
# Use cases:
#   - Speed up indexing of a freshly pushed tag (Packagist's webhook
#     usually does this automatically within ~30 s; this is the manual
#     fallback).
#   - Force a purge of a tag that was deleted from a mirror (e.g. a
#     pre-release that was used to test the pipeline) — Packagist does
#     not always re-scan on delete events, so the deleted tag can
#     linger in the index for hours. Re-scanning forces an immediate
#     reconciliation against the mirror's actual ref state.
#
# Credentials are taken from environment variables ONLY. The token
# never appears on argv, in the script, or in shell history.
#
# Usage:
#   export PACKAGIST_USER="<your packagist username>"
#   read -s PACKAGIST_TOKEN && export PACKAGIST_TOKEN   # paste token, hidden
#   ./scripts/packagist-resync.sh                  # all 16 mirrors
#   ./scripts/packagist-resync.sh core symfony-bundle    # subset
#
# To get the token: https://packagist.org/profile/ → "API tokens" →
# "Show API token" or "Create new token". Treat it like a password.
#
# After running:
#   unset PACKAGIST_TOKEN PACKAGIST_USER
# to clear the credentials from the current shell session.

set -euo pipefail

ALL_PACKAGES=(
    core
    symfony-bundle
    twig-theme
    filter
    easyadmin-filter-bridge
    audit
    widgets
    search
    workflow-bridge
    bulk-async
    adapter-messenger
    adapter-doctrine
    adapter-redis
    adapter-flysystem
    adapter-http
    adapter-meilisearch
)

if [[ -z "${PACKAGIST_USER:-}" || -z "${PACKAGIST_TOKEN:-}" ]]; then
    echo "ERROR: PACKAGIST_USER and PACKAGIST_TOKEN must be exported." >&2
    echo "       See the header comment of this script." >&2
    exit 2
fi

PACKAGES=("${@:-${ALL_PACKAGES[@]}}")
if [[ $# -eq 0 ]]; then
    PACKAGES=("${ALL_PACKAGES[@]}")
fi

echo "============================================================"
echo "  Packagist re-scan — ${#PACKAGES[@]} package(s)"
echo "  User:   $PACKAGIST_USER"
echo "  Token:  ****${PACKAGIST_TOKEN: -4}   (last 4 chars only)"
echo "============================================================"

ok=0
fail=0
for pkg in "${PACKAGES[@]}"; do
    body=$(printf '{"repository":{"url":"https://github.com/polysource/%s"}}' "$pkg")
    response=$(curl -sS -X POST -H 'content-type:application/json' \
        "https://packagist.org/api/update-package?username=${PACKAGIST_USER}&apiToken=${PACKAGIST_TOKEN}" \
        -d "$body" 2>&1)

    status=$(echo "$response" | jq -r '.status // .error // empty' 2>/dev/null || echo "$response")
    if [[ "$status" == "success" ]]; then
        printf "  ✓ %-30s queued for re-scan\n" "$pkg"
        ok=$((ok+1))
    else
        printf "  ✗ %-30s %s\n" "$pkg" "$status"
        fail=$((fail+1))
    fi
done

echo "============================================================"
echo "  OK: $ok / ${#PACKAGES[@]}    FAIL: $fail"
echo "============================================================"
echo
echo "Packagist usually finishes the re-scan within 10–30 seconds."
echo "Verify with:"
echo "  curl -sf https://repo.packagist.org/p2/polysource/<pkg>.json | jq '.packages.\"polysource/<pkg>\" | map(.version)'"
echo
echo "Reminder: clear credentials from this shell session:"
echo "  unset PACKAGIST_TOKEN PACKAGIST_USER"

[[ $fail -eq 0 ]] || exit 1
