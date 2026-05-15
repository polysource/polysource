# Cookbook — Messenger failed-messages dashboard

A complete, copy-paste template for adding a Polysource-powered
`/admin/failed-messages` dashboard to a Symfony 7.4 application. By
the end of this recipe you have a working dashboard with formatted
columns, retry / dismiss buttons, and per-attribute access control.

If you only want to *see* the dashboard run without writing any code,
run `make demo` from the repository root — it boots the bundled
`examples/messenger-demo` app on http://localhost:8080.

## Prerequisites

- Symfony 7.4 on PHP 8.4.
- A Messenger `failed` transport using a listable backend
  (Doctrine, Redis, AMQP, InMemory). See the
  [adapter reference → supported transports](../adapters/messenger.md#supported-transports).
- A Symfony Security firewall covering `/admin`. Polysource refuses to
  serve `/admin` without one.

## 1. Install

```bash
composer require polysource/symfony-bundle polysource/adapter-messenger
```

(Both packages are on Packagist since v0.1.0; current stable: v0.5.7.)

## 2. Register the bundles

If Symfony Flex didn't add them, edit `config/bundles.php`:

```php
return [
    // …
    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
    Polysource\Adapter\Messenger\PolysourceMessengerBundle::class => ['all' => true],
];
```

## 3. Wire routes and config

`config/routes/polysource.yaml`:

```yaml
polysource:
    resource: .
    type: polysource
```

`config/packages/polysource.yaml`:

```yaml
polysource:
    url_prefix: /admin
```

`config/packages/polysource_messenger.yaml`:

```yaml
polysource_messenger:
    failed_transport_name: failed
    resource_slug: failed-messages
    payload_max_bytes: 50000
    max_retry_all: 1000
    max_purge: 1000
```

## 4. Declare your field types

v0.1 of `polysource/core` ships only the abstract `FieldInterface` +
`FieldTrait`; concrete types live in your application until the core
ships built-in fields. Five short classes cover the usual
Messenger-failed columns. Drop them in `src/Polysource/Field/`:

```php
// src/Polysource/Field/IdField.php
namespace App\Polysource\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class IdField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/id.html.twig');
    }
}
```

```php
// src/Polysource/Field/TextField.php
namespace App\Polysource\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class TextField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/text.html.twig');
    }
}
```

```php
// src/Polysource/Field/DateTimeField.php
namespace App\Polysource\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class DateTimeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/datetime.html.twig');
    }
}
```

```php
// src/Polysource/Field/CodeField.php
namespace App\Polysource\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class CodeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/code.html.twig');
    }
}
```

```php
// src/Polysource/Field/BooleanField.php
namespace App\Polysource\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/boolean.html.twig');
    }
}
```

(Boolean isn't strictly used by the Messenger adapter; ship it anyway
— you'll need it for the next dashboard.)

## 5. Subclass `FailedMessageResource`

`src/Polysource/AppFailedMessageResource.php`:

```php
namespace App\Polysource;

use App\Polysource\Field\CodeField;
use App\Polysource\Field\DateTimeField;
use App\Polysource\Field\IdField;
use App\Polysource\Field\TextField;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;

final class AppFailedMessageResource extends FailedMessageResource
{
    public function configureFields(string $page): iterable
    {
        yield IdField::new('message_class', 'Message');
        yield TextField::new('exception_class', 'Exception')->onlyOnIndex();
        yield TextField::new('exception_message', 'Reason');
        yield DateTimeField::new('failed_at', 'Failed at');
        yield CodeField::new('payload', 'Payload')->onlyOnDetail();
    }
}
```

## 6. Swap the upstream service

`config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: false

    App\:
        resource: '../src/'
        exclude:
            - '../src/Kernel.php'
            # Reached via the swap below; auto-registering it here
            # would double-tag polysource.resource and collide on the
            # `failed-messages` slug.
            - '../src/Polysource/AppFailedMessageResource.php'

    # Replace the bundle's empty FailedMessageResource with the
    # subclass that yields concrete field DTOs. Arguments must be
    # re-declared explicitly — re-defining a service id in YAML does
    # NOT inherit the explicit replaceArgument calls the
    # PolysourceMessengerExtension made during compilation.
    Polysource\Adapter\Messenger\Resource\FailedMessageResource:
        class: App\Polysource\AppFailedMessageResource
        arguments:
            $dataSource: '@Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource'
            $slug: '%polysource_messenger.resource_slug%'
            $actions:
                - '@Polysource\Adapter\Messenger\Action\RetryFailedMessageAction'
                - '@Polysource\Adapter\Messenger\Action\DismissFailedMessageAction'
                - '@Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction'
                - '@Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction'
```

## 7. Grant access

The four `POLYSOURCE_FAILED_MESSAGE*` attributes are voter attributes,
not Symfony roles. Register a voter:

```php
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

`security.voter` is auto-applied via autoconfigure. For granular
per-attribute decisions (e.g. on-call retries but only SREs purge),
see [permissions-with-roles.md](./permissions-with-roles.md).

## 8. Verify

```bash
bin/console cache:clear
bin/console debug:router | grep failed-messages
```

Expected:

```
polysource_failed_messages_index         GET    /admin/failed-messages
polysource_failed_messages_detail        GET    /admin/failed-messages/{id}
polysource_failed_messages_bulk_action   POST   /admin/failed-messages/batch/{action}
polysource_failed_messages_action        POST   /admin/failed-messages/{id}/{action}
```

Open `http://localhost/admin/failed-messages`, log in with a user
holding `ROLE_ADMIN`. If your `failed` transport is currently empty
(it usually is), the table will be empty too. To generate a synthetic
failure, route a message to your `async` transport that you know will
throw, then `messenger:consume` it until the retry budget is
exhausted:

```bash
bin/console messenger:dispatch ... # any message routed to `async`
bin/console messenger:consume async --limit=1
```

## What you should see

- A table with five columns: **Message**, **Exception**, **Reason**,
  **Failed at**, plus row-level **Detail / Retry / Dismiss** buttons.
- A page-level **Retry all** and **Purge** dropdown.
- A detail view showing the same columns plus the `Payload` column
  (`@Polysource/field/code.html.twig` renders it as a `<pre>` block).
- Cursor-style pagination ("Older / Newer") instead of "Page 1 / N" —
  Messenger transports don't expose totals.

## Next steps

- [adding-a-custom-action.md](./adding-a-custom-action.md) — write a
  `RetryWithDelayAction` that re-dispatches with a `DelayStamp`.
- [permissions-with-roles.md](./permissions-with-roles.md) — split
  retry vs purge across two roles.
- [../adapters/messenger.md](../adapters/messenger.md) — full adapter
  reference including caveats and logging behaviour.
