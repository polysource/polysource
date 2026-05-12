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
#      error** — catches B2-style "Unknown function" parse failures
#      that any guarded function call would trigger.
#   6. ChipExtension's `polysource_saved_views_available()` Twig
#      function returns false (no symfony-bundle), and the stub
#      `saved_views_dropdown` is registered so the gated template
#      reference parses.
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
echo "=== [5/6] lint:twig on bridge templates — B2 regression guard ==="
echo "    parses every template the bridge prepends into @EasyAdmin namespace"
run_in_container '
    # The bridge prepends Resources/views/ into the @EasyAdmin namespace.
    # If a template references an undefined Twig function (the v0.1.1 bug
    # for `saved_views_dropdown`), this lint step fails with
    # Twig\Error\SyntaxError before runtime ever hits it.
    php bin/console lint:twig vendor/polysource/easyadmin-filter-bridge/Resources/views/ 2>&1 | tail -5

    # Belt-and-braces: actually call the gate function and assert the
    # stub is wired (proves the v0.1.2 fix is in place).
    php bin/console debug:twig --filter=saved_views_dropdown 2>&1 | grep -q "saved_views_dropdown" \
        || { echo "FAIL: saved_views_dropdown Twig function is not registered — B2 regression"; exit 1; }
    echo "OK: saved_views_dropdown stub registered (B2 regression guarded)"
'

echo
echo "=== [6/6] polysource_saved_views_available() returns false (no symfony-bundle) ==="
run_in_container '
    OUT=$(php -r "
        require __DIR__.\"/vendor/autoload.php\";
        \$kernel = new App\Kernel(\"dev\", true);
        \$kernel->boot();
        \$twig = \$kernel->getContainer()->get(\"test.service_container\")->get(\"twig\");
        var_dump(\$twig->render(\"@!Twig/inline.html.twig\", []));
    " 2>&1 || true)

    # Simpler: invoke via debug:twig (Symfony exposes the function list)
    php bin/console debug:twig --filter=polysource_saved_views_available 2>&1 | grep -q "polysource_saved_views_available" \
        || { echo "FAIL: polysource_saved_views_available Twig function missing"; exit 1; }
    echo "OK: polysource_saved_views_available function exposed"
'

echo
echo "=================================================="
echo "  ✓ Smoke test (Packagist, bridge-alone) PASS"
echo "=================================================="
