# ADR-005 — Configuration mechanism

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

Une `Resource` Polysource a besoin de déclarer plusieurs choses :
- son nom (`failed-messages`, `products`, `flags`)
- son label affiché à l'écran (i18n)
- sa data source (laquelle parmi celles taggées `polysource.data_source`)
- ses fields (par page : index / detail / edit)
- ses actions (inline / global / bulk)
- ses filtres
- sa permission

Plusieurs mécanismes possibles pour cette configuration : YAML, méthodes d'interface, attributes PHP, ou hybride.

## Options envisagées

### Option A — Configuration YAML

```yaml
polysource:
    resources:
        failed-messages:
            label: 'Failed messages'
            data_source: messenger.failed
            fields:
                index: [id, message_class, failed_at, exception_class]
            actions: [retry, dismiss, retry_all]
            permission: ROLE_ADMIN
```

**Pour** : centralisé, lisible.
**Contre** : pas type-safe, pas de refactoring IDE, configuration hors du code Resource.

### Option B — Méthodes d'interface (modèle EasyAdmin)

Chaque `Resource` étend `AbstractResource` et override des méthodes :

```php
class FailedMessageResource extends AbstractResource
{
    public function getName(): string { return 'failed-messages'; }
    public function configureFields(string $page): iterable { yield IdField::new('id'); /* ... */ }
}
```

**Pour** : type-safe, refactoring IDE, configuration co-localisée.
**Contre** : verbeux pour les resources triviales.

### Option C — PHP Attributes

```php
#[AsResource(name: 'failed-messages', label: 'Failed messages', dataSource: 'messenger.failed')]
final class FailedMessageResource
{
    #[AsField(page: ['index', 'detail'], type: IdField::class)]
    public string $id;
}
```

**Pour** : moderne, déclaratif, peu de boilerplate.
**Contre** : limité pour la logique conditionnelle.

### Option D — Hybride : interface methods (par défaut) + attributes (raccourcis)

L'utilisateur peut soit étendre `AbstractResource` et override des méthodes, soit utiliser des attributes pour les cas simples.

## Décision

**Option D — Hybride** est retenue.

### Mécanisme primaire : interface methods

```php
namespace App\Admin\Resource;

use Polysource\Core\Resource\AbstractResource;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Field\IdField;
use Polysource\Core\Field\TextField;
use Polysource\Core\Field\DateTimeField;
use Polysource\Core\Field\CodeField;
use Polysource\Core\Action\Actions;

#[AsResource(name: 'failed-messages', dataSource: 'messenger.failed')]
final class FailedMessageResource extends AbstractResource
{
    public function getLabel(): string|TranslatableInterface
    {
        return new TM('polysource.failed_messages.label');
    }

    public function configureFields(string $page): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('messageClass')->setLabel('Message');
        yield DateTimeField::new('failedAt');
        yield CodeField::new('payload')->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', RetryFailedMessageAction::class)
            ->add('index', DismissFailedMessageAction::class)
            ->addBulk(RetryAllFailedMessagesAction::class);
    }

    public function getPermission(): ?string
    {
        return 'POLYSOURCE_FAILED_MESSAGE';
    }
}
```

### Raccourci : attributes pour les cas triviaux

L'attribute `#[AsResource(...)]` permet de gagner du temps quand on n'a pas besoin de la richesse des méthodes. Il sert aussi de **registration** : la classe est automatiquement taggée `polysource.resource` via le compiler pass.

Pour les `Field` simples, on **ne fait pas** d'attributes au niveau propriété en v0.1 (Option C complète). C'est trop limitant pour les cas conditionnels (`isDisplayed()`, `setPermission()`, etc.). On reste sur `configureFields()`.

### Pas de YAML

YAML est explicitement écarté pour la déclaration de Resource. La configuration globale (`url_prefix`, etc.) reste en YAML/PHP `config/packages/polysource.yaml`, mais pas les Resources.

## Conséquences

### Positives

- **Type-safety** : refactoring IDE (renommer un field cascade automatiquement).
- **Découverte automatique** : l'attribute `#[AsResource]` permet l'autoconfiguration via Symfony DI sans toucher à `services.yaml`.
- **DX simple pour les cas triviaux** : un Resource read-only minimal tient en 15 lignes.
- **DX riche pour les cas complexes** : `configureFields(string $page)` accepte toute la logique PHP nécessaire.

### Négatives

- Deux mécanismes coexistent → doc utilisateur doit expliquer quand utiliser quoi.
- L'attribute `#[AsResource]` ne capture pas tout — confusion possible. Mitigation : doc claire que l'attribute est un **raccourci de registration**, les méthodes restent la source de vérité.

### Discoverability

Un Resource est découvert si :
1. Il porte l'attribute `#[AsResource]` (méthode recommandée), OU
2. Il est explicitement enregistré dans `services.yaml` avec le tag `polysource.resource`.

Pas d'auto-scan de tous les fichiers PHP du projet — trop magique.

### Comparaison avec les concurrents

- **EasyAdmin** : interface methods uniquement (`configureCrud`, `configureFields`, etc.). Pas d'attribute.
- **Filament** : interface methods + builders fluides (Filament-style à introduire en v0.3+).
- **API Platform** : attributes uniquement (`#[ApiResource]`).
- **Sonata** : interface methods (verbeux).

Notre choix hybride se rapproche de ce que **Symfony lui-même fait** (`#[AsCommand]`, `#[AsEventListener]` + interfaces) — c'est l'idiome moderne de la framework.

## Références

- [PHP 8.0 Attributes](https://wiki.php.net/rfc/attributes_v2)
- [Symfony Attributes for autoconfiguration](https://symfony.com/blog/new-in-symfony-6-1-service-autoconfiguration-with-attributes)
- API Platform `#[ApiResource]` — modèle d'inspiration
