#!/usr/bin/env bash
# One-shot: split each polysource/* sub-package into its own GitHub
# repo so Packagist can register them individually. Used once at
# v0.1.0 launch to bootstrap the 16 split mirrors; future syncs go
# through the auto-split GitHub Action.
#
# What it does, per package:
#   1. Create an empty `polysource/<pkg>` GitHub repo if it doesn't
#      exist (description and topics seeded from the package's
#      composer.json).
#   2. Run `git subtree split --prefix=packages/<pkg> HEAD` to
#      produce a clean commit history that contains only that
#      package's files at the root.
#   3. Push that history to `polysource/<pkg>:refs/heads/main`.
#   4. Push the v0.1.0 tag at the same commit so Packagist can
#      register the version.
#
# Usage:
#   ./scripts/split-packages.sh           # split + push all 16 packages
#   ./scripts/split-packages.sh core      # split + push a single package
#   ./scripts/split-packages.sh --tag v0.1.1   # push a different tag
#
# Requires: gh CLI (authenticated), git 2.20+, network access.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

TAG="v0.1.0"
PACKAGES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tag)
            TAG="$2"
            shift 2
            ;;
        --tag=*)
            TAG="${1#*=}"
            shift
            ;;
        -h|--help)
            sed -n '2,/^$/p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            PACKAGES+=("$1")
            shift
            ;;
    esac
done

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

if [[ ${#PACKAGES[@]} -eq 0 ]]; then
    PACKAGES=("${ALL_PACKAGES[@]}")
fi

# Sanity: tag must exist locally.
if ! git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
    echo "ERROR: tag $TAG does not exist locally." >&2
    echo "       Run scripts/release.sh first." >&2
    exit 1
fi

TAG_SHA=$(git rev-parse "refs/tags/$TAG^{commit}")

ensure_repo() {
    local pkg="$1"
    local description
    description=$(jq -r '.description // "Polysource — " + .name' "packages/$pkg/composer.json" 2>/dev/null \
        || echo "Polysource — $pkg")

    if gh repo view "polysource/$pkg" >/dev/null 2>&1; then
        echo "  · github.com/polysource/$pkg already exists, skipping create"
    else
        echo "  · creating github.com/polysource/$pkg"
        gh repo create "polysource/$pkg" \
            --public \
            --description "$description" \
            --homepage "https://github.com/polysource/polysource" \
            >/dev/null
        # Topics for discoverability (mirror the monorepo's set
        # narrowed to the package's domain).
        gh repo edit "polysource/$pkg" \
            --add-topic "polysource,symfony,php,php8,$(echo "$pkg" | tr '-' ',')" \
            >/dev/null 2>&1 || true
    fi
}

split_and_push() {
    local pkg="$1"
    local remote="git@github.com:polysource/$pkg.git"

    echo "  · git subtree split --prefix=packages/$pkg $TAG"
    local split_sha
    split_sha=$(git subtree split --prefix="packages/$pkg" "$TAG_SHA" 2>/dev/null | tail -1)

    if [[ -z "$split_sha" ]]; then
        echo "  ! split produced no SHA — aborting" >&2
        return 1
    fi
    echo "  · split SHA: $split_sha"

    echo "  · pushing to $remote main + $TAG"
    # Force-push main is OK for split mirrors — they're read-only
    # downstream consumers.
    git push --force "$remote" "$split_sha:refs/heads/main"
    git push --force "$remote" "$split_sha:refs/tags/$TAG"
}

echo "============================================================"
echo "  polysource subtree split — tag $TAG ($TAG_SHA)"
echo "  Packages: ${PACKAGES[*]}"
echo "============================================================"

for pkg in "${PACKAGES[@]}"; do
    if [[ ! -d "packages/$pkg" ]]; then
        echo "WARNING: packages/$pkg does not exist, skipping" >&2
        continue
    fi

    echo
    echo "[$pkg]"
    ensure_repo "$pkg"
    split_and_push "$pkg"
done

echo
echo "============================================================"
echo "  All splits pushed."
echo "============================================================"
echo
echo "Next step — Packagist registration. For each package, go to"
echo "  https://packagist.org/packages/submit"
echo "and submit:"
echo

for pkg in "${PACKAGES[@]}"; do
    echo "  https://github.com/polysource/$pkg"
done
