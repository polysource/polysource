# polysource/workflow-bridge

> Symfony Workflow integration for Polysource — auto-generated transition buttons + state chip per resource.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## When to use

You're modelling an ecommerce / CRM / claims / HR / regulated workflow with `symfony/workflow` and want the admin UI to expose only the transitions actually enabled by the workflow guards. This package wraps Symfony's `Workflow\Registry` into auto-generated Polysource actions and a state chip Twig helper.

See [ADR-021](../../docs/adr/0021-workflow-bridge.md). Separate package so `polysource/symfony-bundle` doesn't pull `symfony/workflow` for hosts that don't use it.

## What it ships

- **`WorkflowAwareInterface`** + **`WorkflowAwareTrait`** — opt-in marker for resources.
- **`WorkflowResolver`** — wraps Symfony Workflow `Registry` with graceful null-on-failure.
- **`TransitionDiscovery`** — delegates to `Workflow::getEnabledTransitions()` (guards natifs Symfony intacts).
- **`ApplyTransitionAction`** — auto-generated per transition, granular permission `POLYSOURCE_WORKFLOW_TRANSITION_<UPPER>`, `TransitionException` → `ActionResult::failure`.
- **`WorkflowChipPalette`** + **`WorkflowChipExtension`** Twig (`polysource_workflow_chip_palette()` + `polysource_workflow_state_label()`).
- `_state_chip.html.twig` partial Bootstrap.
- Config `polysource_workflow_bridge.palettes.<workflow>.<state>` with wildcard fallback.

Audit log (ADR-020) traces every transition for free (`actionName=transition-<name>`).

## Install

```bash
composer require polysource/workflow-bridge symfony/workflow
```

Register the bundle:

```php
return [
    Polysource\WorkflowBridge\PolysourceWorkflowBridgeBundle::class => ['all' => true],
];
```

## Documentation

- [Workflow-bridge walkthrough](../../docs/user/workflow-bridge/)
