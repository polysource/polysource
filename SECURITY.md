# Security Policy

Polysource is a Symfony admin engine that handles authentication
flows, permission checks, file storage, audit logs, and bulk
mutations on production data. Security issues are taken seriously.

## Reporting a vulnerability

**Do not file a public GitHub issue for security reports.**

Report privately via [GitHub Security Advisories](https://github.com/polysource/polysource/security/advisories/new),
which keeps the disclosure private until a fix is available.

You can also email the maintainer directly:
[aymen.samaali@gmail.com](mailto:aymen.samaali@gmail.com).
PGP encryption is not yet set up; if you need encrypted comms, ping
the maintainer first to arrange a key exchange channel.

When reporting, please include:

1. A clear description of the vulnerability and its impact.
2. The package(s) and version(s) affected (e.g.
   `polysource/symfony-bundle@1.1.0`).
3. Steps to reproduce, ideally with a minimal proof-of-concept
   (a test case, a curl command, or a tiny Symfony app).
4. Any mitigations or workarounds you've identified.
5. Whether you'd like credit in the eventual advisory (and how
   you'd like to be named).

## Triage timeline

| Step | Target |
|---|---|
| Acknowledge receipt | within 72 hours |
| Initial assessment + severity | within 7 days |
| Fix + advisory drafted | within 30 days for High / Critical, best-effort otherwise |
| Public disclosure | coordinated with the reporter, typically 14-90 days after fix is shipped |

These targets are best-effort for a single-maintainer project.
They will tighten as the project matures and a broader security
team forms.

## Supported versions

| Version | Status |
|---|---|
| `1.x` | ✅ Active — security fixes shipped as patch releases |
| `< 1.0` | ❌ Pre-1.0 releases — no longer supported, please upgrade |

The compatibility matrix (PHP / Symfony / EasyAdmin / Doctrine
versions) is documented in
[`docs/adr/0015-multi-version-compatibility-baseline.md`](./docs/adr/0015-multi-version-compatibility-baseline.md).
A security fix lands on every supported row of the matrix
simultaneously.

## Scope

In-scope:

- All packages under the `Polysource\\*` namespace shipped from
  this monorepo (`polysource/core`, `polysource/symfony-bundle`,
  `polysource/filter`, `polysource/easyadmin-filter-bridge`,
  `polysource/audit`, `polysource/widgets`, `polysource/search`,
  `polysource/workflow-bridge`, `polysource/bulk-async`, and the
  six adapters under `polysource/adapter-*`).
- The default Twig theme shipped via `polysource/twig-theme`.
- The Stimulus controllers shipped from each package's
  `assets/controllers/` directory.

Out of scope:

- The `examples/` showcase apps — they are demos with intentionally
  weak credentials (e.g. `shopco/shopco` HTTP basic) and are not
  hardened for production.
- Third-party dependencies — report those upstream
  (`symfony/messenger`, `easycorp/easyadmin-bundle`,
  `predis/predis`, etc.).
- Issues that require unrealistic preconditions (e.g. the operator
  is already root on the host, or has database write access
  outside the admin UI).

## Hall of fame

Researchers who report a confirmed vulnerability are credited in
the matching GitHub Security Advisory, in the
[`CHANGELOG.md`](./CHANGELOG.md), and (with consent) in this file
once the project ships its first such advisory.

_No advisories yet — this section will populate as the project
matures._
