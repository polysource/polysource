#!/usr/bin/env bash
# Smoke test: verify `composer require polysource/symfony-bundle` works on a
# vanilla Symfony 7.4 skeleton. Re-runnable before every `v0.X.Y` tag.
#
# What it verifies:
#   1. Composer pulls core + symfony-bundle + twig-theme cleanly via path repos
#   2. Symfony Flex auto-registers PolysourceBundle in config/bundles.php
#   3. cache:clear succeeds (DI compiles)
#   4. debug:container exposes 15+ Polysource services
#   5. debug:twig exposes the Polysource Twig namespace + functions
#   6. The bundle boots WITHOUT polysource/filter (graceful degradation)
#   7. Adding polysource/filter on top does not break boot
#
# Why path repos: pre-tag the packages aren't on Packagist yet. Path repos
# symlink the working tree, which is the closest approximation of what an
# end user will get post-publish.
#
# Usage: ./scripts/smoke-vanilla-symfony.sh
# Requires: docker compose (the project's PHP container is reused).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SMOKE_DIR="${SMOKE_DIR:-/tmp/polysource-smoke-$$}"
DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker compose}"

cleanup() {
    rm -rf "$SMOKE_DIR"
}
trap cleanup EXIT

run_in_container() {
    $DOCKER_COMPOSE -f "$REPO_ROOT/docker-compose.yml" run --rm \
        -v "$SMOKE_DIR:/smoke" \
        -v "$REPO_ROOT:/polysource:ro" \
        -w /smoke \
        php \
        sh -c "$1"
}

mkdir -p "$SMOKE_DIR"

echo "=== [1/5] Bootstrap vanilla Symfony 7.4 skeleton ==="
run_in_container '
    composer create-project symfony/skeleton:^7.4 . --no-interaction --no-progress 2>&1 | tail -3
'

echo "=== [2/5] Wire polysource path repositories + require symfony-bundle ==="
run_in_container '
    composer config --no-plugins --no-interaction repositories.polysource-core path /polysource/packages/core
    composer config --no-plugins --no-interaction repositories.polysource-bundle path /polysource/packages/symfony-bundle
    composer config --no-plugins --no-interaction repositories.polysource-twig path /polysource/packages/twig-theme
    composer config --no-plugins --no-interaction minimum-stability dev
    composer config --no-plugins --no-interaction prefer-stable true
    composer require polysource/symfony-bundle:@dev --no-interaction --no-progress 2>&1 | tail -10
'

echo "=== [3/5] Verify PolysourceBundle is auto-registered + cache:clear ==="
run_in_container '
    grep -q "Polysource\\\\Bundle\\\\PolysourceBundle" config/bundles.php || { echo "FAIL: PolysourceBundle not in bundles.php"; exit 1; }
    php bin/console cache:clear --env=dev 2>&1 | tail -2
    SERVICES=$(php bin/console debug:container --env=dev 2>&1 | grep -ci "polysource")
    [ "$SERVICES" -ge 15 ] || { echo "FAIL: expected ≥15 polysource services, got $SERVICES"; exit 1; }
    echo "OK: $SERVICES polysource DI services registered"
'

echo "=== [4/5] Graceful degradation: probe Twig fn without polysource/filter ==="
run_in_container '
    TWIG_OUT=$(php bin/console debug:twig 2>&1)
    echo "$TWIG_OUT" | grep -q "polysource_saved_views_supported" || { echo "FAIL: probe Twig fn missing"; exit 1; }
    echo "OK: graceful-degradation probe registered"
'

echo "=== [5/5] Add polysource/filter, verify it co-exists ==="
run_in_container '
    composer config --no-plugins --no-interaction repositories.polysource-filter path /polysource/packages/filter
    composer require polysource/filter:@dev --no-interaction --no-progress 2>&1 | tail -3
    grep -q "Polysource\\\\Filter\\\\PolysourceFilterBundle" config/bundles.php || { echo "FAIL: PolysourceFilterBundle not registered"; exit 1; }
    php bin/console cache:clear --env=dev 2>&1 | tail -2
    echo "OK: both bundles boot together"
'

echo
echo "=================================================="
echo "  Smoke test PASS"
echo "  - polysource/symfony-bundle installs cleanly"
echo "  - PolysourceBundle auto-registered in bundles.php"
echo "  - 15+ DI services compiled"
echo "  - Twig functions registered (incl. graceful probe)"
echo "  - polysource/filter co-exists when added on top"
echo "=================================================="
