#!/usr/bin/env bash
# Smoke test (post-Packagist variant): verify that
# `composer require polysource/symfony-bundle` pulls the published
# packages from Packagist and boots cleanly on a vanilla Symfony 7.4
# skeleton.
#
# Differs from `scripts/smoke-vanilla-symfony.sh` in one critical way:
# this script does NOT register path repositories — it requires composer
# to resolve everything through Packagist. That makes it the right
# test to run *after* a release tag, to prove a real consumer's
# experience.
#
# What it verifies:
#   1. Composer pulls polysource/symfony-bundle from Packagist (no path
#      repo, no minimum-stability hack)
#   2. Symfony Flex auto-registers PolysourceBundle in config/bundles.php
#   3. cache:clear succeeds (DI compiles)
#   4. debug:container exposes ≥ 15 Polysource services
#   5. The bundle boots WITHOUT polysource/filter (graceful degradation)
#   6. Adding polysource/filter on top does not break boot
#
# Usage: ./scripts/smoke-packagist.sh
# Optional: VERSION_CONSTRAINT=^0.1 ./scripts/smoke-packagist.sh
# Requires: docker compose (the project's PHP container is reused).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SMOKE_DIR="${SMOKE_DIR:-/tmp/polysource-smoke-packagist-$$}"
DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker compose}"
# Default: caret on the latest tag, so the smoke always exercises the
# release that was just published (a hardcoded default drifted to ^0.1
# once and silently smoked the wrong lineage — never again).
LATEST_TAG="$(git -C "$REPO_ROOT" describe --tags --abbrev=0 2>/dev/null | sed 's/^v//')"
VERSION_CONSTRAINT="${VERSION_CONSTRAINT:-^${LATEST_TAG:-1.0}}"

cleanup() {
    rm -rf "$SMOKE_DIR"
}
trap cleanup EXIT

run_in_container() {
    $DOCKER_COMPOSE -f "$REPO_ROOT/docker-compose.yml" run --rm \
        -v "$SMOKE_DIR:/smoke" \
        -w /smoke \
        php \
        sh -c "$1"
}

mkdir -p "$SMOKE_DIR"

echo "=================================================="
echo "  Smoke test (Packagist) — constraint: $VERSION_CONSTRAINT"
echo "  Workdir: $SMOKE_DIR"
echo "=================================================="

echo
echo "=== [1/5] Bootstrap vanilla Symfony 7.4 skeleton ==="
run_in_container '
    composer create-project symfony/skeleton:^7.4 . --no-interaction --no-progress 2>&1 | tail -3
'

echo
echo "=== [2/5] composer require polysource/symfony-bundle (from Packagist) ==="
run_in_container "
    composer require 'polysource/symfony-bundle:${VERSION_CONSTRAINT}' --no-interaction --no-progress 2>&1 | tail -15
"

echo
echo "=== [3/5] PolysourceBundle auto-registered + cache:clear + DI compiles ==="
run_in_container '
    grep -q "Polysource\\\\Bundle\\\\PolysourceBundle" config/bundles.php \
        || { echo "FAIL: PolysourceBundle not in bundles.php"; exit 1; }
    echo "OK: PolysourceBundle in config/bundles.php"

    php bin/console cache:clear --env=dev 2>&1 | tail -2

    SERVICES=$(php bin/console debug:container --env=dev 2>&1 | grep -ci "polysource")
    [ "$SERVICES" -ge 15 ] || { echo "FAIL: expected ≥15 polysource services, got $SERVICES"; exit 1; }
    echo "OK: $SERVICES polysource DI services registered"
'

echo
echo "=== [4/5] Graceful degradation: probe Twig fn without polysource/filter ==="
run_in_container '
    TWIG_OUT=$(php bin/console debug:twig 2>&1)
    echo "$TWIG_OUT" | grep -q "polysource_saved_views_supported" \
        || { echo "FAIL: probe Twig fn missing"; exit 1; }
    echo "OK: polysource_saved_views_supported probe present"
'

echo
echo "=== [5/5] Add polysource/filter on top, verify co-existence ==="
run_in_container "
    composer require 'polysource/filter:${VERSION_CONSTRAINT}' --no-interaction --no-progress 2>&1 | tail -3
    grep -q 'Polysource\\\\Filter\\\\PolysourceFilterBundle' config/bundles.php \
        || { echo 'FAIL: PolysourceFilterBundle not registered'; exit 1; }
    php bin/console cache:clear --env=dev 2>&1 | tail -2
    echo 'OK: both bundles boot together'
"

echo
echo "=================================================="
echo "  ✓ Smoke test (Packagist) PASS"
echo "  Constraint tested: $VERSION_CONSTRAINT"
echo "  - polysource/symfony-bundle installs from Packagist"
echo "  - PolysourceBundle auto-registered"
echo "  - ≥15 DI services compiled"
echo "  - Graceful-degradation Twig probe present"
echo "  - polysource/filter co-exists when added on top"
echo "=================================================="
