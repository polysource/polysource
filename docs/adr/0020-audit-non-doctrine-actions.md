# ADR-020 — Audit non-Doctrine actions (Phase 12)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 12 — deuxième capability ADR-017 cherry-picks
- **En lien avec** : [ADR-005 — `#[AsResource]` + actions](./0005-as-resource-attribute.md), [ADR-006 — Messenger envelope mapper](./0006-messenger-envelope-mapper.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — `AdminPluginInterface` + public contracts](./0018-admin-plugin-interface-and-public-contracts.md), [ADR-019 — Architecture des saved views](./0019-saved-views-architecture.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §3 retient
**audit non-Doctrine actions** parmi les 7 features Phase 10+ : tracer qui
a déclenché quoi, sur quelle ressource, à quel instant, avec quel résultat.
Aucun équivalent Symfony aujourd'hui — `simplethings/entity-audit-bundle`
et `dama/doctrine-test-bundle` couvrent uniquement les mutations Doctrine
ORM. Or Polysource cible précisément les ressources non-Doctrine (Messenger
failed messages, Redis hashes, S3, Meilisearch, HTTP API) où les seules
mutations observables passent par `ActionInterface::execute()` /
`executeBatch()`.

L'étude `symfony-admin-framework-analysis.md` §3.4 cite Filament
(`spatie/laravel-activitylog` intégré), Forest Admin (audit log natif),
Linear et Notion (changelogs cliquables). C'est P1 audience-capture pour
les utilisateurs en environnement régulé (HIPAA, GDPR Art. 30, SOX) qui
ne peuvent pas adopter un admin sans audit trail.

Pré-requis posés par [ADR-018](./0018-admin-plugin-interface-and-public-contracts.md) :

- Tag DI primaire : `polysource.audit_logger`
- Interface contributeur : `AuditLoggerInterface` (multiples loggers
  acceptés — fan-out vers Doctrine + syslog + Datadog par exemple)
- Le tout ship dans un plugin séparé (pas dans `polysource/symfony-bundle`).

## Décision

### 1. Package de livraison : nouveau `polysource/audit`

Audit n'est ni filter-spécifique ni admin-spécifique : c'est une capability
transversale qui s'applique à **toute action exécutée par
`polysource/symfony-bundle`** quel que soit l'adapter (Messenger, Redis, S3,
Doctrine, custom). Il vit donc dans son propre package, pas dans
`polysource/filter` (ADR-019) ni dans `polysource/symfony-bundle` (qui doit
rester sans dépendance optionnelle Doctrine).

```
polysource/audit
├── src/
│   ├── PolysourceAuditBundle.php
│   ├── DependencyInjection/
│   │   └── PolysourceAuditExtension.php  # gating Doctrine ORM
│   ├── Plugin/
│   │   └── AuditPlugin.php               # #[AsPlugin] (ADR-018)
│   ├── Model/
│   │   ├── AuditEntry.php                # VO immutable
│   │   ├── AuditOutcome.php              # enum: success/failure/exception
│   │   └── AuditActorInterface.php       # contrat pour le current user
│   ├── Logger/
│   │   ├── AuditLoggerInterface.php      # contrat write-only
│   │   ├── AggregateAuditLogger.php      # fan-out (default impl)
│   │   ├── DoctrineAuditLogger.php       # storage Doctrine ORM
│   │   ├── PsrLogAuditLogger.php         # bridge vers psr/log (toujours wirable)
│   │   └── NullAuditLogger.php           # noop pour tests
│   ├── Storage/Doctrine/
│   │   └── AuditEntryRecord.php          # entité table polysource_audit_log
│   ├── EventListener/
│   │   └── ActionAuditSubscriber.php     # hook les events action
│   ├── Resource/
│   │   ├── AuditLogResource.php          # #[AsResource] — l'audit log lui-même est browsable
│   │   └── AuditLogDataSource.php
│   └── Action/
│       └── ExportAuditCsvAction.php      # global action GDPR Art. 30
├── Resources/
│   ├── config/services.php
│   └── translations/PolysourceAudit.{en,fr}.yaml
└── composer.json
```

**Justification du package séparé** :
- `polysource/symfony-bundle` ne doit pas tirer Doctrine ORM (cf. ADR-001).
- Hosts sans audit (POC, demos jetables) n'ont pas à payer la table SQL.
- Permet aux entreprises de fork le package pour shipper leur propre
  storage (CloudWatch, BigQuery, Splunk) sans toucher au reste.

### 2. Modèle — `AuditEntry` value object

Immutable, en ligne avec ADR-004 / ADR-019 :

```php
namespace Polysource\Audit\Model;

final class AuditEntry
{
    /**
     * @param list<string>          $recordIds  — empty for global actions
     * @param array<string, mixed>  $context    — IP, user agent, requestId, action context
     */
    public function __construct(
        public readonly string $id,                 // UUID v7 host-generated
        public readonly \DateTimeImmutable $occurredAt,
        public readonly string $actorId,            // user identifier (string-cast); '__anonymous__' if no user
        public readonly ?string $actorLabel,        // display name if available
        public readonly string $resourceName,
        public readonly string $actionName,
        public readonly array $recordIds,
        public readonly AuditOutcome $outcome,
        public readonly ?string $message,
        public readonly int $durationMs,
        public readonly array $context = [],
    ) {
        // Validation au constructor : id non-vide, durationMs >= 0,
        // recordIds liste de strings non-vides, occurredAt UTC normalisé.
    }
}
```

Choix :
- `actorId` est string. Cast côté listener : `(string) $user?->getUserIdentifier() ?? '__anonymous__'`.
- `recordIds` liste vide pour les actions globales (`Export CSV`, `Purge all`).
- `context` libre — l'event subscriber y met IP / user agent / request id ;
  les hosts peuvent ajouter des champs métier.
- `durationMs` mesuré dans `ActionAuditSubscriber` autour du
  `safelyRun()` callable.

### 3. Enum `AuditOutcome`

Trois cas (cf. the project context file « enums simples, case-only ») :

```php
enum AuditOutcome: string
{
    case Success = 'success';      // ActionResult::success()
    case Failure = 'failure';      // ActionResult::failure() (graceful)
    case Exception = 'exception';  // uncaught throwable (rare via safelyRun)
}
```

Pas de méthodes sur l'enum — le mapping vers le label UI passe par le
template Twig + translator (clé `polysource.audit.outcome.<value>`).

### 4. Contrat `AuditLoggerInterface`

Write-only, single-method :

```php
namespace Polysource\Audit\Logger;

interface AuditLoggerInterface
{
    public function log(AuditEntry $entry): void;
}
```

Justification du minimalisme :
- Lecture passe par `AuditLogResource` (Doctrine queryBuilder), pas par le
  logger.
- Plusieurs loggers en parallèle (fan-out Doctrine + Datadog) → tous
  taggés `polysource.audit_logger`, agrégés dans `AggregateAuditLogger`
  qui devient le service injecté.
- `void` retour : audit ne peut pas faire échouer l'action. Un logger qui
  throw doit être contenu (try/catch dans l'aggregator).

### 5. Hook : event subscriber sur les actions

Plutôt que muter `ActionController::safelyRun()`, on émet **deux events
Symfony** dans le bundle existant :

```php
namespace Polysource\Bundle\Event;

final class ActionAboutToExecuteEvent
{
    public function __construct(
        public readonly ActionInterface $action,
        public readonly ResourceInterface $resource,
        public readonly array $recordIds,         // list<string>
        public readonly Request $request,
    ) {
    }
}

final class ActionExecutedEvent
{
    public function __construct(
        public readonly ActionInterface $action,
        public readonly ResourceInterface $resource,
        public readonly array $recordIds,
        public readonly Request $request,
        public readonly ActionResult $result,
        public readonly int $durationMs,
        public readonly ?\Throwable $exception = null,
    ) {
    }
}
```

`ActionController` les dispatche autour du `safelyRun()`. Le subscriber
audit (dans `polysource/audit`) écoute uniquement `ActionExecutedEvent`
— l'`AboutToExecute` est exposé pour d'autres usages (rate limiting,
SLO instrumentation) mais l'audit attend forcément le résultat.

**Justification events** :
- Couplage faible : audit est totalement optionnel.
- Réutilisable : les hosts peuvent écouter pour leurs propres besoins
  (ex. Mercure broadcast d'activité, cf. Phase 16 bulk async).
- Testable : tests unitaires d'`ActionController` ne paient pas le
  coût audit.

Ces 2 events vont dans `polysource/symfony-bundle` (pas dans audit) car
ils sont émis par le bundle. Audit n'est qu'un consommateur parmi
plusieurs.

### 6. Storage — `DoctrineAuditLogger` + table

Comme ADR-019 §3, gating sur `interface_exists(EntityManagerInterface)` :

```sql
CREATE TABLE polysource_audit_log (
    id              VARCHAR(36)  PRIMARY KEY,
    occurred_at     DATETIME     NOT NULL,
    actor_id        VARCHAR(120) NOT NULL,
    actor_label     VARCHAR(120) NULL,
    resource_name   VARCHAR(120) NOT NULL,
    action_name     VARCHAR(120) NOT NULL,
    record_ids      JSON         NOT NULL,  -- array<string>
    outcome         VARCHAR(16)  NOT NULL,
    message         TEXT         NULL,
    duration_ms     INTEGER      NOT NULL,
    context         JSON         NOT NULL,
    INDEX idx_occurred_at (occurred_at),
    INDEX idx_actor_resource (actor_id, resource_name),
    INDEX idx_resource_action (resource_name, action_name)
);
```

Indexes choisis :
- `occurred_at` pour la pagination chronologique du `AuditLogResource`.
- `(actor_id, resource_name)` pour répondre « tout ce qu'a fait Alice
  sur les commandes le mois dernier » (compliance Art. 30 GDPR).
- `(resource_name, action_name)` pour répondre « toutes les retry des
  failed messages dans la dernière semaine ».

Pas de FK vers une table user — l'`actor_id` est string (matche
`UserInterface::getUserIdentifier()`) pour rester portable entre apps
qui n'ont pas la même classe `User`.

### 7. `AuditLogResource` — l'audit est lui-même browsable

Auto-tagged `#[AsResource]` (ADR-005), permission
`POLYSOURCE_AUDIT_VIEW`. Filtres standards via `polysource/filter` :
- `occurredAt` (between dates)
- `actorId` (=)
- `resourceName` (in)
- `actionName` (in)
- `outcome` (in: success/failure/exception)

Une seule `BulkActionInterface` shippée : `ExportAuditCsvAction` —
GDPR Art. 30 demande de pouvoir extraire le journal pour produire le
registre des traitements.

**Pas d'inline action `Delete`**. Un audit log immutable est le seul
audit log de confiance. La rétention se fait par cron host-side
(`polysource:audit:purge --before=2025-05-05`) — un command Symfony
livré dans `polysource/audit`.

### 8. Plugin Phase ADR-018

`AuditPlugin` implémente `AdminPluginInterface` :

```php
#[AsPlugin]
final class AuditPlugin implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getPluginName(): string    { return 'polysource/audit'; }
    public function getPluginVersion(): string { return '0.1.0'; }
    public function configure(ContainerBuilder $container): void {
        // Register tag aliases, declare contributed types
    }
}
```

Apparaît dans le manifest plugin (cf. ADR-018 §6) avec sa contribution :
1 resource (`AuditLogResource`), 1 logger interface, 1 listener.

### 9. Champs context standards émis par `ActionAuditSubscriber`

Convention sur le `context` array :

| Clé | Type | Source |
|---|---|---|
| `ip` | string | `$request->getClientIp()` |
| `userAgent` | string | `$request->headers->get('User-Agent')` |
| `requestId` | string | `$request->headers->get('X-Request-Id')` ou UUID v4 généré |
| `actionContext` | array | `$result->context` (cf. ADR existant) |
| `errorClass` | string | si exception, `$exception::class` |
| `errorTrace` | string | si exception, `$exception->getTraceAsString()` (tronqué 8 KiB) |

Hosts peuvent étendre via leur propre subscriber sur `ActionExecutedEvent`
qui mute `$entry->context` ? **Non** — `AuditEntry` est immutable.
Mécanisme alternatif : un `AuditContextEnricherInterface` (ADR-018-style
contributor tag) résolu par l'aggregator avant `log()`. À shipper
seulement si un host le demande — YAGNI v0.1 du package.

### 10. Sécurité

- Audit log readable seulement par `ROLE_ADMIN` ou attribute custom.
  `AuditLogResource::getViewPermission()` retourne `'POLYSOURCE_AUDIT_VIEW'`.
- Pas de masquage automatique des données sensibles. Si un host audit un
  endpoint `change-password`, il est responsable de NE PAS mettre le
  password dans `ActionResult::context`. Un linter PHPStan custom pourra
  vérifier ça en v0.2.
- Headers d'authentification (`Authorization`, cookies) volontairement
  exclus du capture par défaut — convention `ActionAuditSubscriber`
  whiteliste les headers (UA + Request ID seulement).

## Conséquences

### Positives

- Compliance débloquée pour les hosts régulés (HIPAA, GDPR, SOX).
- Audience nouvelle : entreprises qui ne peuvent pas adopter un admin
  sans audit. Concrètement débloque les pilotes payants type fintech /
  santé qu'on perdait sur cette case.
- Fan-out logger trivial : ajouter Datadog APM ou syslog = 1 service
  taggé `polysource.audit_logger`.
- Réutilisation des events `ActionAboutToExecuteEvent` /
  `ActionExecutedEvent` pour Mercure broadcast (Phase 16) et bulk async
  progress tracking (Phase 16 aussi).

### Négatives / coûts

- Nouveau package `polysource/audit` à maintenir : compose, CI matrix,
  tests, traductions EN/FR.
- Ajoute 6 nouveaux types publics (cf. arborescence §1) — consomme du
  budget des 40 types `core` (ADR-010) ? **Non** — ils vivent dans
  `polysource/audit`, pas `polysource/core`. Le budget `core` n'est
  pas touché.
- Couplage Doctrine ORM par défaut. Hosts sans Doctrine doivent câbler
  un `PsrLogAuditLogger` ou implémenter leur propre `AuditLoggerInterface`
  (NoSQL, append-only file, …) — la documentation guide explicitement.
- Migration BC : ajouter les events dans `ActionController` ne casse
  rien (pas de listeners par défaut), mais les tests existants doivent
  vérifier que les events sont bien dispatched dans tous les paths
  (success, graceful failure, exception).

### Risques mitigés

- **Performance** : `AggregateAuditLogger` exécute les loggers
  synchrones dans le request-response cycle. Pour un host qui veut un
  logger lent (Datadog HTTP), un `AsyncAuditLogger` qui dispatch sur
  Messenger sera shippé en v0.2 (envelope `LogAuditEntryMessage`).
  v0.1 = synchrone, durations attendues < 5 ms par logger.
- **Volume** : 1 GB/jour pour 10k actions/jour avec context complet.
  La purge cron + indexes adressent. Hosts à très haut volume
  archivent par mois (partition Doctrine 3.x + commande `archive`).
- **Test isolation** : `NullAuditLogger` câblé dans l'environnement
  `test` du host. Le subscriber ne touche pas l'audit table en CI.

## Plan d'implémentation (Phase 12)

Découpage TDD batch par batch, comme Phase 11 :

| Batch | Tâches | Test target |
|---|---|---|
| **A** | `AuditEntry` VO + `AuditOutcome` enum + `AuditLoggerInterface` + `NullAuditLogger` + `AggregateAuditLogger` | Unit |
| **B** | `ActionAboutToExecuteEvent` + `ActionExecutedEvent` dans `polysource/symfony-bundle` ; dispatch dans `ActionController::__invoke()` + `bulk()` | Unit + Functional |
| **C** | `ActionAuditSubscriber` qui consomme `ActionExecutedEvent` et appelle `AggregateAuditLogger` | Unit + Functional |
| **D** | `DoctrineAuditLogger` + `AuditEntryRecord` entité + DI gating Doctrine | Functional |
| **E** | `AuditLogResource` + `AuditLogDataSource` (Doctrine queryBuilder) + filtres (occurredAt, actor, resource, action, outcome) | Functional |
| **F** | `ExportAuditCsvAction` bulk action (GDPR Art. 30) | Functional + E2E |
| **G** | `polysource:audit:purge` console command (rétention) | Unit + Functional |
| **H** | `AuditPlugin` (ADR-018) + manifest entry | Unit |
| **I** | `examples/messenger-demo` : ajouter `polysource/audit` + visualisation des retries dans le dashboard | E2E manuel |
| **J** | `docs/user/audit/` : install, configure, schema, custom logger, retention | Docs |

Estimation : **~3 semaines** (similaire à saved views Phase 11).

## Alternatives rejetées

### A. Audit dans `polysource/symfony-bundle` directement

Rejeté car ferait dépendre le bundle de Doctrine ORM, contredit ADR-001.
Le gating conditionnel marche en théorie (cf. `DoctrineSavedViewStorage`
ADR-019) mais ferait grossir le bundle de ~30 fichiers — il deviendrait
le hub central qu'on a explicitement refusé d'avoir.

### B. Surcharger l'`ActionInterface` avec un `getAuditMetadata()`

Rejeté car forcerait chaque action existante (4 dans `adapter-messenger`)
à implémenter une méthode supplémentaire. Le subscriber + events
maintient le contrat existant inchangé — les actions n'ont rien à
savoir de l'audit.

### C. Audit synchrone via décorateur sur `ActionExecutorInterface`

Plus propre que les events sur le papier, mais Polysource n'a pas (encore)
un `ActionExecutor` injectable — `ActionController::safelyRun()` fait le
boulot inline. Refactor `safelyRun → ActionExecutor → AuditingActionExecutor`
serait un bigger change que les 2 events. À reconsidérer en v0.3 si on
doit factoriser.

### D. Stocker dans `monolog/monolog` directement (file-based)

Insuffisant pour les usages réels. Les compliance officers veulent un
endpoint web avec filtres + export CSV ; un `var/log/audit.log` ne couvre
ni la recherche ni la rétention par utilisateur. `PsrLogAuditLogger`
shippé reste utile pour le dev local et comme bridge vers Sentry/ELK.

### E. Coupler l'audit aux mutations DataSource (write-side de l'adapter)

Rejeté : seuls les data sources writables auraient un audit, et Polysource
cible plutôt les ressources read-only (Messenger, S3, Meilisearch). En
auditant au niveau **action**, tout adapter est couvert par construction —
y compris les actions qui ne mutent pas la donnée (ex. « Reprocess from
DLQ ») et qui sont précisément les plus utiles à tracer.

## Glossaire

- **Action** : objet implémentant `ActionInterface` (inline ou bulk).
- **Actor** : user authentifié au moment de l'action ; `__anonymous__`
  si action déclenchée par un cron ou requête publique.
- **Outcome** : `success` / `failure` / `exception` — orthogonal à
  `ActionResult::$success` (qui est `bool`) car on distingue les exceptions
  uncaught des `ActionResult::failure()` gracieux.
- **Context** : payload libre passé via `ActionResult::$context` ou
  enrichi par le subscriber (IP, UA, request ID).

## Migration / breaking changes

Aucun. ADR-020 ajoute :
- 2 events dans `polysource/symfony-bundle` (additif, pas de listener
  par défaut → comportement inchangé pour les hosts existants).
- 1 nouveau package `polysource/audit` (opt-in pur).

## Suite (post-v0.1)

- v0.2 : `AsyncAuditLogger` (Messenger fan-out vers loggers lents).
- v0.2 : `AuditContextEnricherInterface` si demande utilisateur.
- v0.3 : intégration Mercure pour broadcast live d'activité (jumeau
  Phase 16 bulk async).
- v0.4 : audit-aware permissions — refuser une action si l'audit logger
  est down (mode strict pour environnements régulés).
