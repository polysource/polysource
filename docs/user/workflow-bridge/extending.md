# Extending `polysource/workflow-bridge`

Defaults cover the 80% case (Symfony Workflow registry + Bootstrap
chips). This page is the seam catalogue for the rest.

## Multi-tenant workflows

If different tenants run different workflows on the same domain
class, your resource returns `null` from `getWorkflowName()` and
the bridge falls back to `Registry::get($subject)` — Symfony
Workflow then picks whichever workflow's `supports()` matches the
record at runtime.

```php
public function getWorkflowName(): ?string
{
    return null;
}
```

The pragmatic pattern is one workflow class per tenant + a
tenant-aware `SupportStrategyInterface`.

## Custom palette beyond Bootstrap

The chip template hardcodes Bootstrap `text-bg-<slug>`. Hosts on
Tailwind / custom CSS override the partial:

```twig
{# templates/bundles/PolysourceWorkflowBridgeBundle/_state_chip.html.twig #}
{%- set palette = polysource_workflow_chip_palette(workflow, state) -%}
{%- set label = polysource_workflow_state_label(state)|trans({}, 'PolysourceWorkflowBridge') -%}
<span class="px-2 py-1 rounded-full text-xs font-medium bg-{{ palette }}-100 text-{{ palette }}-800">
    {{- label -}}
</span>
```

The palette slugs are still strings — you map them to your
design system however you like.

## Before-transition forms

The bridge ships a fire-and-forget button; there is no
`BeforeTransitionFormInterface`, and Polysource ships no form UI at
all (see [../concepts/resource.md](../concepts/resource.md)). To
collect input before applying a transition, ship a wrapper action
that renders your own form step and applies the transition itself,
plus a guard that hides the auto-generated `transition-<name>`
button.

## Mercure broadcast

Listen on `ActionExecutedEvent` (from `polysource/symfony-bundle`,
ADR-020) and publish to Mercure when the action name starts with
`transition-`:

```php
final class WorkflowMercureBroadcaster implements EventSubscriberInterface
{
    public function __construct(private readonly HubInterface $hub) {}

    public static function getSubscribedEvents(): array
    {
        return [ActionExecutedEvent::class => 'onActionExecuted'];
    }

    public function onActionExecuted(ActionExecutedEvent $event): void
    {
        if (!str_starts_with($event->action->getName(), 'transition-')) {
            return;
        }

        $this->hub->publish(new Update(
            'polysource/workflow/' . $event->resource->getName(),
            json_encode([
                'recordIds' => $event->recordIds,
                'transition' => substr($event->action->getName(), 11),
                'outcome' => $event->result->success ? 'success' : 'failure',
            ], \JSON_THROW_ON_ERROR) ?: '{}',
        ));
    }
}
```

The subscriber above is the whole story: there is no
`polysource/workflow-bridge-mercure` add-on, and no JS client ships.
(`polysource/bulk-async` does wire Mercure for job progress, if you
want a reference implementation to read.)

## What the bridge does *not* do

None of these ships, and none is on the roadmap — each is a
host-side extension you write on top of the contracts above:

- Workflow visualisation in the admin UI (Graphviz / Mermaid
  embedded in the detail page).
- Per-transition required forms.
- Mercure broadcast as a first-class feature.

## See also

- [ADR-021](../../adr/0021-symfony-workflow-bridge.md) §"Suite (post-v0.1)"
- [ADR-020](../../adr/0020-audit-non-doctrine-actions.md) — audit
  events the workflow listener consumes.
- [walkthrough.md](./walkthrough.md) — first-hour UX flow.
