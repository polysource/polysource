# polysource/core

> Core contracts and value objects for Polysource Admin.
> Pure PHP. **Zero Symfony dependency.**

## Overview

This package provides the storage-agnostic contracts and value objects that
all Polysource adapters and the Symfony bundle build upon:

- **DataSource interfaces** — `DataSourceInterface` (read-only, 3 methods),
  `WritableDataSourceInterface`, `BatchableDataSourceInterface`
- **Query value objects** — `DataQuery`, `DataPage`, `DataRecord`, `DataPayload`,
  `FilterCriterion`, `Pagination`, `SortDirection`
- **Resource contracts** — `ResourceInterface`, `AbstractResource`
- **Field declarations** — `FieldInterface`, `FieldDto`, `FieldTrait`
- **Filter declarations** — `FilterInterface`, `FilterDto`
- **Action contracts** — `ActionInterface`, `InlineActionInterface`,
  `BulkActionInterface`, `ActionResult`
- **Permission abstraction** — `PermissionInterface`
- **Exceptions** — `DataSourceException`, `ResourceNotFoundException`,
  `UnsupportedOperationException`

## Installation

```bash
composer require polysource/core
```

Requires PHP 8.4+.

## Status

This package is part of Polysource v0.1 (design phase, no production release yet).

See the main repository [README](https://github.com/polysource/polysource) and
[ADRs](https://github.com/polysource/polysource/tree/main/docs/adr).

## License

MIT
