# Concept — Permission

Polysource never makes its own access-control decisions. It asks
**Symfony Security** (or any compatible checker) whether the current
user holds a given **string attribute**, and acts on the answer.

The whole abstraction is one method:

```php
namespace Polysource\Core\Permission;

interface PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool;
}
```

That's it. No roles. No ACL. No tenants. Just *"is this attribute
granted to this user, possibly with this subject?"*

## Where attributes are checked

Polysource asks `isGranted()` in **three** places:

| When | Attribute checked | Source of attribute |
|---|---|---|
| Before any access to a resource (index, detail, action) | `ResourceInterface::getPermission()` | Resource declaration |
| Before rendering a field | `FieldDto::$permission` | `FieldTrait::setPermission()` |
| Before rendering or invoking an action | `ActionInterface::getPermission()` | Action declaration |

If `getPermission()` returns `null`, the check is skipped (every
authenticated user passes). If it returns a string, that string is
passed verbatim to `PermissionInterface::isGranted()`.

A typical Messenger setup uses these attributes:

| Attribute | Granted to who can… |
|---|---|
| `POLYSOURCE_FAILED_MESSAGE` | …open the dashboard at all |
| `POLYSOURCE_FAILED_MESSAGE_RETRY` | …click "Retry" on a row |
| `POLYSOURCE_FAILED_MESSAGE_DISMISS` | …click "Dismiss" |
| `POLYSOURCE_FAILED_MESSAGE_PURGE` | …click "Purge all" |

There is nothing magical about the `POLYSOURCE_` prefix — it's
convention. Use whatever string scheme fits your app.

## The default Symfony binding

`polysource/symfony-bundle` ships
`SymfonyAuthorizationCheckerPermission`, a thin adapter from
`PermissionInterface` to Symfony's `AuthorizationCheckerInterface`:

```php
final readonly class SymfonyAuthorizationCheckerPermission implements PermissionInterface
{
    public function __construct(
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {}

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        if (null === $this->authorizationChecker) {
            throw new LogicException(/* … */);
        }

        return $this->authorizationChecker->isGranted($attribute, $subject);
    }
}
```

The bundle aliases `PermissionInterface` to this implementation by
default. You don't have to wire anything.

## Fail-closed contract

If your app does not register a Symfony Security firewall covering
the Polysource URL prefix, `SymfonyAuthorizationCheckerPermission`
**throws** rather than silently granting access:

> `PolysourceBundle could not find a Symfony Security firewall.
> Configure security.firewalls covering your polysource.url_prefix
> (default /admin), or alias
> Polysource\Core\Permission\PermissionInterface to a custom
> implementation in your services.yaml. Polysource intentionally
> refuses to fall back to a permissive default.`

This is deliberate. /admin is exactly the URL prefix where a missing
firewall is most damaging, so Polysource refuses to serve traffic
under those conditions.

## How attributes get answered

Symfony's `AuthorizationCheckerInterface` runs every registered voter
and combines their votes with the configured strategy (`affirmative`
by default). Out-of-the-box voters:

- `RoleHierarchyVoter` — votes on attributes that start with
  `ROLE_`. **Polysource attributes do not start with `ROLE_`**, so
  this voter abstains on them.
- `ExpressionVoter` — votes on `expression(...)` attributes.
- `AuthenticatedVoter` — votes on `IS_AUTHENTICATED_*` attributes.

None of those vote on `POLYSOURCE_*`. You have two options:

### Option 1 — write a custom voter (recommended)

```php
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
        // Real apps decide per attribute. This one grants everything to ROLE_ADMIN.
        return \in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}
```

With autowire + autoconfigure on (Symfony default), the voter is
auto-tagged with `security.voter`. Done.

For granular per-action mapping (e.g. on-call retries but only SREs
purge), see [../cookbook/permissions-with-roles.md](../cookbook/permissions-with-roles.md).

### Option 2 — replace `PermissionInterface` outright

If your app doesn't use Symfony Security at all (e.g. you build on top
of a custom session token), implement `PermissionInterface` yourself
and alias it in `services.yaml`:

```yaml
services:
    Polysource\Core\Permission\PermissionInterface:
        alias: App\Polysource\MyOwnPermissionChecker
```

Polysource will route every authorisation question through your
implementation. No Security firewall needed in that case.

## What about CSRF?

CSRF is checked by `ActionController` independently of the permission
gate. Every action POST request must carry a token whose name matches
`{resourceSlug}_{actionName}` (or `{resourceSlug}_bulk_{actionName}`
for bulk routes). The token is rendered into the action button's
hidden input by the bundled Twig templates — there's nothing for you
to do unless you build your own form.

## What about rate limiting?

Polysource does not rate-limit actions. If your action is expensive
(retry-all over a hot transport), wrap it with Symfony's
[Rate Limiter](https://symfony.com/doc/current/rate_limiter.html) at
the action level. The Messenger adapter exposes hard caps
(`max_retry_all`, `max_purge`, default 1000) which prevent runaway
clicks but don't replace a proper rate limiter for shared transports.

## See also

- [resource.md](./resource.md) — `getPermission()` is one of seven
  resource declarations.
- [field.md](./field.md) — `setPermission()` per field.
- [action.md](./action.md) — `getPermission()` per action.
- [../cookbook/permissions-with-roles.md](../cookbook/permissions-with-roles.md)
  — a complete role-to-attribute mapping table.
