#!/usr/bin/env bash
# Release script for Polysource.
#
# What it does:
#   1. Sanity gates: clean tree, on main, no unpushed commits, CI green
#   2. Verifies CHANGELOG.md has a populated [VERSION] block
#   3. Creates an annotated git tag `v<VERSION>` at HEAD
#   4. Pushes the tag to `origin`
#   5. Prints copy-pastable instructions for the 16 Packagist submissions
#      (Polysource is monorepo-split-free for v0.1 — each package needs
#      to be submitted manually the first time; subsequent versions
#      are picked up automatically by the GitHub webhook once configured)
#
# What it does NOT do:
#   - It does not push to Packagist directly. The first version of each
#     package must be manually submitted at
#     https://packagist.org/packages/submit because Packagist deduplicates
#     by repo URL and our 16 packages share a single repo URL — only a
#     human can confirm "yes, this is the same monorepo as the 15 other
#     listings, please add this 16th one".
#   - It does not draft GitHub release notes — copy the [VERSION] block
#     from CHANGELOG.md after the tag pushes (a future GH Action can
#     automate this, tracked as a help-wanted issue).
#
# Usage:
#   ./scripts/release.sh 0.1.0           # actually does it
#   ./scripts/release.sh 0.1.0 --dry-run # prints what it would do
#
# Run from the repo root.

set -euo pipefail

VERSION="${1:-}"
DRY_RUN=false
if [[ "${2:-}" == "--dry-run" ]]; then
    DRY_RUN=true
fi

if [[ -z "$VERSION" ]]; then
    echo "Usage: $0 <version> [--dry-run]" >&2
    echo "Example: $0 0.1.0" >&2
    exit 1
fi

# Reject leading "v" — the tag will be `v$VERSION`, but the bare version
# is what shows up in CHANGELOG and on Packagist.
if [[ "$VERSION" == v* ]]; then
    echo "ERROR: pass the version without the 'v' prefix (got '$VERSION')." >&2
    echo "       The tag will become 'v$VERSION' automatically." >&2
    exit 1
fi

TAG="v$VERSION"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

run() {
    if $DRY_RUN; then
        echo "[dry-run] $*"
    else
        eval "$@"
    fi
}

PACKAGES=(
    polysource/core
    polysource/symfony-bundle
    polysource/twig-theme
    polysource/filter
    polysource/easyadmin-filter-bridge
    polysource/audit
    polysource/widgets
    polysource/search
    polysource/workflow-bridge
    polysource/bulk-async
    polysource/adapter-messenger
    polysource/adapter-doctrine
    polysource/adapter-redis
    polysource/adapter-flysystem
    polysource/adapter-http
    polysource/adapter-meilisearch
)

# ---------------------------------------------------------------------
# Gate 1 — working tree is clean
# ---------------------------------------------------------------------
echo "[1/6] Working tree clean?"
if [[ -n "$(git status --porcelain)" ]]; then
    echo "  FAIL: uncommitted changes:" >&2
    git status --short >&2
    exit 1
fi
echo "  OK"

# ---------------------------------------------------------------------
# Gate 2 — on main, up to date with origin
# ---------------------------------------------------------------------
echo "[2/6] On main + up-to-date with origin?"
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$BRANCH" != "main" ]]; then
    echo "  FAIL: current branch is '$BRANCH' (expected 'main')" >&2
    exit 1
fi
git fetch origin main --quiet
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
if [[ "$LOCAL" != "$REMOTE" ]]; then
    echo "  FAIL: local main ($LOCAL) ≠ origin/main ($REMOTE) — push first" >&2
    exit 1
fi
echo "  OK ($LOCAL)"

# ---------------------------------------------------------------------
# Gate 3 — tag does not already exist
# ---------------------------------------------------------------------
echo "[3/6] Tag $TAG free?"
if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
    echo "  FAIL: tag $TAG already exists locally" >&2
    exit 1
fi
if git ls-remote --tags origin "refs/tags/$TAG" 2>/dev/null | grep -q "$TAG"; then
    echo "  FAIL: tag $TAG already exists on origin" >&2
    exit 1
fi
echo "  OK"

# ---------------------------------------------------------------------
# Gate 4 — CHANGELOG has a populated block for this version
# ---------------------------------------------------------------------
echo "[4/6] CHANGELOG.md has a [$VERSION] block?"
if ! grep -q "^## \[$VERSION\]" CHANGELOG.md; then
    echo "  FAIL: no '## [$VERSION]' header found in CHANGELOG.md" >&2
    echo "        update the changelog before tagging" >&2
    exit 1
fi
# Capture the block so we can use it as the tag annotation.
ANNOTATION=$(awk -v v="$VERSION" '
    /^## \[/ {
        if (capturing) exit
        if ($0 ~ "^## \\[" v "\\]") { capturing = 1; print; next }
    }
    capturing { print }
' CHANGELOG.md)
if [[ -z "$ANNOTATION" ]]; then
    echo "  FAIL: extracted empty annotation from CHANGELOG.md [$VERSION] block" >&2
    exit 1
fi
echo "  OK ($(echo "$ANNOTATION" | wc -l | tr -d ' ') lines extracted)"

# ---------------------------------------------------------------------
# Gate 5 — local CI passes
# ---------------------------------------------------------------------
echo "[5/6] Local CI green? (composer validate, cs-check, phpstan, tests, coverage)"
if $DRY_RUN; then
    echo "  [dry-run] would run: make ci"
else
    if ! make ci > /tmp/release-ci.log 2>&1; then
        echo "  FAIL: make ci failed — see /tmp/release-ci.log" >&2
        tail -20 /tmp/release-ci.log >&2
        exit 1
    fi
fi
echo "  OK"

# ---------------------------------------------------------------------
# Gate 6 — vanilla Sf 7.4 smoke test passes
# ---------------------------------------------------------------------
echo "[6/6] Vanilla Sf 7.4 smoke test passes?"
if $DRY_RUN; then
    echo "  [dry-run] would run: ./scripts/smoke-vanilla-symfony.sh"
else
    if ! ./scripts/smoke-vanilla-symfony.sh > /tmp/release-smoke.log 2>&1; then
        echo "  FAIL: smoke test failed — see /tmp/release-smoke.log" >&2
        tail -20 /tmp/release-smoke.log >&2
        exit 1
    fi
fi
echo "  OK"

# ---------------------------------------------------------------------
# Tag + push
# ---------------------------------------------------------------------
echo
echo "All gates passed. Creating + pushing tag $TAG."
echo

ANNOTATION_FILE=$(mktemp)
trap 'rm -f "$ANNOTATION_FILE"' EXIT
echo "$ANNOTATION" > "$ANNOTATION_FILE"

run "git tag -a '$TAG' -F '$ANNOTATION_FILE'"
run "git push origin '$TAG'"

# ---------------------------------------------------------------------
# Packagist instructions
# ---------------------------------------------------------------------
cat <<EOF

============================================================
  Tag $TAG pushed.
============================================================

Next steps — Packagist registration (one-time per package):

For each of the 16 packages, go to
  https://packagist.org/packages/submit
and submit the SAME repo URL:
  https://github.com/polysource/polysource

Packagist will detect the package name from each composer.json.
You will register them in this order to respect the dependency
graph (no functional reason — Packagist accepts them in any order
— but registering core first means subsequent submissions get a
"Required by" link automatically):

EOF

i=1
for pkg in "${PACKAGES[@]}"; do
    printf "  %2d. %s\n" "$i" "$pkg"
    i=$((i+1))
done

cat <<EOF

After registration:
  - Configure the GitHub webhook on the repo
    (Settings > Webhooks > Add webhook), using the URL Packagist
    gives you. From v0.1.1 onwards, every tag push will publish
    automatically to all 16 packages.
  - Draft the GitHub release at
    https://github.com/polysource/polysource/releases/new
    and paste the [$VERSION] block from CHANGELOG.md as the body.
  - Post the 2 launch announcements (see scripts/launch-notes/).

EOF