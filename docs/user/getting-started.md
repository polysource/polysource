# Getting started — five minutes to a Messenger dashboard

This guide walks you through the **fastest** path to a working
Polysource dashboard: the Messenger failed-messages screen, in your own
Symfony 7.4 application.

If you'd rather see it run before reading anything, the repository
ships a full demo:

```bash
make demo
```

(Wraps `make -C examples/messenger-demo up`. Boots a Symfony 7.4 app on
http://localhost:8080 with login `admin` / `admin`.)

The rest of this guide assumes you already have a Symfony 7.4 app and
want to add Polysource to it.

## Prerequisites

- A Symfony application on PHP 8.2 or later. Polysource requires
  PHP 8.2+ and Symfony 6.4 LTS+; the walk-through below uses Symfony
  7.4 on PHP 8.4 as its example stack.
- A configured Messenger `failed` transport. Doctrine, Redis, AMQP and
  InMemory all work; SQS and Beanstalk do not (see
  [adapters/messenger.md](./adapters/messenger.md#supported-transports)).
- A Symfony Security firewall protecting the URL prefix where
  Polysource will be mounted (default: `/admin`).

If your app already meets all three, you can ship the dashboard in 4
steps.

## 1. Install the packages

```bash
composer require polysource/symfony-bundle polysource/adapter-messenger
```

If you're working from a clone of the monorepo (contributor or
unreleased-branch testing), follow
[installation.md → Dev install](./installation.md#dev-install) first,
then come back here.

## 2. Register the bundles

Symfony Flex does this automatically. If you don't use Flex, edit
`config/bundles.php`:

```php
return [
    // …
    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
    Polysource\Adapter\Messenger\PolysourceMessengerBundle::class => ['all' => true],
];
```

## 3. Wire the routes and config

Create `config/routes/polysource.yaml`:

```yaml
polysource:
    resource: .
    type: polysource
```

Create `config/packages/polysource.yaml`:

```yaml
polysource:
    url_prefix: /admin
```

Create `config/packages/polysource_messenger.yaml`:

```yaml
polysource_messenger:
    failed_transport_name: failed   # must match the transport name in framework.messenger
    resource_slug: failed-messages
```

## 4. Grant access to the dashboard

Polysource asks Symfony Security if the current user holds the
`POLYSOURCE_FAILED_MESSAGE` attribute (and `POLYSOURCE_FAILED_MESSAGE_RETRY`,
`_DISMISS`, `_PURGE` for individual actions). Polysource attributes
don't start with `ROLE_`, so the default `RoleHierarchyVoter` abstains
on them — you must register a voter that knows how to vote on
them.

The simplest "all admins can do everything" voter, suitable for a
single-tenant internal tool:

```php
<?php
// src/Security/PolysourceAdminVoter.php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PolysourceAdminVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'POLYSOURCE_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return \in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}
```

Symfony auto-tags voters via `security.voter` when autowire +
autoconfigure are on (default). Done.

For per-attribute decisions (e.g. *"on-call can retry but only SREs
can purge"*), see
[cookbook/permissions-with-roles.md](./cookbook/permissions-with-roles.md).

## Verify

```bash
bin/console debug:router | grep polysource
bin/console cache:clear
```

You should see five routes:

```
polysource_failed_messages_index         GET     /admin/failed-messages
polysource_failed_messages_detail        GET     /admin/failed-messages/{id}
polysource_failed_messages_detail_panel  GET     /admin/failed-messages/{id}/detail-panel
polysource_failed_messages_bulk_action   POST    /admin/failed-messages/batch/{action}
polysource_failed_messages_action        POST    /admin/failed-messages/{id}/{action}
```

Open `http://localhost/admin/failed-messages`, log in with a user
holding `ROLE_ADMIN`. If your `failed` transport is empty, the table
will be empty too — that's normal and means the wiring works. To
generate a synthetic failure for testing:

```bash
bin/console messenger:dispatch ... # any message routed to the `async` transport
bin/console messenger:consume async --limit=1   # let it fail and land in `failed`
```

## What you get

- An index page listing failed envelopes (paginated, cursor-based —
  Messenger transports don't expose a total).
- A detail page showing the JSON-serialised payload, exception class,
  exception message, failed-at timestamp.
- Per-row **Retry** and **Dismiss** buttons.
- Page-level **Retry all** and **Purge** buttons (capped at 1000 per
  click — see `polysource_messenger.max_retry_all` / `.max_purge`).
- The option to let a row **expand in place** to show detail content
  loaded lazily — implement `HasRowDetailsInterface` on the resource,
  see [row-details.md](./row-details.md).

## Configuring the columns

`FailedMessageResource` ships an intentionally empty
`configureFields()` so it stays agnostic of your envelope shape — the
theme then derives columns from the record. To curate them, subclass
the resource and yield the concrete field types `polysource/core`
ships: `IdField`, `TextField`, `DateTimeField`, `BooleanField` and
`CodeField`. See
[cookbook/messenger-failed-dashboard.md](./cookbook/messenger-failed-dashboard.md)
for a copy-paste template.

## What you don't get out of the box

- **Authentication.** Polysource integrates with whatever firewall and
  user provider your app already uses; it never ships its own login
  page.
- **Audit trail.** Actions log via PSR-3 (`LoggerInterface`) when one
  is available, but Polysource does not persist an audit log itself.

## Next steps

- [concepts/](./concepts/) — understand the building blocks
  (Resource, DataSource, Field, Action, Permission).
- [cookbook/messenger-failed-dashboard.md](./cookbook/messenger-failed-dashboard.md)
  — full template including custom field types.
- [cookbook/adding-a-custom-action.md](./cookbook/adding-a-custom-action.md)
  — write your own `RetryWithDelayAction`.
- [cookbook/permissions-with-roles.md](./cookbook/permissions-with-roles.md)
  — per-action role mapping.
