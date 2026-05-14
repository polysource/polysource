#!/usr/bin/env bash
# Smoke test (post-Packagist, bridge-alone variant): verify that
# `composer require polysource/easyadmin-filter-bridge` ALONE pulls
# the bridge + its transitive deps from Packagist, boots cleanly on
# a vanilla Symfony skeleton with EasyAdmin installed, and that the
# bridge's prepended Twig templates COMPILE without symfony-bundle
# being present.
#
# Why this script exists:
#   v0.1.1 shipped a critical install blocker — the bridge's
#   auto-prepended `crud/index.html.twig` referenced a Twig function
#   (`saved_views_dropdown`) owned by `polysource/symfony-bundle`,
#   which is NOT a dep of the bridge. Twig resolves function names
#   at PARSE time, independently of `{% if %}` guards, so the template
#   failed to compile on every EA index page with filters — across
#   the entire host app, not just controllers using bridge features.
#
#   The existing `smoke-packagist.sh` exercises the symfony-bundle
#   install path, which masks this bug because symfony-bundle's
#   PolysourceFilterExtension registers `saved_views_dropdown`. This
#   script targets the bridge-alone install path — the one that
#   actually broke for users.
#
# What it verifies:
#   1. Composer pulls polysource/easyadmin-filter-bridge alone from
#      Packagist. polysource/filter comes in as a transitive dep.
#      polysource/symfony-bundle does NOT.
#   2. Symfony Flex auto-registers both bundles in config/bundles.php.
#   3. cache:clear succeeds (DI compiles).
#   4. EasyAdmin is also installable alongside (the bridge's
#      documented target stack).
#   5. **lint:twig on the bridge's prepended templates parses without
#      error** — catches B2-style "Unknown function" parse failures.
#      `debug:twig --filter=saved_views_dropdown` proves the function
#      is registered (the function is owned by polysource/filter since
#      v0.1.4, transitive through the bridge).
#
# Note — runtime behavior is NOT exercised here:
#   The graceful-degradation contract (saved_views_dropdown returns
#   "" when SavedViewService is null) is unit-tested inside the
#   polysource/filter package itself (cf.
#   `tests/Unit/SavedView/Twig/SavedViewExtensionTest::renderDropdownReturnsEmptyWhenServiceIsNull`).
#   It cannot be cleanly retested here because EasyAdmin pulls
#   DoctrineBundle + SecurityBundle transitively, so the DI gate in
#   polysource/filter wires the REAL storage path — and rendering
#   the dropdown then requires either a working PDO driver + an
#   actual table, or a fresh-install error swallowing strategy that
#   isn't yet implemented (v0.1.5+ concern).
#
# History of step 6 (now removed):
#   Until v0.1.3 the script asserted the presence of
#   `polysource_saved_views_available()` — a runtime gate in the
#   bridge's ChipExtension. v0.1.4 moved ownership of
#   `saved_views_dropdown` to polysource/filter (a transitive dep of
#   the bridge), removed the v0.1.2 stub, and dropped the
#   `polysource_saved_views_available` gate entirely. A v0.1.4
#   attempt at re-rendering through a stub command failed because EA
#   pulls Doctrine/Security in, so the real storage path was hit
#   without a usable DB driver. The behavioral contract is unit-tested
#   upstream; the smoke focuses on the install-path B2 regression.
#
# Usage: ./scripts/smoke-packagist-bridge.sh
# Optional: VERSION_CONSTRAINT=^0.1 ./scripts/smoke-packagist-bridge.sh
# Requires: docker compose (the project's PHP container is reused).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SMOKE_DIR="${SMOKE_DIR:-/tmp/polysource-smoke-bridge-$$}"
DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker compose}"
VERSION_CONSTRAINT="${VERSION_CONSTRAINT:-^0.1}"

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
echo "  Smoke test (Packagist, bridge-alone) — $VERSION_CONSTRAINT"
echo "  Workdir: $SMOKE_DIR"
echo "=================================================="

echo
echo "=== [1/6] Bootstrap vanilla Symfony 7.4 skeleton ==="
run_in_container '
    composer create-project symfony/skeleton:^7.4 . --no-interaction --no-progress 2>&1 | tail -3
'

echo
echo "=== [2/6] composer require easycorp/easyadmin-bundle (the host stack) ==="
run_in_container '
    composer require easycorp/easyadmin-bundle:^5.0 --no-interaction --no-progress 2>&1 | tail -3
'

echo
echo "=== [3/6] composer require polysource/easyadmin-filter-bridge ALONE ==="
echo "    (no symfony-bundle, no manual polysource/filter — bridge pulls filter transitively)"
run_in_container "
    composer require 'polysource/easyadmin-filter-bridge:${VERSION_CONSTRAINT}' --no-interaction --no-progress 2>&1 | tail -10

    # Sanity check: symfony-bundle must NOT be installed (this script's whole point)
    if [ -d vendor/polysource/symfony-bundle ]; then
        echo 'FAIL: polysource/symfony-bundle was installed transitively — that defeats this scenario.'
        exit 1
    fi
    echo 'OK: polysource/symfony-bundle is absent (bridge-alone install path verified)'

    # Bridge + transitive deps must be there
    for pkg in core filter easyadmin-filter-bridge; do
        [ -d vendor/polysource/\$pkg ] || { echo \"FAIL: polysource/\$pkg missing\"; exit 1; }
    done
    echo 'OK: polysource/{core, filter, easyadmin-filter-bridge} installed'
"

echo
echo "=== [4/6] Both bundles auto-registered + cache:clear succeeds ==="
run_in_container '
    grep -q "PolysourceFilterBundle" config/bundles.php \
        || { echo "FAIL: PolysourceFilterBundle missing"; exit 1; }
    grep -q "PolysourceEasyAdminFilterBridgeBundle" config/bundles.php \
        || { echo "FAIL: PolysourceEasyAdminFilterBridgeBundle missing"; exit 1; }
    echo "OK: both bundles in config/bundles.php"

    php bin/console cache:clear --env=dev 2>&1 | tail -2
'

echo
echo "=== [5/6] Routes auto-register (regression guard for v0.5.4 fix) ==="
echo "    a fresh install must show the 8 polysource routes WITHOUT host action"
run_in_container '
    # Without the v0.5.4 Bundle::boot() auto-import, hosts had to
    # manually add `@PolysourceEasyAdminFilterBridge/config/routes.php`
    # to config/routes.yaml — silently breaking every helper URL.
    # Guard the regression here: 8 polysource_* routes MUST appear
    # in debug:router on a fresh install with no manual import.
    count=$(php bin/console debug:router 2>&1 | grep -c "^ *polysource_")
    if [ "$count" -lt 8 ]; then
        echo "FAIL: only $count polysource routes registered, expected >= 8."
        echo "      v0.5.4 Bundle::boot() auto-import must be working."
        php bin/console debug:router 2>&1 | grep polysource_ || true
        exit 1
    fi
    echo "OK: $count polysource routes auto-registered (v0.5.4 regression guarded)"
'

echo
echo "=== [6/6] lint:twig on bridge templates — B2 regression guard ==="
echo "    parses every template the bridge prepends into @EasyAdmin namespace"
run_in_container '
    # The bridge prepends Resources/views/ into the @EasyAdmin namespace.
    # If a template references an undefined Twig function (the v0.1.1 bug
    # for `saved_views_dropdown`), this lint step fails with
    # Twig\Error\SyntaxError before runtime ever hits it.
    php bin/console lint:twig vendor/polysource/easyadmin-filter-bridge/Resources/views/ 2>&1 | tail -5

    # Belt-and-braces: assert the function is registered (v0.1.4 owns
    # it in polysource/filter::SavedViewExtension, transitive via the
    # bridge — the v0.1.1 B2 bug would manifest as this being absent).
    php bin/console debug:twig --filter=saved_views_dropdown 2>&1 | grep -q "saved_views_dropdown" \
        || { echo "FAIL: saved_views_dropdown Twig function is not registered — B2 regression"; exit 1; }
    echo "OK: saved_views_dropdown registered (B2 regression guarded)"
'

echo
echo "=================================================="
echo "  ✓ Smoke test (Packagist, bridge-alone) PASS"
echo "=================================================="
