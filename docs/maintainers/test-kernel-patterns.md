# TestKernel patterns — canonical setup for polysource integration tests

> **Audience:** Polysource maintainers writing functional / integration
> tests inside a package's `tests/` tree. Hosts using polysource do
> not need this — it documents the conventions used inside the
> monorepo so future TestKernels stay consistent.
>
> **Status:** Pattern guide, not extracted code. Extraction into a
> shared `polysource/test-fixtures` package is deferred — see
> [Future extraction](#future-extraction) below for the trigger.

## Why this guide exists

Three TestKernels already live in the monorepo:

- `packages/easyadmin-filter-bridge/tests/Functional/Integration/App/TestKernel.php` (161 lines)
- `packages/adapter-messenger/tests/Functional/App/TestKernel.php` (148 lines)
- `packages/symfony-bundle/tests/Functional/App/TestKernel.php` (96 lines)

Each one wires a different bundle stack — intentionally — but they
all hit the same surface problems: PHPUnit 11 strict mode, cache-dir
collisions across kernel instances, KernelBrowser leaking handlers,
SQLite schema reset, etc. This guide captures the **canonical
solution to each** so the next TestKernel doesn't reinvent or skip
them.

## 1. Unique cache + log dirs per kernel instance

**Problem:** Two TestKernel instances in the same test run that share
a cache dir get into a race where Symfony's container compiler
silently reuses the first one's compiled container. Symptom:
`ServiceNotFoundException` for services the second kernel registers
but the first didn't.

**Solution:** Include `spl_object_id($this)` in the path.

```php
public function getCacheDir(): string
{
    return sys_get_temp_dir() . '/polysource-<pkg>/' . $this->environment . '/' . spl_object_id($this) . '/cache';
}

public function getLogDir(): string
{
    return sys_get_temp_dir() . '/polysource-<pkg>/' . $this->environment . '/' . spl_object_id($this) . '/logs';
}
```

**Audit status (2026-05-15):**

| TestKernel | Uses `spl_object_id`? |
|---|---|
| `easyadmin-filter-bridge` integration kernel | ✓ |
| `adapter-messenger` functional kernel | ✗ — latent risk if tests run kernels in parallel |
| `symfony-bundle` functional kernel | ✗ — latent risk |

The two `✗` rows are safe **today** because their suites only boot a
single kernel instance per test, but new tests should adopt the
pattern. Tracked for cleanup with the future extraction.

## 2. Direct `$kernel->handle()` instead of KernelBrowser

**Problem:** PHPUnit 11 with `failOnRisky=true` flags every test that
uses `Symfony\Bundle\FrameworkBundle\KernelBrowser` because
Symfony's `Kernel::handle()` registers an exception handler via
`set_exception_handler()` and never unwinds it during `shutdown()`.
PHPUnit detects the leftover handler and flags the test as risky.

**Solution:** Dispatch requests directly through `$kernel->handle()`.
The HTTP path is the same; we skip the cookie + history bookkeeping
that the browser layer adds.

```php
protected function request(string $method, string $path): Response
{
    $request = Request::create($path, $method);

    return $this->kernel->handle($request);
}
```

If a test genuinely needs the browser (form submissions, redirect
following), put it in a **separate `integration` PHPUnit suite** and
disable failOnRisky for that suite only:

```xml
<!-- phpunit.xml.dist -->
<testsuite name="integration">
    <directory>packages/<pkg>/tests/Functional/Integration</directory>
</testsuite>
```

```bash
# Run via dedicated target with the failOnRisky override
vendor/bin/phpunit --testsuite=integration --do-not-fail-on-risky
```

See `Makefile::test-integration` for the canonical invocation.

## 3. SQLite in-memory schema reset per test

**Problem:** Doctrine + SQLite-in-memory survives the connection but
not across kernel instances. The fresh kernel per test starts with an
empty schema and needs `SchemaTool::createSchema()` called before
fixtures load.

**Solution:** Run `SchemaTool::dropSchema + createSchema` in
`setUp()`. The drop is belt-and-braces — SQLite in-memory wouldn't
survive anyway, but the explicit pair locks the contract.

```php
protected function setUp(): void
{
    $this->kernel = new TestKernel('test', false);
    $this->kernel->boot();
    $this->em = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');

    $schemaTool = new SchemaTool($this->em);
    $metadata = $this->em->getMetadataFactory()->getAllMetadata();
    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $this->loadFixtures();
}

protected function tearDown(): void
{
    $this->kernel->shutdown();
    parent::tearDown();
}
```

## 4. Minimal security stanza (anonymous firewall)

Most integration tests don't exercise auth, but Symfony's
`SecurityBundle` errors out if it's wired without at least one
firewall. Use the public-everywhere stanza:

```php
$container->loadFromExtension('security', [
    'providers' => [
        'in_memory' => ['memory' => null],
    ],
    'firewalls' => [
        'main' => [
            'pattern' => '^/',
            'security' => false,
        ],
    ],
]);
```

Tests that DO exercise auth should subclass the TestKernel and
override `configureContainer` to wire an `http_basic` firewall + an
`InMemoryUserProvider` with seeded fixtures. The bundle-tests use a
`DeniedTestKernel` variant for this pattern — see
`packages/symfony-bundle/tests/Functional/App/`.

## 5. Doctrine mapping in monorepo dev mode

**Problem:** Tests need to map both the test fixture entities AND
the polysource entities (e.g., `SavedView`, `FilterUrlToken`,
`ColumnPreferences`) without relying on Composer autoload paths or
PSR-4 magic. The polysource entity sources live at
`packages/<pkg>/src/`, not in `vendor/`.

**Solution:** Use `is_bundle: false` mappings with absolute paths
computed from the kernel file's location:

```php
'mappings' => [
    'TestApp' => [
        'type' => 'attribute',
        'is_bundle' => false,
        'dir' => __DIR__ . '/Entity',
        'prefix' => 'Polysource\\<Pkg>\\Tests\\Functional\\Integration\\App\\Entity',
        'alias' => 'TestApp',
    ],
    'PolysourceFilter' => [
        'type' => 'attribute',
        'is_bundle' => false,
        // 5 dirs up from packages/<pkg>/tests/Functional/Integration/App
        // gives packages/, then 'filter/src' from there.
        'dir' => \dirname(__DIR__, 5) . '/filter/src',
        'prefix' => 'Polysource\\Filter',
        'alias' => 'PolysourceFilter',
    ],
],
```

The `\dirname(__DIR__, 5)` math depends on where the TestKernel
lives in the tree. If your TestKernel sits closer to the package
root, adjust the depth count.

## 6. Doctrine ORM 2.x quirk — HYDRATE_ARRAY + entity select

**Problem:** In Doctrine ORM 2.x, `toIterable($qb->getQuery(), [], AbstractQuery::HYDRATE_ARRAY)`
on a query that selects a full entity (`SELECT e FROM Entity e`)
yields **zero rows** silently — ArrayHydrator can't stream entity
objects, so it skips them. ORM 3.x fixes this. Polysource currently
supports both.

**Solution:** Select scalar fields explicitly when using array
hydration:

```php
$qb = $em->createQueryBuilder()
    ->select('e.id', 'e.name', 'e.status')  // NOT 'e'
    ->from(Entity::class, 'e');

foreach ($qb->getQuery()->toIterable([], AbstractQuery::HYDRATE_ARRAY) as $row) {
    // ['id' => …, 'name' => …, 'status' => …]
}
```

This bit the bridge's CSV export — regression covered by
`BridgeIntegrationTestCase::exportYieldsAllRows`.

## 7. Bundle::boot() route registration

The bridge's `Bundle::boot()` auto-imports its 8 routes by reading
`config/routes.php` from its own resource path. TestKernels that
include the bridge get these routes automatically — no manual
`configureRoutes()` work. The pattern is documented in
`PolysourceEasyAdminFilterBridgeBundle::boot()`.

If your kernel needs to override the bridge's prefix (multi-tenant
test scenario), set `auto_register_routes: false` in the bundle
config and import the routes manually in `configureRoutes()`:

```php
protected function configureRoutes(RoutingConfigurator $routes): void
{
    $routes->import(
        '@PolysourceEasyAdminFilterBridge/config/routes.php',
        prefix: '/tenant/{slug}/admin',
        type: 'php',
    );
}
```

## Future extraction

The pattern guide above is **the canonical reference today**. Three
TestKernels exist; only the bridge integration kernel uses items 2,
3, and 6 (HTTP-level integration). The other two are functional
tests that wire bundles and inspect the container without dispatching
requests.

**Extraction trigger:** when a 2nd package needs HTTP-level
integration testing (kernel boot + request dispatch + SchemaTool
reset), extract the following into a `polysource/test-fixtures`
dev-only package:

- `BridgeIntegrationTestCase` → `IntegrationTestCase` (rename, drop
  bridge-specific fixture loading; subclasses provide their own)
- The unique-cache-dir trait
- The `Request::create + $kernel->handle()` helper
- The SchemaTool reset helper
- The JSON decode helper

The composer.json would be dev-only (`type: library`, listed under
`require-dev` of the consuming packages). It would NOT be published
to Packagist as a runtime dep — only the monorepo's CI uses it via
path repos.

**Today's decision:** don't extract. YAGNI applies — extracting one
caller into a shared package adds composer.json, CI, release pipeline,
Packagist mirror, ADR, maintenance overhead for zero current benefit.
This guide is enough to keep the next TestKernel author aligned.

## Drift detection

If you update the canonical TestKernel (the bridge's), update this
guide. The drift signal that would have warned us in the past:

- A new TestKernel that skips item 1 (unique cache dirs) → reviewer
  catches it via this guide
- A new TestKernel using KernelBrowser without a separate integration
  suite → CI fails risky-strict
- A new TestKernel mapping polysource entities via PSR-4 expecting
  vendor paths → tests fail with `Class Entity not managed`

## Related

- [phpunit.xml.dist](../../phpunit.xml.dist) — integration suite definition
- [Makefile::test-integration](../../Makefile) — `--do-not-fail-on-risky` invocation
- `packages/easyadmin-filter-bridge/tests/Functional/Integration/BridgeIntegrationTestCase.php`
  — current canonical implementation
- ADR-008 — Docker as default execution context (why TestKernels use `sys_get_temp_dir()`)
