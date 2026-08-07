# §6 — Architecture cible

> Proposition d'architecture pour un **moteur d'admin Symfony multi-datasource**, indépendant d'EasyAdmin mais inspiré par lui.
>
> Inspirations directes : Sylius Grid (`DataSourceInterface` minimal + tag service), React Admin (9 méthodes dataProvider), AdminJS (10 méthodes BaseResource), Refine (6 providers orthogonaux), Filament (builders fluides).

## 6.1 Découpage en packages

```
polysource/                          Monorepo — 16 packages livrés, v1.1.0 publiée 2026-08-07
│
├── PRIMITIVES (zéro dep Symfony dans core)
│   ├── polysource/core              Contracts + value objects (38 types publics),
│   │                                dont le VO RowDetail (v1.1.0 — template | listing)
│   └── polysource/filter            FilterCollection, FilterService session, saved views,
│                                    enhanced form types — utilisable standalone
│
├── PRODUIT 1 — Polysource standalone admin (= bundle ci-dessous + adapters)
│   ├── polysource/symfony-bundle    Wiring (DI, routing, AdminContext, AsResource)
│   ├── polysource/twig-theme        Templates Twig (copiés/adaptés depuis EA v5, MIT)
│   ├── polysource/adapter-messenger Messenger failed transport read + 4 actions
│   ├── polysource/adapter-doctrine  Doctrine ORM read + write (whitelist filter properties)
│   ├── polysource/adapter-redis     Redis hashes via Predis (SCAN cursor pagination)
│   ├── polysource/adapter-flysystem Files S3 / local / Azure / GCS via Flysystem
│   ├── polysource/adapter-http      REST APIs via Symfony HttpClient (page-num + cursor)
│   └── polysource/adapter-meilisearch Meilisearch indexes
│
├── PRODUIT 2 — bridge EasyAdmin
│   └── polysource/easyadmin-filter-bridge
│                                    FilterConfiguratorInterface auto-tagués, 4 custom
│                                    filters, EventSubscriber session + saved-view apply,
│                                    RowDetailProviderInterface + Polysource::rowDetail()
│                                    (v1.1.0), override Twig (zéro fork EA)
│
└── CAPABILITÉS TRANSVERSES (opt-in, packages séparés — cf. ADR-018 plugin architecture)
    ├── polysource/audit             GDPR Art. 30 / HIPAA action log
    ├── polysource/bulk-async        Bulk over Messenger + progression Mercure
    ├── polysource/widgets           Dashboard widgets (KPI / list / chart)
    ├── polysource/search            Cmd+K palette cross-resource (fan-out aggregator)
    └── polysource/workflow-bridge   Symfony Workflow integration (transitions + state chip)
```

**Installation minimale** : `core` + `symfony-bundle` + `twig-theme` + 1 adapter selon le cas d'usage. Tout le reste est opt-in via `composer require polysource/<package>`.

## 6.2 Contrat de stockage — interfaces principales

### 6.2.1 Le `Resource` — équivalent du CrudController

```php
namespace Polysource\Core\Resource;

interface ResourceInterface
{
    public function getName(): string;            // 'product', 'failed_message', 'flag'
    public function getLabel(): string|TranslatableInterface;
    public function getIdentifierName(): string;  // 'id', 'uuid', 'message_id'
    public function getDataSource(): DataSourceInterface;

    /** @return iterable<FieldInterface> */
    public function configureFields(string $page): iterable;

    public function configureActions(Actions $actions): Actions;
    public function configureFilters(Filters $filters): Filters;

    public function getPermission(): ?string;     // attribute Symfony Voter
}
```

### 6.2.2 `DataSourceInterface` — contrat minimal en lecture (3 méthodes)

```php
namespace Polysource\Core\DataSource;

interface DataSourceInterface
{
    public function search(DataQuery $query): DataPage;          // list + filter + sort + paginate
    public function find(string|int $identifier): ?DataRecord;   // single
    public function count(DataQuery $query): ?int;               // null = unknown (sources sans total)
}
```

### 6.2.3 `WritableDataSourceInterface` — extension écriture (interface ségrégée)

```php
namespace Polysource\Core\DataSource;

interface WritableDataSourceInterface extends DataSourceInterface
{
    public function create(DataPayload $payload): DataRecord;
    public function update(string|int $identifier, DataPayload $payload): DataRecord;
    public function delete(string|int $identifier): void;
}
```

ISP : un adapter read-only n'implémente que `DataSourceInterface`. L'admin masque automatiquement les boutons Create/Edit/Delete s'il détecte que la source ne fait pas la writable interface.

### 6.2.4 Value objects neutres

```php
namespace Polysource\Core\Query;

final readonly class DataQuery
{
    public function __construct(
        public string $resourceName,
        public ?string $searchText = null,
        /** @var array<string, FilterCriterion> */
        public array $filters = [],
        /** @var array<string, 'asc'|'desc'> */
        public array $sort = [],
        public ?Pagination $pagination = null,    // null = pas de pagination
    ) {}

    public function withFilter(string $name, FilterCriterion $criterion): self { /* immutable */ }
    public function withSort(string $field, string $direction): self { /* immutable */ }
}

final readonly class FilterCriterion
{
    public function __construct(
        public string $property,
        public string $operator,    // 'eq', 'neq', 'gt', 'lt', 'like', 'in', 'between', 'null'
        public mixed $value,
    ) {}
}

final readonly class Pagination
{
    public function __construct(
        public int $offset = 0,
        public int $limit = 20,
        public ?string $cursor = null,    // pour cursor pagination
    ) {}
}

final readonly class DataPage
{
    public function __construct(
        /** @var iterable<DataRecord> */
        public iterable $items,
        public ?int $total,                // null si la source ne le sait pas
        public ?string $nextCursor = null,
        public ?string $prevCursor = null,
    ) {}
}

final readonly class DataRecord
{
    public function __construct(
        public string|int $identifier,
        public array $properties,    // map propertyName → value
        public mixed $rawSource = null,    // ex : l'entité Doctrine sous-jacente, ou l'array Redis
    ) {}

    public function get(string $property): mixed { /* lookup */ }
}

final readonly class DataPayload
{
    public function __construct(
        public array $properties,    // ce que le formulaire / l'API renvoie
    ) {}
}
```

### 6.2.5 Field, Filter, Action

```php
namespace Polysource\Core\Field;

interface FieldInterface
{
    public static function new(string $property, string|TranslatableInterface|null $label = null): self;
    public function getAsDto(): FieldDto;
}
// Inspiré directement d'EasyAdmin — c'est précisément la partie qu'on peut reprendre tel quel.

namespace Polysource\Core\Filter;

interface FilterInterface
{
    public function getProperty(): string;
    public function getSupportedOperators(): array;     // ['eq', 'like']
    public function applyToQuery(DataQuery $query, FilterCriterion $criterion): DataQuery;
}
// Note : applyToQuery retourne un *nouveau* DataQuery (immutable) — pas de mutation
// d'un QueryBuilder ; chaque adapter saura traduire DataQuery dans son langage natif.

namespace Polysource\Core\Action;

interface ActionInterface
{
    public function getName(): string;
    public function getLabel(): string|TranslatableInterface;
    public function getIcon(): ?string;
    public function getPermission(): ?string;
    public function isDisplayed(DataRecord $record, AdminContext $context): bool;
}

interface InlineActionInterface extends ActionInterface
{
    public function execute(DataRecord $record, AdminContext $context): ActionResult;
}

interface BulkActionInterface extends ActionInterface
{
    /** @param iterable<DataRecord> $records */
    public function executeBatch(iterable $records, AdminContext $context): ActionResult;
}

final readonly class ActionResult
{
    public function __construct(
        public bool $success,
        public string|TranslatableInterface|null $message = null,
        public ?Response $redirect = null,    // si on veut forcer une redirection
    ) {}
}
```

### 6.2.6 Permission

```php
namespace Polysource\Core\Permission;

interface PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool;
}
// Implémentation par défaut : Polysource\Bundle\Security\SymfonyAuthorizationCheckerPermission qui délègue à AuthorizationChecker.
// Fail-closed : sans firewall configuré, throw LogicException (cf. Phase 6 + audit Phase 7).
```

### 6.2.7 Renderer / Formatter

```php
namespace Polysource\Core\Renderer;

interface RendererInterface
{
    public function supports(FieldDto $field): bool;
    public function render(FieldDto $field, DataRecord $record, AdminContext $context): string;
}
// Chaque type de field a un renderer ; ils s'enregistrent par tag DI 'polysource.renderer'.
// Les renderers consomment des templates Twig — héritage direct des templates EasyAdmin.

interface FormatterInterface
{
    public function format(mixed $value, FieldDto $field, AdminContext $context): mixed;
}
// Étape avant le renderer : transforme la valeur brute en valeur formatée (date → string, money → "12,34 €")
```

## 6.3 Flux de requête typique

### 6.3.1 Index (list)

```
HTTP GET /admin/{resourceName}                     ex: /admin/failed-message
│
├─ AdminRouter::onKernelRequest()
│  ├─ resolve resourceName 'failed-message' → FailedMessageResource (instance via DI)
│  ├─ resolve action 'index'
│  ├─ build AdminContext (request, currentResource, page='index', search params)
│  └─ store in request.attributes
│
├─ AdminContextResolver injecte AdminContext dans le controller générique IndexController::__invoke()
│
├─ IndexController::__invoke(AdminContext $ctx)
│  ├─ dispatch BeforeIndexEvent (stoppable)
│  ├─ permission check : $ctx->resource->getPermission() if any
│  ├─ build fields  : $ctx->resource->configureFields('index')
│  ├─ build filters : $ctx->resource->configureFilters(new Filters())
│  ├─ build query   : DataQuery construit depuis Request (search, filters, sort, pagination)
│  ├─ $page = $ctx->resource->getDataSource()->search($query)
│  ├─ apply RendererInterface à chaque DataRecord pour produire la table
│  ├─ build actions : $ctx->resource->configureActions(new Actions())
│  └─ return KeyValueStore { templateName, page, fields, actions, filters }
│
├─ dispatch AfterIndexEvent
│
└─ kernel.view → IndexResponseListener → Twig render → HTTP 200
```

### 6.3.2 Detail / Edit / New / Delete : variantes du même flux

- `Detail` : `find($id)` → render
- `New` : créer un `DataPayload` depuis le form → `WritableDataSource::create($payload)` → redirect
- `Edit` : `find($id)` → form pré-rempli → submit → `update($id, $payload)` → redirect
- `Delete` : `delete($id)` → flash + redirect

### 6.3.3 Row detail panel (v1.1.0)

Cinquième route générée par `PolysourceRouteLoader`, à côté d'`index`,
`detail`, `action` et `bulk_action` (cf. ADR-003 + [ADR-033](../adr/0033-expandable-row-details.md)) :

```
polysource_{routeKey}_detail_panel
HTTP GET /admin/{resourceName}/{id}/detail-panel     → RowDetailPanelController::__invoke
```

```
RowDetailPanelController::__invoke(AdminContext $ctx)
│
├─ assertResourceAccess($ctx->resource)
├─ la resource doit implémenter HasRowDetailsInterface, sinon 404
├─ $record = $ctx->resource->getDataSource()->find($ctx->recordId)   — 404 si absent
├─ garde PAR ENREGISTREMENT :
│     $attribute = $resource->getRowDetailPermission();
│     isGranted($attribute, $record) — le DataRecord est le sujet du voter, 403 sinon
├─ $detail = $resource->getRowDetail($record)                        — 404 si null
│
├─ $detail->isListing() ?
│     oui → EmbeddedListingRenderer : un listing Polysource imbriqué, paginé par le
│           param `rd_page` porté par CETTE requête de panneau (aucune collision avec
│           la query string du listing extérieur)
│     non → le template déclaré par le VO RowDetail + son contexte
│
└─ `?fragment=1` → le fragment seul (injecté dans la ligne dépliée)
   sinon        → `@Polysource/row_detail_page.html.twig`, page autonome complète
                  = le chemin sans JS, puisque le chevron est un vrai `<a href>`
```

Les deux réponses portent des en-têtes `no-store`.

Côté bridge EasyAdmin le même contrat passe par
`RowDetailProviderInterface` (autoconfiguré) + `Polysource::rowDetail()`
dans `configureFields()`, servi par `RowDetailController` — mêmes gardes,
même distinction fragment / page autonome.

### 6.3.4 InlineAction et BulkAction

```
HTTP POST /admin/{resourceName}/{id}/{action}        — InlineAction
HTTP POST /admin/{resourceName}/batch/{action}       — BulkAction (avec les ids dans le body)
│
├─ ActionController::__invoke(AdminContext $ctx)
│  ├─ resolve action via $ctx->resource->configureActions()
│  ├─ permission check
│  ├─ inline : $action->execute($record, $ctx)
│  ├─ bulk   : $action->executeBatch($records, $ctx)
│  └─ ActionResult → flash + redirect or response
```

**Désambiguïsation des routes.** La route bulk `/{slug}/batch/{action}`
est enregistrée AVANT la route paramétrée `/{slug}/{id}/{action}`, et
toutes les routes portant un `{id}` (detail, detail-panel, action) le
contraignent par `requirements: ['id' => '(?!batch$)[^/]+']`. Le
lookahead négatif rejette le littéral `batch` comme identifiant : ceinture
et bretelles, l'ordre d'enregistrement suffirait mais la contrainte rend
l'intention explicite et résiste à un réordonnancement accidentel. Les
`{action}` sont bornés par `[a-z][a-z0-9_-]*`.

### 6.3.5 Pagination

`DataPage` porte trois informations : `items`, `total ?int`, `nextCursor ?string`. Le template paginator détecte :

- `total !== null` → afficher pagination offset/limit classique
- `total === null && nextCursor !== null` → afficher uniquement « Page suivante » + bouton « Précédent » via `prevCursor`
- les deux nuls → pas de pagination, afficher tout (pour petites sources type config flags)

## 6.4 Adapters concrets — esquisses

### 6.4.1 `DoctrineDataSource`

```php
final class DoctrineDataSource implements WritableDataSourceInterface
{
    public function __construct(
        private string $entityFqcn,
        private EntityManagerInterface $em,
        private DoctrineQueryTranslator $translator,
    ) {}

    public function search(DataQuery $query): DataPage
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')->from($this->entityFqcn, 'e');
        $this->translator->apply($qb, $query);    // filtres, sort, search, pagination

        $paginator = new Paginator($qb->getQuery());
        return new DataPage(
            items: array_map($this->toRecord(...), iterator_to_array($paginator)),
            total: $paginator->count(),
        );
    }

    public function find(string|int $id): ?DataRecord
    {
        $entity = $this->em->find($this->entityFqcn, $id);
        return $entity ? $this->toRecord($entity) : null;
    }

    public function count(DataQuery $query): ?int { /* ... */ }
    public function create(DataPayload $payload): DataRecord { /* hydrate, persist, flush */ }
    public function update(string|int $id, DataPayload $payload): DataRecord { /* ... */ }
    public function delete(string|int $id): void { /* ... */ }

    private function toRecord(object $entity): DataRecord
    {
        return new DataRecord(
            identifier: PropertyAccessor::createPropertyAccessor()->getValue($entity, $this->idProperty()),
            properties: $this->extractProperties($entity),
            rawSource: $entity,
        );
    }
}
```

### 6.4.2 `HttpApiDataSource`

```php
final class HttpApiDataSource implements WritableDataSourceInterface
{
    public function __construct(
        private HttpClientInterface $http,
        private string $baseUrl,
        private string $idField = 'id',
    ) {}

    public function search(DataQuery $query): DataPage
    {
        $params = [
            'q'      => $query->searchText,
            'limit'  => $query->pagination?->limit ?? 20,
            'offset' => $query->pagination?->offset ?? 0,
            'sort'   => $this->serializeSort($query->sort),
            'filter' => $this->serializeFilters($query->filters),
        ];
        $resp = $this->http->request('GET', $this->baseUrl, ['query' => $params]);
        $data = $resp->toArray();
        return new DataPage(
            items: array_map($this->toRecord(...), $data['items'] ?? $data),
            total: $data['total'] ?? null,    // certaines APIs ne donnent pas le total
        );
    }
    // ...
}
```

### 6.4.3 `RedisHashDataSource` (read-only, ou writable selon usage)

```php
final class RedisHashDataSource implements DataSourceInterface
{
    public function __construct(
        private \Redis $redis,
        private string $keyPrefix,    // ex: 'feature_flag:'
    ) {}

    public function search(DataQuery $query): DataPage
    {
        $keys = $this->redis->keys($this->keyPrefix . '*');
        $items = [];
        foreach ($keys as $key) {
            $hash = $this->redis->hGetAll($key);
            $items[] = new DataRecord(
                identifier: substr($key, strlen($this->keyPrefix)),
                properties: $hash,
            );
        }
        // filtres/tri/pagination en mémoire
        return $this->paginateInMemory($items, $query);
    }

    public function find(string|int $id): ?DataRecord
    {
        $hash = $this->redis->hGetAll($this->keyPrefix . $id);
        return $hash ? new DataRecord((string)$id, $hash) : null;
    }

    public function count(DataQuery $query): ?int
    {
        return \count($this->redis->keys($this->keyPrefix . '*'));
    }
}
```

### 6.4.4 `MessengerFailedMessagesDataSource`

```php
final class MessengerFailedMessagesDataSource implements DataSourceInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private TransportInterface $failedTransport,    // 'failed' transport
    ) {}

    public function search(DataQuery $query): DataPage
    {
        if (!$this->failedTransport instanceof ListableReceiverInterface) {
            throw new \LogicException('Configure failed transport with `transport_options: [list_messages: true]`');
        }
        $envelopes = iterator_to_array($this->failedTransport->all($query->pagination?->limit ?? 50));
        $items = array_map(fn($env) => $this->envelopeToRecord($env), $envelopes);
        return new DataPage($items, total: null);    // Messenger ne sait pas le total
    }

    public function find(string|int $id): ?DataRecord
    {
        $envelope = $this->failedTransport->find($id);
        return $envelope ? $this->envelopeToRecord($envelope) : null;
    }

    public function count(DataQuery $query): ?int { return null; }

    private function envelopeToRecord(Envelope $env): DataRecord
    {
        $stamp = $env->last(SentToFailureTransportStamp::class);
        return new DataRecord(
            identifier: $env->last(TransportMessageIdStamp::class)?->getId() ?? spl_object_id($env),
            properties: [
                'message_class' => $env->getMessage()::class,
                'failed_at'     => $stamp?->getFailedAt(),
                'exception'     => $stamp?->getThrowable()?->getMessage(),
                'message_data'  => $env->getMessage(),
            ],
            rawSource: $env,
        );
    }
}
// Avec des actions custom : RetryAction (pousser dans le bus normal), DeleteAction (failedTransport->reject($id))
```

## 6.5 Intégrations Symfony

| Sous-système | Comment c'est intégré |
|---|---|
| **Symfony Security** | `PermissionInterface` par défaut wrap `AuthorizationCheckerInterface`. Les permissions sont des attributs string passés aux Voters (`PRODUCT_VIEW`, `FAILED_MESSAGE_RETRY`). Resources peuvent déclarer `getPermission()` ; Polysource appelle Voter avec subject = la `Resource` elle-même ou le `DataRecord` |
| **Symfony Forms** | Wrapper `AdminFormBuilder` qui prend un `DataPayload` (pas un `data_class` Doctrine) et construit le formulaire. Pour les associations : autocomplete via le **`DataSourceInterface`** de la resource liée — pas via `EntityType` |
| **Twig** | Templates fournis par `polysource/twig-theme`. Themable via héritage `{% extends '@Polysource/layout.html.twig' %}`. Tous les fields ont leur template dans `templates/field/` |
| **Messenger** | Adapter dédié + `RetryAction`, `DismissAction`, `RetryAllAction` |
| **EventDispatcher** | 8 events lifecycle similaires à EasyAdmin mais avec `DataRecord` au lieu d'`object` |
| **Translator** | Tous les labels/messages acceptent `string\|TranslatableInterface` |
| **HttpKernel** | Listener `kernel.request` détecte les routes polysource, listener `kernel.view` rend les `KeyValueStore` |

## 6.6 Bridge EasyAdmin — livré

Cette section décrivait deux directions envisagées. C'est tranché et
livré : `polysource/easyadmin-filter-bridge` est en production depuis
la 0.1.0 et fait partie du gel d'API v1.0.

### A. EasyAdmin comme adapter Doctrine pour Polysource — écartée

Un `EasyAdminBackedDoctrineDataSource` qui aurait délégué à `EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository`. Avantage théorique : hériter de la logique search/filter d'EasyAdmin sans la réimplémenter. Écartée parce qu'elle attachait le rythme de release de Polysource à celui d'EasyAdmin. `polysource/adapter-doctrine` parle directement à l'ORM.

### B. Polysource enrichit un dashboard EasyAdmin existant — retenue et livrée

C'est la voie prise, sous une forme plus fine que le « panel Polysource dans le layout EA » esquissé ici. Le bridge n'injecte pas de pages : il enrichit les listings EA existants — `FilterConfiguratorInterface` auto-tagués qui substituent les form types enrichis, 4 filtres custom absents d'EA amont, la barre de chips, les vues sauvegardées, et depuis la v1.1.0 les row details dépliables. Zéro fork d'EasyAdmin, uniquement des overrides Twig. Cible atteinte : adoption progressive, un `composer require` suffit.

Les deux produits coexistent bien : un hôte peut faire tourner le bridge sur ses CRUD Doctrine EA **et** le bundle standalone sur ses resources non-Doctrine, dans la même application (c'est exactement ce que démontre `examples/showcase-demo`).

## 6.7 Synthèse — qu'est-ce qui est neuf vs hérité

| Concept | Hérité d'EasyAdmin | Réinventé |
|---|---|---|
| `Resource` | inspiré de `CrudController` mais sans Doctrine | nouveau contrat clean |
| `DataSourceInterface` | absent d'EasyAdmin (Doctrine partout) | inspiré Sylius Grid + React Admin |
| `DataQuery` / `DataPage` / `DataRecord` | absent d'EasyAdmin (`QueryBuilder` partout) | nouveau, immutable, neutre |
| `FieldInterface` + `FieldDto` | conservé, peut copier la liste des field types | copy-paste OK |
| `FilterInterface` | reconçu — n'utilise plus `QueryBuilder` | nouveau |
| `ActionInterface` / `BulkActionInterface` | inspiré, ségrégué | ISP-respecting |
| Templates Twig | copiés sous licence MIT | adaptations marginales |
| Routing / AdminUrlGenerator | inspiré, simplifié | nouveau |
| Menu | copié conceptuellement | copy-adapter |
| Security Voters | utilise Symfony directement | aucune réinvention |
| Events lifecycle | inspirés, simplifiés à 8 events | nouveau |

**Coût — estimations initiales, conservées pour mémoire.** Au moment
d'écrire ce document, la v0.1 (resources read-only + 1-2 adapters +
index/detail) était chiffrée à **6–8 semaines pour 1 senior**, et la
v1.0 (CRUD complet + 4-5 adapters + actions + filtres complets + bridge
EasyAdmin) à **6 mois temps complet**. La v0.1.0 a été publiée le
2026-05-10 et la v1.0.0 le 2026-08-06, avec un périmètre plus large que
prévu (16 packages, capabilités transverses incluses).

Voir [`CHANGELOG.md`](../../CHANGELOG.md) pour ce qui a été livré et [`ROADMAP.md`](../../ROADMAP.md) pour ce qui reste à venir. Le plan de construction phase-par-phase reste un document de travail interne du mainteneur (non publié).
