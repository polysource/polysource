# ADR-024 — Bulk async + Mercure (Phase 16)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 16 — sixième et dernière capability ADR-017 cherry-picks
- **En lien avec** : [ADR-005 — `#[AsResource]` + actions](./0005-configuration-mechanism.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — Plugin architecture](./0018-admin-plugin-interface-and-public-contracts.md), [ADR-020 — Audit non-Doctrine actions](./0020-audit-non-doctrine-actions.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §4 retient
**bulk async + Mercure** parmi les 7 features Phase 10+ : exécuter
les bulk actions en arrière-plan via Symfony Messenger, suivre la
progression via Mercure SSE, exposer un dashboard `BulkJobs` pour
les opérateurs.

Aujourd'hui dans `polysource/symfony-bundle`, `BulkActionInterface::executeBatch()`
tourne **synchroniquement** dans le request-response cycle. Pour
les bulk actions sur 5/10/100 records ça passe; pour 5000+ records
(retry-all, purge-failed-messages, mass-update) le timeout HTTP
hit avant la fin et l'opérateur n'a aucune visibilité sur l'état.

Filament (Laravel) ship un `BulkAction::async()` modifier qui fait
exactement ça: dispatch un job + progress notifications. Symfony
admin ecosystem n'a rien d'équivalent.

## Décision

### 1. Package séparé `polysource/bulk-async`

Comme audit / workflow-bridge / widgets / search : opt-in. Vit
dans son propre package pour ne pas tirer Symfony Messenger /
Mercure dans `polysource/symfony-bundle`.

```
polysource/bulk-async
├── src/
│   ├── PolysourceBulkAsyncBundle.php
│   ├── DependencyInjection/PolysourceBulkAsyncExtension.php
│   ├── Job/
│   │   ├── BulkJob.php                         # immutable VO
│   │   ├── BulkJobStatus.php                   # enum
│   │   ├── BulkJobStorageInterface.php
│   │   ├── DoctrineBulkJobStorage.php
│   │   └── Doctrine/BulkJobRecord.php
│   ├── Messenger/
│   │   ├── BulkJobMessage.php
│   │   └── BulkJobHandler.php
│   ├── Dispatcher/AsyncBulkActionDispatcher.php
│   ├── Resource/BulkJobResource.php
│   ├── Controller/ProgressController.php
│   ├── Mercure/MercureBulkJobBroadcaster.php   # gated optional
│   └── Twig/BulkProgressExtension.php
├── Resources/
│   ├── config/services.php
│   ├── views/_progress.html.twig
│   └── translations/PolysourceBulkAsync.{en,fr}.yaml
├── assets/controllers/progress_controller.js
└── composer.json
```

### 2. `BulkJob` VO

Champs : `id` (UUID v7), `createdAt` (UTC), `resourceName`,
`actionName`, `actorId`, `recordIds` (list<string>), `status`
(BulkJobStatus), `processedCount`, `failedCount`, `startedAt?`,
`completedAt?`, `errorMessage?` (8 KiB cap). Helpers `total()`
+ `progress()`.

### 3. `BulkJobStatus` enum

`Pending` (dispatched), `Running`, `Completed` (all done, 0
failures), `Failed` (terminal but ≥1 record failed), `Cancelled`
(operator stopped before completion).

### 4. `BulkJobStorageInterface` + `DoctrineBulkJobStorage`

Convention identique à ADR-019 (saved views) et ADR-020 (audit) :
contrat minimal, default Doctrine impl gated sur
`interface_exists(EntityManagerInterface)`.

Table `polysource_bulk_jobs` :

| Column | Type |
|---|---|
| `id` | varchar(36) PK |
| `created_at` | datetime_immutable, indexed |
| `resource_name` | varchar(120), indexed |
| `action_name` | varchar(120) |
| `actor_id` | varchar(120), indexed |
| `record_ids_json` | text |
| `status` | varchar(16), indexed |
| `processed_count` | integer |
| `failed_count` | integer |
| `started_at` | datetime_immutable nullable |
| `completed_at` | datetime_immutable nullable |
| `error_message` | text nullable |

Indexes : `(created_at)`, `(actor_id, created_at)`, `(status)`.

### 5. Messenger pipeline

`BulkJobMessage(jobId)` → `BulkJobHandler::__invoke()` :
1. Re-fetch job, exit if not Pending (re-delivery safety).
2. Mark Running + startedAt.
3. Resolve action service + DataSource records.
4. Loop: re-fetch job each iteration to honour Cancelled status.
5. Run `executeBatch([$record])` 1-by-1 for fine-grained progress.
6. Persist progress every 5 records OR every 500ms (throttled).
7. Mark Completed/Failed + completedAt at end.

Choix :
- **1-by-1 dispatch dans le handler** : permet une progression
  fine au prix d'overhead. Hosts qui veulent batch interne (ex.
  100-by-100) ship leur propre handler.
- **Cancellation respect** : re-fetch le job à chaque tour.
- **Progress flush throttling** : évite 10k INSERTs sur un job
  10k records.

### 6. `AsyncBulkActionDispatcher` — host-facing API

Hosts l'utilisent depuis leur action controller :

```php
$result = $action->isAsync($recordCount)
    ? $this->asyncDispatcher->dispatch($resource, $action, $ids, $user)
    : $action->executeBatch($records);
```

`BulkActionInterface::isAsync(int $count): bool` est ajouté
*optionnellement* (default `false` via `BulkActionTrait::isAsync()`
shipped in this package). Hosts qui adoptent v0.1 sans modifier
leurs actions ne paient rien.

### 7. `BulkJobResource` — browsable

Auto-tagged `#[AsResource]`, permission `POLYSOURCE_BULK_JOB_VIEW`.
Filtres : `actorId` (eq), `status` (in), `createdAt` (between),
`resourceName` (in). Inline action `cancel` qui passe le status
à `Cancelled`.

### 8. Mercure broadcast — optionnel

`MercureBulkJobBroadcaster` listen sur les progress events et
publie sur `polysource/bulk-jobs/{id}`. Service gated sur
`class_exists(\Symfony\Component\Mercure\HubInterface)` — apps
sans Mercure tombent gracieusement sur le polling Stimulus.

### 9. Stimulus + polling fallback

`progress_controller.js` :
- Si Mercure wired (`data-mercure-url-value` présent) →
  EventSource subscription au topic.
- Sinon → polling `GET /admin/bulk-jobs/{id}/progress` toutes les
  2 secondes.

Affiche progress bar Bootstrap + texte "`processed / total`
records · `failed` failures · ETA `Xs`".

### 10. Audit (ADR-020)

Le `BulkJobMessage` handler dispatche
`ActionExecutedEvent` (depuis `polysource/symfony-bundle`) une
fois le job terminé — l'audit log trace alors le job comme une
action async normale, avec `actionName = bulk:<original-action>`
et `recordIds = [tous les records traités]`. Le `context` carry
`processedCount` + `failedCount` pour audits compliance.

### 11. Plugin (ADR-018)

`#[AsPlugin(name: 'polysource/bulk-async')]`.

## Conséquences

### Positives

- Bulk actions deviennent scalables (5k+ records sans timeout HTTP).
- Audit trail (ADR-020) capture les jobs async natifs.
- Mercure ship une UX live-progress moderne; polling fallback
  garantit que la progress UI marche partout.
- Cancellation côté UI : opérateur peut stop un job long sans
  attendre la fin.

### Négatives / coûts

- 1 nouveau package + 3 nouvelles dépendances optionnelles
  (`symfony/messenger` hard, `symfony/mercure` soft).
- Storage supplémentaire (1 row par job + progress updates).
  `polysource:bulk-jobs:purge` retention shippera en v0.2.
- Le `BulkJobHandler` consomme un message worker — hosts doivent
  configurer un transport (Doctrine / Redis / AMQP) et lancer un
  `messenger:consume`.

## Plan d'implémentation (Phase 16)

| Batch | Tâches |
|---|---|
| **A** | `BulkJob` VO + `BulkJobStatus` enum + `BulkJobStorageInterface` + `DoctrineBulkJobStorage` + tests |
| **B** | `BulkJobMessage` + `BulkJobHandler` + `AsyncBulkActionDispatcher` + tests |
| **C** | `BulkJobResource` + `ProgressController` + tests |
| **D** | `MercureBulkJobBroadcaster` (optional dep) + tests |
| **E** | Stimulus `progress_controller.js` + `_progress.html.twig` + `BulkProgressExtension` Twig |
| **F** | `PolysourceBulkAsyncBundle` + extension + services.php + plugin manifest |
| **G** | `docs/user/bulk-async/` |

Estimation : **~3-4 semaines** (le plus gros lift des 6 cherry-picks).

## Alternatives rejetées

### A. Run async dans `polysource/symfony-bundle` directement

Tirerait Messenger en hard dep partout. Rejeté pour préserver
l'opt-in.

### B. Cancellation par event Doctrine plutôt que re-fetch

Une approche pub/sub bypasserait le DB round-trip à chaque tour.
Rejeté pour v0.1 — plus complexe, ROI marginal pour Mb-scale.
Reconsidérer en v0.3 si benchmarks le justifient.

### C. Forcer Mercure (sans fallback polling)

Rejeté car certains hosts production ne peuvent pas exposer un
Mercure hub. Polling garantit que la progress UI marche partout.

### D. Stocker les progress updates en Redis plutôt que Doctrine

Plus rapide, mais ajoute une dépendance Redis hard. Rejeté pour
v0.1 ; v0.2 ship un `RedisBulkJobStorage` pour les hosts à très
haut volume.

## Migration / breaking changes

Aucun. Nouveau package opt-in. Le `BulkActionInterface::isAsync()`
est ajouté avec une default impl `false` dans le `BulkActionTrait`
shipped par ce package — les actions existantes ne paient rien
si elles n'optent pas.

## Suite (post-v0.1)

- v0.2 : `polysource:bulk-jobs:purge` retention command.
- v0.2 : `RedisBulkJobStorage` pour high-volume hosts.
- v0.2 : retry per-record (idempotent actions seulement).
- v0.3 : Slack/Discord webhook notifications when long jobs
  complete.
- v0.3 : pause/resume (pas que cancel).
- v0.4 : intégration Symfony Workflow (ADR-021) — bulk-async
  workflow transitions ("apply transition X to all matching
  orders").
