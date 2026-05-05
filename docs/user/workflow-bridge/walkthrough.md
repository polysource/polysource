# Workflow bridge walkthrough — first hour

After [installation](./installation.md), this page walks you from
"my Workflow is registered" to "my admin shows the state chip and
auto-generated transition buttons".

## 1. Make your resource workflow-aware

Your domain object already has a `state` property — that's how
Symfony Workflow works. The Polysource resource exposing this
domain object opts in:

```php
namespace App\Polysource;

use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Resource\AbstractResource;
use Polysource\WorkflowBridge\Action\TransitionActionFactory;
use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Polysource\WorkflowBridge\Resource\WorkflowAwareTrait;

#[AsResource]
final class OrderResource extends AbstractResource implements WorkflowAwareInterface
{
    use WorkflowAwareTrait;

    public function __construct(
        OrderDataSource $dataSource,
        private readonly TransitionActionFactory $factory,
    ) {
        parent::__construct($dataSource);
    }

    public function getName(): string { return 'orders'; }
    public function getLabel(): string { return 'Orders'; }
    public function getIdentifierProperty(): string { return 'id'; }
    public function getPermission(): ?string { return 'POLYSOURCE_ORDER_VIEW'; }

    public function getWorkflowName(): ?string
    {
        return 'order';
    }

    public function configureFields(string $page): iterable { return []; }

    public function configureActions(): iterable
    {
        // The factory yields one ApplyTransitionAction per transition
        // currently activable on the *current* record. Polysource
        // calls this with $record set per row, so the visible
        // buttons always match the row's state.
    }
}
```

## 2. Render the chip

In your resource's index / detail Twig template:

```twig
{# templates/orders/index.html.twig #}
{% for record in records %}
    <tr>
        <td>{{ record.identifier }}</td>
        <td>{{ record.properties.customer_email }}</td>
        <td>
            {{ include('@PolysourceWorkflowBridge/_state_chip.html.twig', {
                workflow: 'order',
                state: record.properties.state,
            }) }}
        </td>
    </tr>
{% endfor %}
```

The chip's Bootstrap class comes from your palette config. If a
state has no mapping, it falls back to `secondary`.

## 3. Trigger a transition

The action button group on each row now shows one button per
transition activable on that row's state. Click "Pay" on a draft
order → `Workflow::apply($order, 'pay')` runs. On success the
flash bag carries `Transition "pay" applied.` and the row's chip
flips from `Draft` to `Paid`.

If the transition is rejected (guard blocks it, race condition):
- The flash shows `Transition "pay" rejected: <reason>`.
- The audit log records `outcome = failure` (NOT `exception` —
  rejection is a graceful business outcome).

## 4. Audit traceability

If you've installed `polysource/audit`, every transition is
captured automatically. No extra wiring. Each audit row has:

| Column | Value |
|---|---|
| `action_name` | `transition-pay` |
| `resource_name` | `orders` |
| `outcome` | `success` or `failure` (or `exception` for crashes) |
| `record_ids` | `["<order id>"]` |
| `actor_id` | the user who clicked the button |
| `context.ip` / `context.requestId` | request metadata |

Filter `/admin/audit-log?filter[actionName][operator]=in&filter[actionName][values][0]=transition-cancel`
to see every cancellation. Compliance officers ask for this exact
filter quarterly.

## 5. Permission gates per transition

Voters that prefix-match `POLYSOURCE_WORKFLOW_TRANSITION_*` make
granular access trivial: "only finance can refund, only ops can
cancel". The framework hides buttons the user isn't allowed to
click — so the audit log doesn't fill with `outcome = failure`
permission denials.

## 6. Inspect transitions from the CLI

Symfony Workflow's `workflow:dump` command still works:

```bash
bin/console workflow:dump order | dot -Tsvg -o order.svg
```

This is the source of truth for the transition graph. The bridge
just exposes it in the admin UI.

## See also

- [installation.md](./installation.md) — wiring basics.
- [extending.md](./extending.md) — multi-tenant workflows, palette
  fallbacks, future hooks.
- [ADR-021](../../adr/0021-symfony-workflow-bridge.md) — design
  rationale.
