# polysource/adapter-doctrine

> Doctrine ORM adapter for Polysource — generic read+write `DataSource` over any Doctrine entity.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## When to use

The Doctrine cohabitation case from [ADR-012](../../docs/adr/0012-dual-product-positioning.md) — when a Polysource standalone admin needs to expose a Doctrine entity (lightweight cases) **alongside** an EasyAdmin instance handling the heavy CRUD.

For pure Doctrine CRUD, keep using EasyAdmin.

## Install

```bash
composer require polysource/adapter-doctrine
```

Register the bundle:

```php
return [
    Polysource\Adapter\Doctrine\PolysourceAdapterDoctrineBundle::class => ['all' => true],
];
```

## What it ships

- **`DoctrineDataSource`** — implements `WritableDataSourceInterface` over `EntityManagerInterface`.
- **`DoctrineResource`** — non-final convenience base for declaring a Doctrine entity as a Polysource resource.
- **Whitelist filter properties** — only properties explicitly declared as filterable can be queried, preventing query injection.

## Documentation

- [Adapter doctrine guide](../../docs/user/adapters/doctrine.md)
- [Cookbook — build your own adapter](../../docs/user/cookbook/build-your-own-adapter.md)
