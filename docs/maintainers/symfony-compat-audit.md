# Symfony compatibility audit — what we advertise vs what we test

> **Audience:** Polysource maintainers + reviewers triaging
> "does this work on Sf X?" issues. Not a user-facing doc — for
> hosts, see [installation.md](../user/installation.md).
>
> **Last audited:** 2026-05-15 (post v0.5.7, mid-v0.6.x cycle)

## TL;DR

| Symfony minor | Advertised by | Composer-validated | Smoke-tested | Test-matrix-gated | Status |
|---|---|---|---|---|---|
| **5.4 LTS** | `filter`, `bridge`, `widgets`, `search`, `bulk-async`, `workflow-bridge`, `adapter-redis`, `adapter-meilisearch` | ✓ | ✓ (PR #44) | ✗ | **Floor-supported** |
| **6.0–6.3** | same set | ✓ | ✗ | ✗ | **Best-effort** (composer-validated only, EOL'd upstream) |
| **6.4 LTS** | all 14 packages | ✓ | ✓ | ✓ (PHP 8.1+8.2) | **First-class** |
| **7.0–7.1** | all 14 | ✓ | ✗ | ✗ | **Best-effort** (EOL'd upstream) |
| **7.2** | all 14 | ✓ | ✗ | ✓ (PHP 8.3) | **First-class** (non-LTS proof) |
| **7.3** | all 14 | ✓ | ✗ | ✗ | **Best-effort** |
| **7.4 LTS** | all 14 | ✓ | ✓ | ✓ (PHP 8.3+8.4) | **First-class** |
| **8.0** | all 14 | ✓ | ✗ | ✗ | **Forward-compat aspiration** (Sf 8.0 not released yet) |

**Definitions:**
- **Advertised** — listed in `composer.json` require constraints
- **Composer-validated** — `composer validate --strict` + the install resolver accepts the version (no API call made)
- **Smoke-tested** — one of the `scripts/smoke-*.sh` boots a vanilla skeleton against the version
- **Test-matrix-gated** — the CI PHPUnit matrix runs the full test suite against the version

## Why these tiers exist

Symfony's release calendar runs 2 minors per year. Polysource targets:

1. **All active LTS lines** (5.4, 6.4, 7.4) — first-class
2. **The latest non-LTS** (currently 7.2) — first-class proof that non-LTS combos work
3. **One EOL'd LTS** (5.4 — EOL'd Nov 2025 but still 9% of the EA install base per Packagist data) — floor-supported, smoke-only, with the explicit caveat in [installation.md](../user/installation.md) that hosts on EOL'd Symfony don't get security backports from Symfony itself
4. **Forward-compat to the next major** (8.0) — composer constraint allowed, no test infrastructure yet

The Sf 5.4 floor-support decision is documented in
[ADR-015 — Baseline multi-version support](../adr/0015-baseline-multi-version-support.md).

## Package-level constraint inventory

Not every package advertises the wide `^5.4 || ^6.0 || ^7.0 || ^8.0`
matrix. Two tiers within the monorepo:

### Wide matrix (Sf 5.4 floor)

These packages use only stable APIs available since Sf 5.4 and
advertise `^5.4 || ^6.0 || ^7.0 || ^8.0`:

| Package | Why floor 5.4 |
|---|---|
| `polysource/core` | Pure PHP, no Symfony APIs |
| `polysource/filter` | Form types + DI; all 5.4 APIs |
| `polysource/easyadmin-filter-bridge` | EA 4.24 is itself Sf 5.4-compatible |
| `polysource/widgets` | Twig + DI; no Sf 6+ APIs used |
| `polysource/search` | Routing + DI; no Sf 6+ APIs |
| `polysource/bulk-async` | Messenger 5.4 API; no `Bridge*` helpers |
| `polysource/workflow-bridge` | Workflow component 5.4 API |
| `polysource/adapter-redis` | DI-only |
| `polysource/adapter-meilisearch` | DI-only |
| `polysource/twig-theme` | Twig 3.x + templates; no Sf 6+ helpers |

### Sf 6.4 floor

These packages use APIs introduced in Sf 6.2+ and advertise
`^6.4 || ^7.0 || ^8.0`:

| Package | Symfony 6.2+ API used | Why we can't downgrade |
|---|---|---|
| `polysource/symfony-bundle` | `ValueResolverInterface` (Sf 6.2) | The meta-bundle's controller `#[MapRequestPayload]` integration; rewriting for `ArgumentValueResolverInterface` (deprecated path) is busywork that defeats the v1.0 contract surface |
| `polysource/adapter-messenger` | `SecurityBundle\Security` (renamed Sf 6.2) | Used in the failed-message retry/delete actions for permission gating |
| `polysource/audit` | Same Security namespace | Same gating use case |
| `polysource/adapter-doctrine` | Doctrine ORM 2.16+ which itself requires `symfony/cache` ^6 | Doctrine's own floor |
| `polysource/adapter-flysystem` | League Flysystem 3.x + Symfony 6 helpers | Library floor |
| `polysource/adapter-http` | Symfony HttpClient 6.4 streaming API | Used by adapter pagination |

**Implication:** an app installing `polysource/symfony-bundle` cannot
target Sf 5.4 — the whole meta-bundle's floor is Sf 6.4. An app
installing only `polysource/filter + polysource/easyadmin-filter-bridge`
(the bridge-only path) CAN target Sf 5.4.

## CI matrix breakdown

From `.github/workflows/ci.yml::test`:

```
Row 1: PHP 8.1 × Sf 6.4 LTS × EA 4.24   (legacy floor)
Row 2: PHP 8.2 × Sf 6.4 LTS × EA 4.24   (mainstream)
Row 3: PHP 8.2 × Sf 6.4 LTS × EA 5.0    (bridge crossover)
Row 4: PHP 8.3 × Sf 7.2     × EA 5.0    (non-LTS proof, Sf 7.2 EOL'd)
Row 5: PHP 8.3 × Sf 7.4 LTS × EA 5.0    (modern)
Row 6: PHP 8.4 × Sf 7.4 LTS × EA 5.0    (bleeding)
```

Plus:
- `sf54-floor` smoke job (PR #44) — installs filter + bridge on Sf 5.4
- `smoke-packagist` (manual) — vanilla Sf 7.4 + full meta-bundle
- `smoke-packagist-bridge` (manual) — vanilla Sf 7.4 + bridge alone

**Why 6 rows:** cartesian-product matrices generate impossible combos
(PHP 8.1 × Sf 7.4 is impossible — Sf 7 needs PHP 8.2+). Hand-listed
each row keeps the matrix to ~12min total CI time and prevents
"random" failures from impossible combinations.

## Forward-compat: Sf 8.0

Composer constraints include `|| ^8.0` on every Polysource package
that pins Symfony. Reasoning:

- Sf 8.0 isn't released yet (per Symfony's published roadmap, expected
  late 2027). Adding it to constraints today means installs against
  alpha/beta Sf 8 won't artificially break on resolver constraints.
- No Sf 8.0 CI matrix row exists. When Sf 8.0 alpha ships, add a
  `sf80-alpha` smoke job (mirror of the Sf 5.4 floor pattern in PR #44).
- Deprecation removals in Sf 8.0 may break Polysource. Track via the
  Symfony deprecation report (`symfony/phpunit-bridge` already emits
  these — failures will surface in CI once we add the row).

**Action items for Sf 8.0 readiness:**

- [ ] Once Sf 8.0 alpha ships: add `sf80-alpha` smoke job to CI
- [ ] Audit Sf 7.x deprecations using `symfony/phpunit-bridge` baseline
- [ ] Replace any `Symfony\Component\X\LegacyApi` usage flagged as
      removed-in-8.0

## Forward-compat: PHP 8.5

Same shape as Sf 8.0:

- All packages advertise `php: ">=8.1"` (no upper bound)
- CI tests PHP 8.1 / 8.2 / 8.3 / 8.4
- PHP 8.5 (expected Nov 2026) not yet in CI
- When PHP 8.5 ships, add a row to the matrix

## Doctrine ORM 2.x vs 3.x compat

Orthogonal to Symfony, but worth documenting here:

| Doctrine ORM | Advertised by | Tested |
|---|---|---|
| **2.16+** | `polysource/adapter-doctrine`, `bridge` | ✓ (current CI default) |
| **3.x** | same | ✓ (composer minor variation tested locally; not gated in CI) |

The ORM 2.x quirks (HYDRATE_ARRAY + entity select yielding 0 rows;
`toIterable` streaming differences) are documented in
[test-kernel-patterns.md](./test-kernel-patterns.md#6-doctrine-orm-2x-quirk--hydrate_array--entity-select).

**Gap:** no CI matrix row for ORM 3.x. Adding one is cheap (add
`doctrine: '^3.0'` to the existing matrix) and would catch ORM
2-vs-3 drift. **Recommended for v0.7.x.**

## EasyAdmin compat

Polysource's bridge advertises `^4.24 || ^5.0`. Tested combinations:

| EA version | PHP × Sf rows that test it |
|---|---|
| **4.24+** | PHP 8.1+8.2 × Sf 6.4 |
| **5.0+** | PHP 8.2 × Sf 6.4, PHP 8.3 × Sf 7.2+7.4, PHP 8.4 × Sf 7.4 |

The EA 4.x → 5.x transition introduced the
`AssetMappingPass` rename + the new `ImpersonateUserHelper`
interface. Both are abstracted behind the bridge's
`EasyAdminEnvironmentResolver` (added v0.5.4). Drift here would
manifest as DI compile errors caught by both `cache:clear` smokes
and the test matrix.

## Drift detection

CI catches drift in 3 places:

1. **composer validate** — fails on inconsistent constraints across packages
2. **PHPUnit matrix** — fails on Sf-specific API breakage on any
   tested row
3. **smoke-* scripts** — fail on install path regressions (the v0.1.1
   B2 Twig bug is the canonical example)

What CI does NOT catch:

- Sf 5.4 functional bugs (only the smoke gate, no full test suite)
- Sf 6.0–6.3 install + functional bugs (no matrix row, no smoke)
- ORM 3.x specific bugs (no matrix variation)
- Sf 8.0 anything (no row, not released)

**Recommendation:** before tagging v1.0, fill the gaps above with
either matrix rows or explicit "not supported" deprecation in the
constraint matrix. Defer to v0.7+ planning.

## v1.0 freeze targets

Per [ADR-011 (pre-v1.0 freeze checklist)](../adr/0011-pre-v1.0-freeze-checklist.md),
the v1.0 compatibility floor will commit to:

- **PHP 8.2+ floor** (drops 8.1 — currently 4% of Packagist downloads,
  EOL'd by Nov 2025)
- **Sf 6.4 LTS floor for all packages** (drops 5.4 — EOL'd Nov 2025,
  the dual-tier filter+bridge-on-5.4 stays through 0.x for adoption
  but consolidates at v1.0)
- **EA 4.24+ floor maintained** (bridge users on EA 4.x are the
  primary v0 target audience)
- **ORM 2.16+ floor maintained, with ORM 3.x as the recommended path**

The floor decisions are reversible until v1.0 ships. After v1.0
they're SemVer-locked.

## Related

- [ADR-015 — Baseline multi-version support](../adr/0015-baseline-multi-version-support.md) — why we support the matrix we do
- [ADR-011 — Pre-v1.0 freeze checklist](../adr/0011-pre-v1.0-freeze-checklist.md) — v1.0 floor decisions
- [.github/workflows/ci.yml](../../.github/workflows/ci.yml) — the actual matrix
- [scripts/smoke-*.sh](../../scripts/) — install-path smoke scripts
- [installation.md](../user/installation.md) — user-facing version compat statement
