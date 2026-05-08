# ADR-021 — Symfony Workflow bridge (Phase 13)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 13 — troisième capability ADR-017 cherry-picks
- **En lien avec** : [ADR-005 — Configuration mechanism](./0005-configuration-mechanism.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — `AdminPluginInterface` + public contracts](./0018-admin-plugin-interface-and-public-contracts.md), [ADR-020 — Audit non-Doctrine actions](./0020-audit-non-doctrine-actions.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §6 retient
**Symfony Workflow integration** parmi les 7 features Phase 10+ : si
une ressource a un `Workflow` Symfony attaché (états + transitions),
Polysource doit générer automatiquement les actions inline de
transition + afficher l'état courant en chip — sans que l'utilisateur
ait à câbler une `InlineActionInterface` par transition.

L'écosystème Symfony admin actuel (EasyAdmin, Sonata, API Platform)
ne gère pas cette intégration nativement : les développeurs hand-roll
des actions par-Workflow, dupliquent la logique de garde, et perdent
la visibilité sur l'état courant dans la liste. Filament (Laravel)
ship `BadgeColumn` + `Action::form()` pour ce cas mais reste tied à
la librairie `spatie/laravel-model-states`.

Symfony Workflow est dans le core Symfony depuis 4.1 — pas de question
de tirer une dépendance "exotique". Le gating sur la disponibilité de
la Workflow component reste cohérent avec ADR-018 : pas dans le
hub central.

## Décision

### 1. Package de livraison : nouveau `polysource/workflow-bridge`

Comme ADR-020 / audit, c'est une capability transversale plug-in. Vit
dans son propre package :

```
polysource/workflow-bridge
├── src/
│   ├── PolysourceWorkflowBridgeBundle.php       # #[AsPlugin]
│   ├── DependencyInjection/
│   │   └── PolysourceWorkflowBridgeExtension.php
│   ├── Action/
│   │   ├── ApplyTransitionAction.php             # one instance per transition
│   │   └── TransitionActionFactory.php           # builds them lazily
│   ├── Resource/
│   │   ├── WorkflowAwareInterface.php            # opt-in marker
│   │   └── WorkflowAwareTrait.php                # default implementation
│   ├── Service/
│   │   ├── WorkflowResolver.php                  # finds the Workflow for a record
│   │   └── TransitionDiscovery.php               # enumerates enabled transitions
│   └── Twig/
│       └── WorkflowChipExtension.php             # workflow_state_chip()
├── Resources/
│   ├── config/services.php
│   ├── views/_state_chip.html.twig
│   └── translations/PolysourceWorkflowBridge.{en,fr}.yaml
└── composer.json
```

Justification du package séparé :
- `polysource/symfony-bundle` ne tire pas `symfony/workflow` par
  défaut.
- Hosts qui n'utilisent pas Workflow (ressources read-only :
  Messenger failed, audit log) ne paient ni la DI ni le runtime.
- Future `polysource/saga-bridge` (Phase 17+ : long-running async
  flows via Workflow + Mercure) consomme les mêmes primitives.

### 2. Opt-in : `WorkflowAwareInterface` côté resource

Pas d'auto-detection magique. Une ressource déclare explicitement
qu'elle est workflow-driven :

```php
namespace Polysource\WorkflowBridge\Resource;

interface WorkflowAwareInterface
{
    /**
     * The Symfony Workflow name registered in framework.workflows.
     * Returns null when the workflow can't be resolved at config
     * time (e.g. multi-tenant apps that pick the workflow per
     * record) — the resolver will fall back to runtime resolution.
     */
    public function getWorkflowName(): ?string;

    /**
     * Property on the underlying record holding the current place.
     * Default convention: `state`. Hosts override for legacy schemas.
     */
    public function getStatePropertyName(): string;
}
```

Le trait `WorkflowAwareTrait` fournit la default impl :

```php
trait WorkflowAwareTrait
{
    public function getWorkflowName(): ?string
    {
        return null; // host must override or rely on runtime resolution
    }

    public function getStatePropertyName(): string
    {
        return 'state';
    }
}
```

Justification opt-in :
- Une ressource qui ne déclare pas le marker ne change pas de
  comportement — zéro régression pour les hosts existants.
- Permet à un même bundle d'avoir des ressources mixtes (workflow +
  non-workflow).

### 3. Découverte des transitions

`TransitionDiscovery` introspecte le Symfony Workflow registry au
runtime pour chaque record :

```php
final class TransitionDiscovery
{
    public function __construct(
        private readonly WorkflowResolver $resolver,
    ) {
    }

    /**
     * @return list<Transition> les transitions actuellement
     *                          activables pour ce record
     */
    public function enabledFor(WorkflowAwareInterface $resource, mixed $record): array
    {
        $workflow = $this->resolver->resolve($resource, $record);
        if (null === $workflow) {
            return [];
        }

        return iterator_to_array($workflow->getEnabledTransitions($record), false);
    }
}
```

Important : on délègue à `Workflow::getEnabledTransitions()` qui
applique déjà les guards (`workflow.<name>.guard.<transition>`
events). Pas de re-implémentation de la logique de garde.

### 4. Génération des actions : `TransitionActionFactory`

Pour chaque transition activable, on génère une instance
d'`ApplyTransitionAction` :

```php
final class TransitionActionFactory
{
    public function __construct(
        private readonly WorkflowResolver $resolver,
        private readonly TransitionDiscovery $discovery,
    ) {
    }

    /**
     * @return list<ApplyTransitionAction>
     */
    public function buildFor(WorkflowAwareInterface $resource, mixed $record): array
    {
        $actions = [];
        foreach ($this->discovery->enabledFor($resource, $record) as $transition) {
            $actions[] = new ApplyTransitionAction(
                workflow: $this->resolver->resolve($resource, $record),
                transition: $transition,
            );
        }

        return $actions;
    }
}
```

Une `WorkflowAwareInterface::configureActions()` peut alors yield
ces actions via la factory injectée.

### 5. `ApplyTransitionAction` — `InlineActionInterface`

```php
final class ApplyTransitionAction implements InlineActionInterface
{
    public function __construct(
        private readonly WorkflowInterface $workflow,
        private readonly Transition $transition,
    ) {
    }

    public function getName(): string
    {
        return 'transition-' . $this->transition->getName();
    }

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->transition->getName()));
    }

    public function getIcon(): ?string
    {
        return 'arrow-right-circle';
    }

    public function getPermission(): ?string
    {
        return 'POLYSOURCE_WORKFLOW_TRANSITION_' . strtoupper($this->transition->getName());
    }

    public function isDisplayed(array $context = []): bool
    {
        $record = $context['record'] ?? null;
        return null !== $record && $this->workflow->can($record, $this->transition->getName());
    }

    public function execute(DataRecord $record): ActionResult
    {
        $subject = $record->rawSource;
        if (null === $subject) {
            return ActionResult::failure('Cannot apply transition: record has no rawSource subject.');
        }

        try {
            $this->workflow->apply($subject, $this->transition->getName());

            return ActionResult::success(\sprintf(
                'Transition "%s" applied.',
                $this->transition->getName(),
            ));
        } catch (TransitionException $e) {
            return ActionResult::failure(\sprintf(
                'Transition "%s" rejected: %s',
                $this->transition->getName(),
                $e->getMessage(),
            ));
        }
    }
}
```

Choix :
- Permission `POLYSOURCE_WORKFLOW_TRANSITION_<NAME>` — granulaire
  par transition, hosts wirent un voter.
- `TransitionException` est gracefully converti en
  `ActionResult::failure()` — l'audit log (ADR-020) trace l'échec
  comme `outcome=failure` plutôt qu'`exception` (la transition a
  été *rejetée*, pas crashée).
- `rawSource` du `DataRecord` est l'objet domaine. Convention
  ADR-001 : le DataRecord transporte l'objet original sous
  `$rawSource`. Les actions Workflow nécessitent cet objet
  (Symfony Workflow ne sait pas appliquer une transition sur une
  representation tabular).

### 6. Twig — `workflow_state_chip(record)`

```twig
{# Resources/views/_state_chip.html.twig #}
{% set state = record.properties[statePropertyName] ?? '?' %}
{% set palette = polysource_workflow_chip_palette(workflowName, state) %}
<span class="badge text-bg-{{ palette }} polysource-workflow-chip">
    {{ state|trans({}, 'PolysourceWorkflowBridge') }}
</span>
```

L'extension Twig fournit deux helpers :
- `workflow_state_chip(record)` — render le chip avec auto-detection
  de la palette
- `polysource_workflow_chip_palette(workflowName, state)` — retourne
  le slug Bootstrap (`success`, `warning`, …) pour un état donné

Configuration de la palette via `services.php` ou config bundle :

```yaml
polysource_workflow_bridge:
    palettes:
        order:
            draft: secondary
            paid: success
            cancelled: danger
            refunded: warning
```

Defaults : `success` pour les états terminaux positifs, `danger`
pour les terminaux négatifs, `secondary` sinon. Les hosts overrident
au cas par cas.

### 7. Intégration audit (ADR-020)

Aucun code spécifique requis : l'`ApplyTransitionAction` passe par
`ActionController::safelyRun()` comme toute autre action,
l'`ActionAuditSubscriber` capture l'event, l'`AuditEntry` stamp
contient :
- `actionName = transition-<name>`
- `recordIds = [recordId]`
- `outcome = success | failure | exception`
- `context.actionContext` peut contenir l'état avant/après si
  l'action enrichit le `ActionResult::context`

Hosts qui veulent stamp l'état before/after dans le context ajoutent
un listener sur `ActionExecutedEvent` ou enrichissent le
`ActionResult` côté `ApplyTransitionAction`. Pour v0.1, on garde le
contract minimal — le simple fait que la transition soit dans
l'action_name suffit pour les requêtes Art. 30 ("toutes les
transitions vers `cancelled` sur les commandes ce mois-ci").

### 8. Plugin (ADR-018)

`PolysourceWorkflowBridgeBundle` implémente `AdminPluginInterface`
via `HasPluginMetadata` + `#[AsPlugin]` (même convention que filter
+ audit). Apparaît dans `polysource:plugins:list`.

## Conséquences

### Positives

- Audience régulée + flux métier complexes débloquée : ecommerce
  (commandes), CRM (deals), HR (onboarding), claims management,
  etc. — autant de cas où Workflow est déjà l'abstraction Symfony
  privilégiée.
- Réutilisation **complète** de Symfony Workflow : guards, marking
  store, listeners — tout ce que les hosts ont déjà câblé continue
  de marcher.
- Pas de duplication de logique : on délègue
  `getEnabledTransitions()` au runtime Workflow.
- L'audit log (ADR-020) trace gratuitement chaque transition.

### Négatives / coûts

- Nouveau package à maintenir (composer, CI matrix, traductions
  EN/FR, docs).
- Dépendance optionnelle sur `symfony/workflow` (^5.4 || ^6.0 ||
  ^7.0 || ^8.0) — non pulled pour les hosts qui n'en ont pas.
- L'API `getEnabledTransitions()` est runtime-cher pour les listes
  longues : `O(rows × transitions)` calls vers
  `Workflow::can()`. Mitigation : cache par-record dans le
  resolver, ou rendering paresseux des actions seulement quand
  l'utilisateur ouvre le menu déroulant inline. À shipper en
  v0.2 si performance devient un sujet (mesure d'abord).

### Risques mitigés

- **Marking store ambiguity** : Symfony Workflow supporte plusieurs
  stores (property, single state, multiple states). Notre adapter
  utilise uniquement l'API `Workflow::getMarking()` /
  `Workflow::apply()` qui abstrait le store — agnostique par
  construction.
- **Transition rejetée silencieusement** : `Workflow::apply()`
  throw `TransitionException` si le record n'autorise pas la
  transition (guard refus, race condition). Notre `safelyRun`
  capture et trace via l'audit. Le user voit un flash explicite.
- **Granularité permission** : permission par transition est
  verbose. Hosts qui préfèrent un blanket `POLYSOURCE_WORKFLOW`
  shippent un voter qui prefix-match — comme le
  `PolysourceAdminVoter` du messenger-demo.

## Plan d'implémentation (Phase 13)

| Batch | Tâches | Test target |
|---|---|---|
| **A** | `WorkflowAwareInterface` + `WorkflowAwareTrait` + `WorkflowResolver` + `TransitionDiscovery` | Unit |
| **B** | `ApplyTransitionAction` + `TransitionActionFactory` | Unit + Functional |
| **C** | `WorkflowChipExtension` Twig + `_state_chip.html.twig` template + palette config | Unit + integration |
| **D** | `PolysourceWorkflowBridgeBundle` + extension + services.php gating + plugin manifest | Functional |
| **E** | `docs/user/workflow-bridge/` : install, walkthrough, extending | Docs |

Estimation : **~2 semaines** (plus rapide que ADR-019 et ADR-020 :
pas de DB, on délègue au runtime Workflow).

## Alternatives rejetées

### A. Auto-detection sans opt-in

Inspecter chaque ressource pour voir si elle a un Workflow
applicable et générer les actions implicitement. Rejeté car :
- Magie qui surprend les hosts (les actions apparaissent sans
  qu'ils l'aient demandé).
- Workflow Symfony peut s'appliquer à plusieurs classes (l'option
  `supports`) — ambiguïté qu'il faut résoudre via opt-in.

### B. Action unique avec dropdown des transitions

Une seule `ChooseTransitionAction` qui demande à l'utilisateur
quelle transition appliquer via un select. Rejeté car :
- Granularité permission perdue.
- L'audit log devient flou (`actionName=choose-transition` ne
  permet pas de filtrer).
- L'UI moins immédiate que des boutons par transition.

### C. Décorateur sur `ActionInterface` plutôt que classe dédiée

Wrapper qui prend une action existante et lui colle un guard
Workflow. Rejeté car les transitions sont des actions *de plein
droit*, pas des décorations d'actions existantes.

### D. Stocker la palette sous la forme d'un tableau Twig in-template

Plutôt que via DI / config. Rejeté car les palettes sont host-specific
et la conf YAML est plus auditable (compliance + multi-env).

## Migration / breaking changes

Aucun. ADR-021 ajoute :
- 1 nouveau package `polysource/workflow-bridge` (opt-in pur).
- Aucune modif de `polysource/symfony-bundle` — l'audit (ADR-020)
  fournit déjà les events nécessaires.

## Suite (post-v0.1)

- v0.2 : caching des transitions enabled par record dans le
  resolver pour les listes longues.
- v0.2 : `before-transition` form pour les transitions qui
  demandent un commentaire (cf. ADR-017 §6 mention des forms).
- v0.3 : intégration Mercure → broadcaster les changements d'état
  en SSE pour les UIs collaboratives (cf. ADR-017 §4 bulk async).
- v0.4 : visualisation du Workflow (graphviz / Mermaid) dans la
  detail page d'un record — pour le debug / training des
  opérateurs.
