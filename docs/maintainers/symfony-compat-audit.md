# Symfony compatibility audit — what we advertise vs what we test

> **Audience:** Polysource maintainers + reviewers triaging
> "does this work on Sf X?" issues. Not a user-facing doc — for
> hosts, see [installation.md](../user/installation.md).
>
> **Last audited:** 2026-08-24 (v1.2.0, Sf 8 / PHP 8.5 gating)

## TL;DR

Since the v1.0 freeze (2026-08-06) there is a **single support tier**:
every one of the 16 packages advertises `php: ">=8.2"`, and every
package that pins Symfony advertises `^6.4 || ^7.0 || ^8.0`. The
pre-1.0 dual-tier arrangement (filter + bridge advertising `^5.4`
while the meta-bundle required `^6.4`) is gone.

| Symfony minor | Advertised by | Composer-validated | Smoke-tested | Test-matrix-gated | Status |
|---|---|---|---|---|---|
| **6.4 LTS** | all 16 packages | ✓ | ✓ (`sf54-floor` job) | ✓ (PHP 8.2 × EA 4.x and EA 5.x) | **First-class** |
| **7.0–7.1** | all 16 | ✓ | ✗ | ✗ | **Best-effort** (EOL'd upstream) |
| **7.2** | all 16 | ✓ | ✗ | ✓ (PHP 8.3) | **First-class** (non-LTS proof) |
| **7.3** | all 16 | ✓ | ✗ | ✗ | **Best-effort** |
| **7.4 LTS** | all 16 | ✓ | ✓ | ✓ (PHP 8.3 + 8.4) | **First-class** |
| **8.0** | all 16 | ✓ | ✗ | ✗ | **Best-effort** (covered by the `^8.0` constraint, gated through 8.1) |
| **8.1** | all 16 | ✓ | ✓ (`sf81-ceiling` job) | ✓ (PHP 8.5 × EA 5.x) | **First-class** |

Symfony **5.4** and **6.0–6.3** are no longer advertised anywhere in
the monorepo: the v1.0 floor dropped them (ADR-011). Hosts still on
those lines must stay on the 0.x lineage.

**Definitions:**
- **Advertised** — listed in `composer.json` require constraints
- **Composer-validated** — `composer validate --strict` + the install resolver accepts the version (no API call made)
- **Smoke-tested** — one of the `scripts/smoke-*.sh` boots a vanilla skeleton against the version
- **Test-matrix-gated** — the CI PHPUnit matrix runs the full test suite against the version

## Why these tiers exist

Symfony's release calendar runs 2 minors per year. Polysource targets:

1. **All maintained LTS lines** (6.4, 7.4) — first-class
2. **The latest non-LTS** (currently 7.2) — first-class proof that non-LTS combos work
3. **The current top of the tree** (8.1 on PHP 8.5) — first-class since
   v1.2.0, gated by both a matrix row and a Packagist smoke job

The floor decision is documented in
[ADR-015 — Baseline multi-version support](../adr/0015-multi-version-compatibility-baseline.md)
and amended by ADR-011 for the v1.0 freeze.

## Package-level constraint inventory

Single tier, verified against `packages/*/composer.json` on 2026-08-07:

| Package | PHP | Symfony |
|---|---|---|
| `polysource/core` | `>=8.2` | — (pure PHP, no Symfony dependency) |
| `polysource/twig-theme` | `>=8.2` | — (Twig 3.x only) |
| `polysource/filter` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/easyadmin-filter-bridge` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/symfony-bundle` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/widgets` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/search` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/bulk-async` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/workflow-bridge` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/audit` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-doctrine` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-flysystem` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-http` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-messenger` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-meilisearch` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |
| `polysource/adapter-redis` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` |

**Implication:** the bridge-only path (`polysource/filter` +
`polysource/easyadmin-filter-bridge`) and the full meta-bundle path
now share one floor. There is no longer a combination of packages
that installs below Sf 6.4.

## CI matrix breakdown

From `.github/workflows/ci.yml::test` — 6 hand-listed rows:

```
Row 1: PHP 8.2 × Sf 6.4 LTS × EA ^4.24  (floor)
Row 2: PHP 8.2 × Sf 6.4 LTS × EA ^5.0   (bridge crossover)
Row 3: PHP 8.3 × Sf 7.2     × EA ^5.0   (non-LTS proof, Sf 7.2 EOL'd)
Row 4: PHP 8.3 × Sf 7.4 LTS × EA ^5.0   (modern)
Row 5: PHP 8.4 × Sf 7.4 LTS × EA ^5.0   (bleeding)
Row 6: PHP 8.5 × Sf 8.1     × EA ^5.0   (ceiling)
```

The test job unsets `config.platform.php` before installing. The root
manifest pins it to `8.2.99` so a maintainer on a newer PHP still
resolves against the v1.0 floor, but in CI that pin is redundant
(setup-php already provides each row's exact PHP) and it makes the Sf 8
row unresolvable, since Symfony 8 requires PHP 8.4+.

Plus, in the same workflow:
- `sf54-floor` job — "Sf 6.4 floor smoke (filter + bridge alone)":
  bootstraps a vanilla Sf 6.4 skeleton with EA 4, asserts the bridge
  and filter resolve without pulling `symfony-bundle`, and checks
  `cache:clear` succeeds. Locally: `make smoke-sf54`.
- `sf81-ceiling` job — the mirror image: vanilla Sf 8.1 skeleton on PHP
  8.5 with EA 5, asserts Symfony 8 actually resolved (a stale constraint
  silently falling back to 7.4 would make the job worthless), then
  `cache:clear`, the route count, and `lint:twig`.
- `integration` job — bridge integration suite (TestKernel + SQLite)
- `coverage` job — 90% coverage gate on `packages/core`
- `e2e` job — boots the showcase compose stack and runs
  `phpunit -c phpunit.panther-ci.xml --group=panther`
- `smoke-packagist` / `smoke-packagist-bridge` (manual, `make` targets)
  — vanilla Sf 7.4 + full meta-bundle, and bridge alone, both against
  real Packagist

**Why hand-listed rows:** cartesian-product matrices generate
impossible combos (PHP 8.2 × Sf 8.0 is impossible — Sf 8 needs PHP
8.4+). Listing each row by hand keeps the matrix fast and prevents
"random" failures from impossible combinations.

## What Symfony 8 and PHP 8.5 actually broke (v1.2.0 audit)

Every package that pins Symfony carries `|| ^8.0` (14 of 16 — `core`
and `twig-theme` have no Symfony dependency at all), and every package
advertises `php: ">=8.2"` with no upper bound. Those constraints were
written speculatively before Symfony 8 shipped. The v1.2.0 audit ran
the real thing: PHP 8.5.9 × Sf 8.1.5 × EA 5.5.1 × ORM 3.6.7.

**Product code needed zero changes.** The full 1278-test suite passed
on the first run against that stack. What broke was peripheral, and
all of it is now fixed:

| What | Where | Consumer-visible? |
|---|---|---|
| `symfony/mercure` constrained to `^0.6 \|\| ^1.0`, which excludes 0.7/0.8 — and 0.8 is the only line supporting Sf 8 | root + `bulk-async` require-dev/suggest | **Yes.** A host on Sf 8 wanting live bulk-job progress could not resolve. Widened to `^0.6 \|\| ^0.7 \|\| ^0.8 \|\| ^1.0`. |
| Mercure 0.8 added `getProtocolVersion()` / `getCookieName()` to `HubInterface`; two hand-written stubs fataled at class-load | `bulk-async` unit tests | No. Replaced by PHPUnit mocks so the interface's shape stops being our problem. |
| Sf 8 added a 4th `?Vote $vote` parameter to `Voter::voteOnAttribute()` | one integration fixture | No. The production voter (`SavedViewVoter`) already carried the cross-version `mixed $vote = null` override; the fixture had been missed. |
| `PhpVersionCheck::REQUIRED` still read `8.1.0` | `polysource:doctor` | Cosmetic. The v1.0 freeze moved the floor to 8.2; the check now says so. |

Two non-findings worth recording, so nobody re-investigates them:

- **PHPStan reports ~12 `class.notFound` errors against a Sf 8 vendor**
  (including `ViewEvent`, which exists perfectly well in 8.1). This is
  the ADR-015 `phpVersion: 80100` parser baseline choking on the PHP
  8.4-only syntax Symfony 8.1 ships (property hooks, asymmetric
  visibility), not a missing symbol. The `phpstan` job pins the vendor
  to Sf 7.4 for exactly this reason; leave it pinned.
- **The only deprecations the suite emits on PHP 8.5** are
  `ReflectionProperty::setAccessible()` calls in three test files.
  Zero occurrences in any `src/`.

## Forward-compat: what to do at the next Symfony minor

The pattern is now established, so the next bump is mechanical:

- [ ] Add a matrix row and bump `sf81-ceiling` to the new minor
- [ ] Re-run the audit stack and update the table above
- [ ] Check whether any `symfony/*` optional dependency (Mercure is the
      one that bit us) narrowed itself out of the new lineage

## Doctrine ORM 2.x vs 3.x compat

Orthogonal to Symfony, but worth documenting here. Effective floors
from `composer.json`: ORM `^2.20 || ^3.0` (`adapter-doctrine`,
`audit`, `easyadmin-filter-bridge`), DBAL `^3.6 || ^4.0` (`audit`),
persistence `^3.0 || ^4.0` (`adapter-doctrine`).

| Doctrine ORM | Advertised by | Tested |
|---|---|---|
| **2.20+** | `adapter-doctrine`, `audit`, `bridge` | ✓ (current CI default) |
| **3.x** | same | ✗ in CI (composer minor variation exercised locally only) |

The ORM 2.x quirks (HYDRATE_ARRAY + entity select yielding 0 rows;
`toIterable` streaming differences) are documented in
[test-kernel-patterns.md](./test-kernel-patterns.md#6-doctrine-orm-2x-quirk--hydrate_array--entity-select).

**Open gap (post-1.0):** no CI matrix row pins ORM 3.x. Adding one is
cheap (a `doctrine` axis on the existing rows) and would catch ORM
2-vs-3 drift. Tracked in the ROADMAP Backlog.

## EasyAdmin compat

Polysource's bridge advertises `^4.24 || ^5.0`. Tested combinations:

| EA version | PHP × Sf rows that test it |
|---|---|
| **4.24+** | PHP 8.2 × Sf 6.4 (row 1), plus the `sf54-floor` smoke job |
| **5.0+** | PHP 8.2 × Sf 6.4, PHP 8.3 × Sf 7.2 + 7.4, PHP 8.4 × Sf 7.4 |

The EA 4.x → 5.x transition introduced the
`AssetMappingPass` rename + the new `ImpersonateUserHelper`
interface. Both are abstracted behind the bridge's
`EasyAdminEnvironmentResolver` (added v0.5.4). Drift here would
manifest as DI compile errors caught by both `cache:clear` smokes
and the test matrix.

## Drift detection

CI catches drift in 4 places:

1. **composer validate** — fails on inconsistent constraints across packages
2. **PHPUnit matrix** — fails on Sf-specific API breakage on any
   tested row
3. **Panther E2E job** — fails on browser-level regressions in the
   showcase stack
4. **smoke-* scripts** — fail on install path regressions (the v0.1.1
   B2 Twig bug is the canonical example)

What CI does NOT catch:

- ORM 3.x specific bugs (no matrix variation). Note the Sf 8.1 row
  resolves ORM 3.x incidentally, so the combination is exercised, but
  nothing pins it: an ORM-2-only regression on an older row would still
  slip through.
- Mercure lineage drift. The suite mocks `HubInterface`, which is what
  makes it version-proof, but that also means no row proves the real
  0.8 hub still behaves as `MercureBulkJobBroadcaster` expects.

## v1.0 freeze — shipped

Per [ADR-011 (pre-v1.0 freeze checklist)](../adr/0011-pre-v1.0-freeze-checklist.md),
v1.0.0 shipped on 2026-08-06 with these floors applied across all 16
packages:

- **PHP 8.2+** (8.1 dropped)
- **Sf 6.4 LTS for all packages** (5.4 and 6.0–6.3 dropped; the
  pre-1.0 dual-tier filter+bridge-on-5.4 arrangement ended here)
- **EA 4.24+ maintained** (`^4.24 || ^5.0`)
- **ORM 2.20+ maintained, ORM 3.x allowed and recommended**

These floors are now SemVer-locked: raising any of them requires a
major release. Of the two verification gaps left open at the freeze,
the Sf 8 smoke gate shipped in v1.2.0 (`sf81-ceiling`); a dedicated ORM
3.x matrix row is still open in the ROADMAP Backlog.

Nothing in v1.2.0 touched a floor: it added a ceiling. Hosts on PHP 8.2
and Sf 6.4 upgrade to it with no action.

## Related

- [ADR-015 — Baseline multi-version support](../adr/0015-multi-version-compatibility-baseline.md) — why we support the matrix we do
- [ADR-011 — Pre-v1.0 freeze checklist](../adr/0011-pre-v1.0-freeze-checklist.md) — v1.0 floor decisions
- [.github/workflows/ci.yml](../../.github/workflows/ci.yml) — the actual matrix
- [scripts/smoke-*.sh](../../scripts/) — install-path smoke scripts
- [installation.md](../user/installation.md) — user-facing version compat statement
