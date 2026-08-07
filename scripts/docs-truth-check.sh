#!/usr/bin/env bash
#
# Docs truth-sync gate — catches the mechanical classes of doc drift
# that accumulated between v0.5.7 and v1.1.0 (stale status banners,
# version constants, branch-aliases, screenshot/ADR counts).
#
# Anchored on Polysource::VERSION (packages/core/src/Polysource.php):
# during release prep the constant is bumped first, then every check
# below must agree with it BEFORE the tag is pushed.
#
# Run via `make docs-check`. Wired into `make ci` and the CI
# `validate` job; step 2 of docs/maintainers/release-and-split.md.

set -euo pipefail
cd "$(dirname "$0")/.."

fail=0
err() { printf '✗ %s\n' "$1"; fail=1; }
ok()  { printf '✓ %s\n' "$1"; }

# --- Anchor: the version constant -----------------------------------
CONST_VERSION=$(sed -n "s/.*VERSION = '\([0-9][0-9.]*\)'.*/\1/p" packages/core/src/Polysource.php)
if [ -z "$CONST_VERSION" ]; then
    err "Polysource::VERSION not found or not a plain X.Y.Z string"
    exit 1
fi

TAG=$(git describe --tags --abbrev=0 2>/dev/null || true)
TAG_VERSION=${TAG#v}
if [ -n "$TAG_VERSION" ]; then
    # The constant must be the latest tag, or ahead of it (release prep).
    HIGHEST=$(printf '%s\n%s\n' "$TAG_VERSION" "$CONST_VERSION" | sort -V | tail -1)
    if [ "$HIGHEST" != "$CONST_VERSION" ]; then
        err "Polysource::VERSION ($CONST_VERSION) is behind the latest tag ($TAG_VERSION)"
    else
        ok "Polysource::VERSION = $CONST_VERSION (latest tag: ${TAG_VERSION:-none})"
    fi
fi

MAJOR=${CONST_VERSION%%.*}
MINOR_REST=${CONST_VERSION#*.}
MINOR=${MINOR_REST%%.*}
EXPECTED_ALIAS="${MAJOR}.$((MINOR + 1)).x-dev"

# --- 1. branch-alias lineage in every composer.json -----------------
BAD_ALIAS=$(grep -l '"branch-alias"' composer.json packages/*/composer.json \
    | xargs grep -L "\"dev-main\": \"${EXPECTED_ALIAS}\"" || true)
if [ -n "$BAD_ALIAS" ]; then
    err "branch-alias must be ${EXPECTED_ALIAS} (dev-main) in: $(echo "$BAD_ALIAS" | tr '\n' ' ')"
else
    ok "branch-alias dev-main = ${EXPECTED_ALIAS} in root + 16 packages"
fi

STALE_DEV=$(grep -rn --include='composer.json' -e '"0\.1\.x-dev"' . \
    --exclude-dir=vendor --exclude-dir=var --exclude-dir=node_modules || true)
if [ -n "$STALE_DEV" ]; then
    err "stale 0.1.x-dev pins remain: $(echo "$STALE_DEV" | head -3)"
else
    ok "no stale 0.1.x-dev pins in composer manifests"
fi

# --- 2. Current version stated where users look ---------------------
grep -q "\*\*v${CONST_VERSION}\*\*" README.md \
    && ok "README.md status states v${CONST_VERSION}" \
    || err "README.md status section does not state **v${CONST_VERSION}**"

grep -q "^## \[${CONST_VERSION}\]" CHANGELOG.md \
    && ok "CHANGELOG.md has a [${CONST_VERSION}] section" \
    || err "CHANGELOG.md has no ## [${CONST_VERSION}] section"

grep -q "v${CONST_VERSION}" docs/README.md \
    && ok "docs/README.md mentions v${CONST_VERSION}" \
    || err "docs/README.md does not mention v${CONST_VERSION}"

# --- 3. Pre-1.0 status language must not resurface ------------------
# (CHANGELOG and upgrade guides legitimately quote history.)
STALE_LANG=$(grep -rln --include='*.md' \
    -e 'release-candidate stable' \
    -e 'breaking changes between minors are allowed' \
    README.md CONTRIBUTING.md SECURITY.md ROADMAP.md docs examples packages 2>/dev/null \
    | grep -v -e '^CHANGELOG.md$' -e '/upgrade/' || true)
if [ -n "$STALE_LANG" ]; then
    err "pre-1.0 status language found in: $(echo "$STALE_LANG" | tr '\n' ' ')"
else
    ok "no pre-1.0 status language outside historical files"
fi

# --- 4. ADR index count ---------------------------------------------
ADR_COUNT=$(ls docs/adr/0*.md | wc -l | tr -d ' ')
grep -q "${ADR_COUNT} ADRs" docs/README.md \
    && ok "docs/README.md ADR count matches disk (${ADR_COUNT})" \
    || err "docs/README.md ADR count out of sync (disk has ${ADR_COUNT} ADR files)"

# --- 5. Package count -----------------------------------------------
PKG_COUNT=$(ls -d packages/*/ | wc -l | tr -d ' ')
grep -q "${PKG_COUNT} packages" README.md \
    && ok "README.md package count matches disk (${PKG_COUNT})" \
    || err "README.md package count out of sync (disk has ${PKG_COUNT} packages)"

# --- 6. Screenshot pipeline vs published docs -----------------------
SHOT_STEPS=$(grep -c "'slug' =>" examples/showcase-demo/src/Command/ShowcaseScreenshotsCommand.php)
SHOT_FILES=$(ls docs/user/screenshots/*.png | wc -l | tr -d ' ')
if [ "$SHOT_STEPS" != "$SHOT_FILES" ]; then
    err "screenshot drift: command defines ${SHOT_STEPS} captures, docs/user/screenshots has ${SHOT_FILES} PNGs (run 'make showcase-screenshots')"
else
    ok "screenshot pipeline (${SHOT_STEPS} steps) matches published PNGs (${SHOT_FILES})"
fi

MISSING_SHOTS=$(grep -o 'screenshots/[0-9a-z-]*\.png' docs/user/showcase-tour.md | sort -u \
    | while read -r p; do [ -f "docs/user/$p" ] || echo "$p"; done)
if [ -n "$MISSING_SHOTS" ]; then
    err "showcase-tour.md references missing screenshots: $(echo "$MISSING_SHOTS" | tr '\n' ' ')"
else
    ok "every screenshot referenced by showcase-tour.md exists"
fi

# --------------------------------------------------------------------
if [ "$fail" -ne 0 ]; then
    echo
    echo "Docs are out of sync with the code. Fix the items above —"
    echo "the truth lives in the code; update the docs to match it"
    echo "(or bump Polysource::VERSION first when preparing a release)."
    exit 1
fi
echo
echo "docs-truth-check: all green"
