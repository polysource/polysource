# Cookbook — Permissions with roles

Polysource attributes are arbitrary strings (`POLYSOURCE_FAILED_MESSAGE`,
`POLYSOURCE_FAILED_MESSAGE_RETRY`, …). They are **not** Symfony roles
and the default `RoleHierarchyVoter` abstains on them. This cookbook
shows three patterns for granting them, in increasing granularity:

1. [All-or-nothing](#1-all-or-nothing) — every admin can do everything.
2. [Two roles, two scopes](#2-two-roles-two-scopes) — one role retries,
   another purges.
3. [Per-attribute decision matrix](#3-per-attribute-decision-matrix) —
   a small table maps each attribute to the roles allowed.

Pick the lowest-granularity pattern that matches your team. Don't
over-engineer authorisation up front.

## Prerequisites

A working dashboard from
[messenger-failed-dashboard.md](./messenger-failed-dashboard.md) and
the four `POLYSOURCE_FAILED_MESSAGE*` attributes documented in
[../adapters/messenger.md → Permission attributes](../adapters/messenger.md#permission-attributes).

## 1. All-or-nothing

This is the voter `messenger-failed-dashboard.md` already shipped, for
reference:

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
        return \in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}
```

Use this when every operator who can log in to the admin should be
able to invoke every action. It's the right default for small teams
with one ops role.

## 2. Two roles, two scopes

Goal: anyone in `ROLE_ON_CALL` can view and retry; only `ROLE_SRE` can
dismiss and purge.

```php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PolysourceFailedMessageVoter extends Voter
{
    private const VIEW_AND_RETRY = [
        'POLYSOURCE_FAILED_MESSAGE',
        'POLYSOURCE_FAILED_MESSAGE_RETRY',
    ];

    private const DESTRUCTIVE = [
        'POLYSOURCE_FAILED_MESSAGE_DISMISS',
        'POLYSOURCE_FAILED_MESSAGE_PURGE',
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [...self::VIEW_AND_RETRY, ...self::DESTRUCTIVE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $roles = $token->getRoleNames();

        if (\in_array($attribute, self::VIEW_AND_RETRY, true)) {
            return \in_array('ROLE_ON_CALL', $roles, true)
                || \in_array('ROLE_SRE', $roles, true);
        }

        // DESTRUCTIVE
        return \in_array('ROLE_SRE', $roles, true);
    }
}
```

In `security.yaml`, make sure your user provider yields `ROLE_ON_CALL`
and `ROLE_SRE`. Symfony's `role_hierarchy` can still help here for the
`ROLE_*` parts:

```yaml
security:
    role_hierarchy:
        ROLE_SRE: [ROLE_ON_CALL]   # an SRE is implicitly on-call
```

That hierarchy applies to `ROLE_*` attributes only — it doesn't reach
the `POLYSOURCE_*` attributes, which is exactly why you need the voter
above.

## 3. Per-attribute decision matrix

For larger teams with multiple operational roles, encode the policy as
a static map. This makes the policy auditable in one place.

```php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PolysourceMatrixVoter extends Voter
{
    /**
     * Map from Polysource attribute to the roles that may invoke it.
     * Add a row when you add a new resource or action.
     */
    private const POLICY = [
        'POLYSOURCE_FAILED_MESSAGE'                  => ['ROLE_ON_CALL', 'ROLE_SRE'],
        'POLYSOURCE_FAILED_MESSAGE_RETRY'            => ['ROLE_ON_CALL', 'ROLE_SRE'],
        'POLYSOURCE_FAILED_MESSAGE_RETRY_DELAYED'    => ['ROLE_SRE'],
        'POLYSOURCE_FAILED_MESSAGE_DISMISS'          => ['ROLE_SRE'],
        'POLYSOURCE_FAILED_MESSAGE_PURGE'            => ['ROLE_SRE_LEAD'],
        'POLYSOURCE_FEATURE_FLAG'                    => ['ROLE_PRODUCT', 'ROLE_SRE'],
        'POLYSOURCE_FEATURE_FLAG_TOGGLE'             => ['ROLE_PRODUCT'],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::POLICY[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $allowedRoles = self::POLICY[$attribute] ?? [];
        $userRoles = $token->getRoleNames();

        return [] !== array_intersect($allowedRoles, $userRoles);
    }
}
```

Pros: one file to read for the entire authorisation policy, easy to
unit-test, easy to diff in code review.

Cons: every new attribute requires touching this file. That is an
acceptable trade at realistic scale — a Polysource admin has well
under 30 attributes.

## Verifying the voter wins

Symfony's default access strategy is **`affirmative`**: a single voter
returning `true` grants access. Your `Polysource*Voter` therefore takes
priority over abstaining default voters.

To debug authorisation decisions:

```bash
bin/console debug:autowiring 'security.access.decision_manager'
bin/console debug:container --tag=security.voter
```

In a controller or in `bin/console`:

```php
$decisionManager->decide($token, ['POLYSOURCE_FAILED_MESSAGE_PURGE']);
```

Symfony's `debug:firewall` command also dumps the active firewall +
voters per URL pattern, useful when `/admin` doesn't get the
authorisation chain you expect.

## Testing the voter

```php
public function test_sre_lead_can_purge(): void
{
    $voter = new PolysourceMatrixVoter();
    $token = $this->createMock(TokenInterface::class);
    $token->method('getRoleNames')->willReturn(['ROLE_SRE_LEAD']);

    $result = $voter->vote($token, null, ['POLYSOURCE_FAILED_MESSAGE_PURGE']);
    self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
}

public function test_on_call_cannot_purge(): void
{
    $voter = new PolysourceMatrixVoter();
    $token = $this->createMock(TokenInterface::class);
    $token->method('getRoleNames')->willReturn(['ROLE_ON_CALL']);

    $result = $voter->vote($token, null, ['POLYSOURCE_FAILED_MESSAGE_PURGE']);
    self::assertSame(VoterInterface::ACCESS_DENIED, $result);
}
```

## Replacing `PermissionInterface` outright

If your app doesn't use Symfony Security at all (you build on top of
a custom token, an SSO gateway, an external policy service), you can
sidestep voters entirely by aliasing `PermissionInterface`:

```yaml
services:
    Polysource\Core\Permission\PermissionInterface:
        alias: App\Polysource\OpaPermissionChecker
```

Your implementation receives the attribute string and a subject; you
answer however you like — call OPA, query a table, look up an LDAP
group. Polysource never enforces a Symfony-shaped policy.

## See also

- [../concepts/permission.md](../concepts/permission.md) — the
  underlying contract and the fail-closed Security firewall check.
- [../adapters/messenger.md → Permission attributes](../adapters/messenger.md#permission-attributes)
  — which attributes the Messenger adapter advertises.
- [adding-a-custom-action.md](./adding-a-custom-action.md) — declaring
  a new action and its attribute.
