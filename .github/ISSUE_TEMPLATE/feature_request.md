---
name: Feature request
about: Suggest a use case or capability for Polysource
title: '[Feat] '
labels: enhancement
---

## Use case

Describe the concrete use case:
- **Which non-Doctrine resource** (Messenger failed, Redis hash, S3 file, HTTP API, etc.)?
- **What operation** would you want to perform (list, retry, update, delete, custom action)?

## Why this fits Polysource

Polysource has a strict scope (cf. [`docs/strategy/product-vision.md`](../../docs/strategy/product-vision.md) §2): admin for non-Doctrine / multi-source resources in Symfony.

Briefly explain how your request fits this scope. **If your request is about Doctrine ORM CRUD**, EasyAdmin is probably a better fit.

## Proposed solution

(Optional) How you'd implement it. If it adds a new adapter, what would the `DataSource` look like?

## Alternatives considered

What did you try / consider before opening this request?

## Additional context

Links to similar features in other admin frameworks (Sonata, EasyAdmin, Filament, React Admin) are welcome.
