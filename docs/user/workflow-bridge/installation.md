# Installing `polysource/workflow-bridge`

## 1. Composer

```bash
composer require polysource/workflow-bridge
```

The package hard-requires `symfony/workflow` — apps that don't
have it yet will install it as a transitive dependency.

## 2. Register the bundle

`config/bundles.php`:

```php
return [
    // …existing bundles…
    Polysource\WorkflowBridge\PolysourceWorkflowBridgeBundle::class => ['all' => true],
];
```

Discoverable via `bin/console polysource:plugins:list`.

## 3. Configure your Workflows

This bundle assumes your workflows are already registered the
standard Symfony way under `framework.workflows`:

```yaml
framework:
    workflows:
        order:
            type: state_machine
            marking_store:
                type: 'method'
                property: 'state'
            supports:
                - App\Entity\Order
            initial_marking: draft
            places: [draft, paid, cancelled, refunded]
            transitions:
                pay:
                    from: draft
                    to: paid
                cancel:
                    from: draft
                    to: cancelled
                refund:
                    from: paid
                    to: refunded
```

The bridge consumes the registered Workflow as-is — guards, marking
stores, listeners all keep working.

## 4. Configure the chip palette

`config/packages/polysource_workflow_bridge.yaml`:

```yaml
polysource_workflow_bridge:
    palettes:
        order:
            draft:     secondary
            paid:      success
            cancelled: danger
            refunded:  warning
            '*':       info       # wildcard fallback
```

Palette slugs are Bootstrap contextual classes without the
`text-bg-` prefix. When a state has no exact mapping AND no
wildcard, the chip falls back to `secondary` (Bootstrap's neutral
default).

## 5. Permission gates

Each generated transition action declares
`POLYSOURCE_WORKFLOW_TRANSITION_<UPPER_NAME>` as its permission
attribute. Wire a voter:

```php
// src/Security/WorkflowVoter.php
final class WorkflowVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'POLYSOURCE_WORKFLOW_TRANSITION_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        return match ($attribute) {
            'POLYSOURCE_WORKFLOW_TRANSITION_REFUND' => \in_array('ROLE_FINANCE', $token->getRoleNames(), true),
            'POLYSOURCE_WORKFLOW_TRANSITION_CANCEL' => \in_array('ROLE_OPS', $token->getRoleNames(), true),
            default => \in_array('ROLE_ADMIN', $token->getRoleNames(), true),
        };
    }
}
```

Or — simpler — extend a blanket `POLYSOURCE_*` voter (the
messenger-demo pattern).

## 6. Smoke-test

```bash
bin/console polysource:plugins:list
# Expect: polysource/workflow-bridge   1.1.0
```

## See also

- [walkthrough.md](./walkthrough.md) — once it's installed.
- [extending.md](./extending.md) — palettes, multi-tenant
  workflows, host-side extension points.
- [ADR-021](../../adr/0021-symfony-workflow-bridge.md) — design
  rationale.
