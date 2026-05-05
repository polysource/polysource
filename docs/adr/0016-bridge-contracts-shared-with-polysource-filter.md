# ADR-016 — Bridge contracts shared with `polysource/filter`

- **Date** : 2026-05-04
- **Statut** : Accepté
- **Décide pour** : v0.1.0+
- **Étend** : [ADR-013 — `polysource/filter` architecture](./0013-filter-package-architecture.md)
- **En lien avec** : [ADR-015 — Multi-version baseline](./0015-multi-version-compatibility-baseline.md)

## Contexte

Le bridge `polysource/easyadmin-filter-bridge` accepte aujourd'hui des callbacks
inline pour personnaliser le rendu des chips :

```php
yield Polysource::field(BooleanField::new('isVisible'))
    ->chipFormatter(static fn (mixed $v): string => $v ? 'Visible' : 'Hidden');
```

C'est confortable pour des cas simples mais **bloquant** dès qu'on a besoin :
- d'**injecter des dépendances** (TranslatorInterface, EntityManagerInterface, services métier)
- de **réutiliser** la logique entre plusieurs CrudControllers
- de **tester** le formatter en isolation
- de partager le formatter avec un **autre bridge** futur (Sonata, API Platform)

Une closure verbeuse imbriquée dans `configureFields()` n'est pas le bon outil
pour de la logique riche.

Question parallèle : **où placer les interfaces** ? Si on les met dans le bridge
EA, un futur Sonata bridge devrait soit dupliquer le contrat, soit dépendre du
bridge EA pour réutiliser l'interface. Aucune option n'est saine.

`polysource/filter` est déjà notre **tronc commun** — il sert le pattern Pipeline
(`FilterMapperInterface`, `FilterFormatterInterface`, `FilterRendererInterface`)
pour les hosts qui consomment `FilterCriterion` nativement (sans admin
framework). Y placer un nouveau jeu d'interfaces pour les bridges aligne avec sa
mission.

## Décision

### 1. Deux niveaux d'interfaces dans `polysource/filter`

```
polysource/filter/
├── src/
│   ├── Pipeline/                          ← API native (FilterCriterion-based)
│   │   ├── FilterMapperInterface          ← request → FilterCriterion
│   │   ├── FilterFormatterInterface       ← FilterCriterion → string
│   │   └── FilterRendererInterface        ← FilterCriterion → FormType FQCN
│   │
│   └── Bridge/Contract/                   ← Contrats partagés par les bridges
│       └── ChipFormatterInterface         ← rawValue → string (URL-state API)
```

**Différence sémantique** :

- `Pipeline\*Interface` opère sur `FilterCriterion` (model immutable
  reconstruit côté primitive). Pour les hosts qui utilisent `polysource/filter`
  **nativement**, sans admin framework — ils construisent leurs FilterCollection
  à la main et le pipeline service-tagged (`polysource.filter.{mapper,formatter,renderer}`)
  les traite.
- `Bridge\Contract\*Interface` opère sur `(mixed $rawValue)` — la **forme
  brute** que voit un bridge à un admin framework. EA expose des
  `FilterDataDto` ; Sonata expose ses propres DTOs ; API Platform expose des
  query params. Tous se ramènent à `(string $property, mixed $rawValue)` pour
  la rendition d'un chip.

### 2. Premier contrat exposé : `ChipFormatterInterface`

```php
namespace Polysource\Filter\Bridge\Contract;

interface ChipFormatterInterface
{
    /**
     * Returns the human-readable chip label for a raw filter value.
     *
     * MUST return plain text — chip-rendering templates may auto-escape.
     * Hosts wanting HTML in their chips should override the chip template
     * directly, not return HTML from the formatter.
     */
    public function format(mixed $rawValue): string;
}
```

### 3. Le bridge EA accepte `callable|ChipFormatterInterface`

```php
// Inline (cas simples — comme aujourd'hui)
yield Polysource::field(BooleanField::new('isVisible'))
    ->chipFormatter(static fn (mixed $v): string => $v ? '✓' : '✗');

// Service injecté (cas complexes, réutilisable, testable)
final class VisibilityChipFormatter implements ChipFormatterInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function format(mixed $rawValue): string
    {
        return $this->translator->trans(
            true === $rawValue || '1' === $rawValue ? 'visibility.shown' : 'visibility.hidden',
        );
    }
}

// Dans le CrudController
public function __construct(
    private readonly VisibilityChipFormatter $visibilityFormatter,
) {}

public function configureFields(string $pageName): iterable
{
    yield Polysource::field(BooleanField::new('isVisible'))
        ->chipFormatter($this->visibilityFormatter);
}
```

`PolysourceFilter::chipFormatter()` et `PolysourceField::chipFormatter()` sont
typés `callable|ChipFormatterInterface $formatter`. Stocké tel quel dans
`customOption(BridgeOptions::CHIP_FORMATTER)`.

`ChipValueFormatter::format()` route :

```php
$formatter = $filter->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER);
if ($formatter instanceof ChipFormatterInterface) {
    $result = $formatter->format($rawValue);
} elseif (\is_callable($formatter)) {
    $result = $formatter($rawValue);
} else {
    /* fall through to other stages */
}
```

### 4. Mapper / Renderer / Formatter (pipeline complète) — v0.2+

Pour cohérence avec le pipeline mapper/formatter/renderer déjà introduit
côté `Pipeline\*Interface`, on prévoit la symétrie complète côté bridge
(sans implémenter en v0.1) :

```php
namespace Polysource\Filter\Bridge\Contract;

interface BridgeMapperInterface       // future v0.2+
{
    public function fromRawValue(mixed $rawValue): mixed;
    public function toRawValue(mixed $criterion): mixed;
}

interface BridgeRendererInterface     // future v0.2+
{
    public function getFormType(): string;
}
```

Ces interfaces **N'apparaissent pas** dans le code v0.1. Documentées ici comme
roadmap. La signature exacte sera fixée à l'implémentation.

## Conséquences

### Positives

1. **Tronc commun véritable** : `polysource/filter` ship les contrats que tous
   les bridges réutilisent. Un futur `polysource/sonata-filter-bridge` ou
   `polysource/api-platform-admin-bridge` consomme `ChipFormatterInterface`
   sans dépendance à `polysource/easyadmin-filter-bridge`.
2. **Hosts portables** : un service `MyChipFormatter implements
   ChipFormatterInterface` fonctionne sans changement si l'host migre EA →
   Sonata ou EA → API Platform — la logique métier est découplée du framework.
3. **DI propre** : injection de `TranslatorInterface`, `EntityManagerInterface`,
   services métier via le constructeur. Testable en isolation, mockable.
4. **Backwards-compatible** : les callbacks inline existants continuent de
   fonctionner. La nouvelle option est strictement additive.
5. **Symétrie 2 niveaux** : Pipeline pour le primitive natif, Bridge\Contract
   pour les bridges. Les hosts sophistiqués qui font les 2 (admin EA + script
   batch standalone qui réutilise les filtres) ont des contrats clairs pour
   chaque cas.

### Négatives / Trade-offs

1. **Deux jeux d'interfaces** dans `polysource/filter` — confusion possible
   pour un nouvel arrivant. Mitigé par la doc (`docs/user/filter/getting-started.md`
   distingue clairement les 2 cas).
2. **Bridge garde une dépendance forte sur `polysource/filter`** — déjà le
   cas, mais on resserre encore. Acceptable : c'est la volonté du tronc commun.
3. **Bridge ne consomme PAS la pipeline polysource/filter directement** —
   reste vrai (cf. ADR-013) : la pipeline `FilterCriterion`-based ne fit pas
   les FilterDataDto d'EA. Les 2 namespaces (`Pipeline\*` et
   `Bridge\Contract\*`) ne se croisent pas en runtime.

## Alternatives considérées

### A. Mettre `ChipFormatterInterface` dans le bridge EA

Rejeté : un futur Sonata bridge devrait re-définir le contrat. Pas de tronc
commun.

### B. Supporter uniquement les callables, pas d'interface

Rejeté : pas de DI, pas de réutilisabilité cross-controller, pas de tests
isolés. C'est un blocker user-facing déjà identifié.

### C. Utiliser `FilterFormatterInterface` (Pipeline) côté bridge en wrapping

Possible : le bridge construirait un `FilterCriterion` à partir de `(property,
rawValue)` et appellerait `format($criterion)`. Rejeté pour 2 raisons :

1. La construction d'un `FilterCriterion` côté bridge est un overhead pour
   chaque chip render — gaspillage CPU.
2. Sémantique abusive : un `FilterCriterion` représente une intention déclarative
   (host construit la collection), pas un état URL appliqué.

Les 2 contrats restent distincts intentionnellement.

### D. Service locator tagged sur `polysource.bridge.chip_formatter`

Rejeté pour v0.1 : trop d'overhead pour pas de gain. Si un host veut un
formatter par-property (sans le déclarer dans le `chipFormatter()`), il peut
toujours dispatcher dans son propre formatter via un `match()` interne.

## Plan d'exécution

1. Cette ADR-016 (signée, ce commit).
2. Création de `Polysource\Filter\Bridge\Contract\ChipFormatterInterface` dans
   le primitive (commit séparé, dans la même PR).
3. `PolysourceFilter::chipFormatter()` + `PolysourceField::chipFormatter()`
   acceptent `callable|ChipFormatterInterface $formatter`.
4. `ChipValueFormatter::format()` route `instanceof` avant `is_callable`.
5. Test fonctionnel : 1 fixture inline + 1 fixture service-based dans
   `ChipValueFormatterTest`.
6. Demo : `VisibilityChipFormatter` (service) injecté dans `CategoryCrudController`,
   à côté d'un autre exemple inline pour montrer la cohabitation.
7. Documentation : section "Custom chip formatters" dans le getting-started du
   bridge ; section "Bridge contracts" dans le getting-started du primitive
   pour les implémenteurs de bridges futurs.

Estimation : **~4 heures**, réalisé dans le même cycle que la migration
multi-version (cf. [ADR-015](./0015-multi-version-compatibility-baseline.md)).
