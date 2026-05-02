# Polysource Messenger demo

A runnable Symfony 7.4 application that ships the **Messenger
failed-messages dashboard** wired through Polysource Admin.

This is the cas tueur of v0.1: Symfony Messenger has no built-in admin
UI for inspecting failed messages — only `bin/console messenger:failed:*`.
Polysource turns the same failure transport into a clickable
`/admin/failed-messages` page with retry / dismiss / retry-all / purge
actions.

## Five-minute walkthrough

```bash
make demo
```

(Run from the repo root — wraps `make -C examples/messenger-demo up`.)

The first run:

1. Builds the PHP 8.4 + SQLite container image (~2 min).
2. Installs Composer dependencies (~30 s).
3. Creates the SQLite database and the `messenger_messages` table.
4. Seeds five realistic failed envelopes (Stripe timeout, SMTP rate
   limit, PDF OOM, declined card, SMTP refused).
5. Starts `php -S 0.0.0.0:8080`.

Open <http://localhost:8080/admin/failed-messages> and log in:

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `admin` |

You'll see the five seeded messages. Click **Detail** on any row to see
the JSON-serialised payload. Click **Retry** to dispatch the message
back through the bus (no async worker is wired in the demo, so
"retry" effectively re-queues onto the `async` transport — visible
via `bin/console messenger:stats`). Click **Dismiss** to ack the
envelope without retrying.

Stop with `Ctrl+C`, then `make demo-down` to remove the container.

## What's inside

```
examples/messenger-demo/
├── bin/console               — Symfony console entry point
├── public/index.php          — front controller
├── src/
│   ├── Kernel.php            — MicroKernelTrait kernel
│   ├── Message/              — 3 message classes (Payment, Email, Invoice)
│   └── Command/SeedFailedMessagesCommand.php
├── config/
│   ├── bundles.php           — Framework + Twig + Security + Doctrine
│   │                           + Polysource + PolysourceMessenger
│   ├── packages/
│   │   ├── framework.yaml    — `failed:` transport routing
│   │   ├── doctrine.yaml     — SQLite via DBAL
│   │   ├── security.yaml     — basic auth + role_hierarchy mapping
│   │   ├── polysource.yaml
│   │   └── polysource_messenger.yaml
│   ├── routes.yaml           — imports the polysource: route loader
│   └── services.yaml
├── Dockerfile                — php:8.4-cli-alpine + ext-pdo_sqlite
├── docker-compose.yml        — single php container, port 8080
└── Makefile
```

## How role mapping works

`config/packages/security.yaml` declares a single `admin` user with
`ROLE_ADMIN`, then maps that role to the Polysource permission
attributes via `role_hierarchy`:

```yaml
role_hierarchy:
    ROLE_ADMIN:
        - POLYSOURCE_RESOURCE_VIEW
        - POLYSOURCE_ACTION_INVOKE
        - POLYSOURCE_FAILED_MESSAGE
        - POLYSOURCE_FAILED_MESSAGE_RETRY
        - POLYSOURCE_FAILED_MESSAGE_DISMISS
        - POLYSOURCE_FAILED_MESSAGE_PURGE
```

In a real deployment you would map specific roles (e.g.
`ROLE_ON_CALL`) to subsets — only retry, never purge — without
touching the Polysource code.

## Why no separate worker?

The demo seeds the failed transport directly (the `SeedFailedMessagesCommand`
crafts the stamps that Symfony's
`SendFailedMessageToFailureTransportListener` would have produced). This
keeps startup instantaneous: no need to run `messenger:consume async`,
wait for retries to exhaust, then check the failed transport. Real
applications use the standard worker — Polysource doesn't change that
flow.

## Resetting

```bash
make demo-clean    # wipes vendor/ and var/data.db
make demo          # fresh start with re-seeded messages
```

## Limitations / known caveats

- HTTP basic auth uses **plaintext password storage** (`admin`/`admin`).
  Acceptable for a localhost demo; never copy this section to
  production.
- The `async` transport is configured but no worker consumes it —
  retried messages just sit there. Run
  `docker compose run --rm php php bin/console messenger:consume async --limit=1`
  manually to drain.
- `make demo` binds `0.0.0.0:8080` — fine on a developer laptop,
  inappropriate on a shared host.

## License

MIT (same as the rest of the repository).
