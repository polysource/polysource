# `polysource/adapter-flysystem`

Browse, upload, overwrite, delete files through the Polysource admin
on **any** Flysystem-supported backend: local disk, S3, Azure Blob,
Google Cloud Storage, FTP, in-memory.

## Install

```bash
composer require polysource/adapter-flysystem league/flysystem-aws-s3-v3
# (or league/flysystem-local, league/flysystem-azure-blob-storage, …)
```

```php
// config/bundles.php
return [
    // …
    Polysource\Adapter\Flysystem\PolysourceAdapterFlysystemBundle::class => ['all' => true],
];
```

## Wire a resource

```php
use League\Flysystem\FilesystemOperator;
use Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource;
use Polysource\Adapter\Flysystem\Resource\FlysystemResource;
use Polysource\Bundle\Attribute\AsResource;

#[AsResource]
final class InvoiceFileResource extends FlysystemResource
{
    public function __construct(FilesystemOperator $invoicesStorage)
    {
        parent::__construct(
            dataSource: new FlysystemDataSource(
                filesystem: $invoicesStorage,
                pathPrefix: 'uploads/invoices',
                recursive: true,
            ),
            slug: 'invoices',
            label: 'Invoices',
            permission: 'POLYSOURCE_INVOICE_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        return [];
    }
}
```

The host's `FilesystemOperator` is auto-injected. Hosts typically
declare one operator per bucket / disk in their existing
`oneup_flysystem.yaml` (or pure DI), and stack one
`FlysystemResource` per logical scope they want to admin (`invoices`,
`profile-photos`, `legal-attachments`, …).

## What the data source does

- **`search()`** — `listContents()` + offset-style pagination.
  Surfaces both files and directories (`isDirectory=true` for nav).
  Pagination is **emulated** over the iterator (skip-then-take) —
  fine for a few thousand files per directory; for huge buckets
  ship a custom data source backed by an inventory index.
- **`find($relativePath)`** — `fileExists()` + `mimeType()` +
  `fileSize()` + `lastModified()`.
- **`count()`** — always `null` per ADR-002 (cloud blob stores
  cannot count cheaply).
- **`create($payload)`** — `write()` (string contents) or
  `writeStream()` (resource). Returns the freshly written record.
- **`update($id, $payload)`** — refuses if the file doesn't exist,
  then writes (overwrite semantics). The pre-existing-file guard
  prevents silent typos creating new files at an unintended path.
- **`delete($id)`** — `delete()` (idempotent — missing file is a
  no-op).

## Identifier convention

The record `identifier` is the **relative** path from the configured
`pathPrefix`. So if `pathPrefix = 'uploads/invoices/'` and the file
lives at `uploads/invoices/2026/05/INV-001.pdf`, the record id is
`2026/05/INV-001.pdf`. URLs and routing use the relative form.

## Filter operators

Client-side: `eq`, `in`, `like`. Most useful against the synthetic
`extension` and `mimeType` properties: filter only PDFs, only images,
only files matching a name pattern.

## Why client-side filtering?

Flysystem itself doesn't support server-side filtering — it just
enumerates. For collections > a few thousand files, ship your own
data source backed by an inventory index (DynamoDB/Postgres/RediSearch/…)
and reuse the `WritableDataSourceInterface` contract.

## See also

- [ADR-002 — Pagination cursor](../../adr/0002-pagination-cursor.md)
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md)
