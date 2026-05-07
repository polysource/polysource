# polysource/adapter-flysystem

> Files adapter for Polysource — admin S3, local, Azure, Google Cloud Storage, FTP, etc. via [`league/flysystem`](https://flysystem.thephpleague.com/).

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`FlysystemDataSource`** — implements `WritableDataSourceInterface` over `League\Flysystem\FilesystemOperator`.
- Pagination via `listContents` with offset emulation.
- Mime / size / extension exposure on each `DataRecord`.
- Idempotent write + delete.
- **`FlysystemResource`** — non-final convenience base.

## Install

```bash
composer require polysource/adapter-flysystem league/flysystem-aws-s3-v3
```

(Or any other Flysystem adapter — `league/flysystem-azure-blob-storage`, `league/flysystem-google-cloud-storage`, `league/flysystem-local`, etc.)

Register the bundle:

```php
return [
    Polysource\Adapter\Flysystem\PolysourceAdapterFlysystemBundle::class => ['all' => true],
];
```

## Documentation

- [Adapter flysystem guide](../../docs/user/adapters/flysystem.md)
