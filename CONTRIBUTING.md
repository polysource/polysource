# Contributing to Polysource

Thanks for your interest in Polysource Admin. This document explains how to contribute.

## Project status

Polysource is currently in **design phase** — the strategic and architectural analysis lives in [`docs/`](./docs/README.md), and the implementation roadmap is in [`docs/roadmap/development-plan.md`](./docs/roadmap/development-plan.md).

**No code has been written yet.** Until the development plan is validated and the first packages are scaffolded (`packages/core`, `packages/symfony-bundle`, `packages/twig-theme`, `packages/adapter-messenger`), the most useful contributions are:

- **Reviewing the documentation** — open an issue if you spot inconsistencies, gaps, or design choices you disagree with.
- **Sharing use cases** — what non-Doctrine resource would *you* want to administer? File an issue describing the source, the operations needed, and how you'd configure it.
- **Pressure-testing the architecture** — challenge `DataSourceInterface` against your real-world cases.

## Code of conduct

By participating, you agree to abide by the [Code of Conduct](./CODE_OF_CONDUCT.md).

## Reporting issues

When opening an issue, please provide:

- A clear, descriptive title.
- The Polysource version (or `main` if you're on the design branch).
- The Symfony and PHP versions you target.
- A minimal reproducible example if reporting a bug.
- For architecture/design issues, the specific document section you're commenting on.

## Development workflow (once code lands)

> This section is a placeholder. It will be completed once `packages/core` is scaffolded.

The intended workflow is:

1. Fork the repository.
2. Create a feature branch: `git checkout -b feat/short-description`.
3. Write tests first (the project follows TDD discipline).
4. Make your changes — keep them focused and small.
5. Run the test suite locally: `composer test`.
6. Run the static analysis: `composer phpstan`.
7. Run the code style fixer: `composer cs-fix`.
8. Commit with a Conventional Commits-style message:
   - `feat(core): add DataQuery::withFilter`
   - `fix(adapter-messenger): handle empty failed transport`
   - `docs(roadmap): clarify Phase 4 acceptance criteria`
9. Open a pull request describing the *why*, not just the *what*.

## Commit conventions

- Use [Conventional Commits](https://www.conventionalcommits.org/) format.
- One logical change per commit.
- Reference issues in the body (`Closes #123`), not the subject line.

## Pull requests

- Keep PRs small. A good PR can be reviewed in 30 minutes.
- Link the related issue.
- Include a "Before / After" section in the description if the change is user-visible.
- All CI checks must pass before review.
- Maintainers may request changes — please don't take it personally.

## Project scope discipline

Polysource has a strict scope: **admin for non-Doctrine / multi-source resources in Symfony**. PRs that drift from this scope will be politely declined. Specifically, **out of scope**:

- Doctrine-first CRUD generation (use EasyAdmin).
- No-code internal-tool builders (use Retool / Appsmith).
- BI dashboards (use Grafana / Metabase).
- Multi-tenant SaaS features.

Adding a new adapter (Redis, HTTP, Meilisearch, etc.) is **always welcome** if it ships with:

- A real-world use case description.
- Integration tests against a real instance (testcontainers is fine).
- Documentation showing setup in under 10 minutes.

## Adapter contributions

If you want to contribute a new adapter, the contract is:

```php
namespace YourVendor\PolysourceAdapter\Yours;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataRecord;

final class YourDataSource implements DataSourceInterface
{
    public function search(DataQuery $query): DataPage { /* ... */ }
    public function find(string|int $identifier): ?DataRecord { /* ... */ }
    public function count(DataQuery $query): ?int { /* ... */ }
}
```

Optionally implement `WritableDataSourceInterface` for create/update/delete.

Register via Symfony service tag:

```yaml
services:
    YourVendor\PolysourceAdapter\Yours\YourDataSource:
        tags: [{ name: 'polysource.data_source', alias: 'yours' }]
```

## Documentation contributions

Doc PRs are first-class. Please:

- Keep the writing style consistent with existing docs (factual, evidence-based, not promotional).
- Prefer file/line citations over hand-waving.
- Update the index in [`docs/README.md`](./docs/README.md) if you add a new section.

## Questions

Open a GitHub Discussion or issue. There is no Discord or Slack yet — the project will only set those up if there's demonstrated demand (≥ 100 stars).

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](./LICENSE).
