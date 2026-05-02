# ADR-006 — `EnvelopeMapper` payload serialization

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

`MessengerFailedDataSource` (Phase 4) doit transformer un `Symfony\Component\Messenger\Envelope` en `DataRecord`. La partie sensible est le **payload du message** : sérialisé pour affichage dans le détail UI.

Un message Messenger peut contenir :
- des objets `final readonly class` simples (string/int props)
- des objets avec `Closure` ou ressources (non-sérialisables)
- des références circulaires
- des `DateTimeImmutable`, `Uuid`, etc.
- des collections imbriquées

L'utilisateur de Polysource veut **voir** ce qu'il y avait dans le message pour comprendre pourquoi il a échoué. Le rendu UI doit être lisible et ne **jamais** crasher.

## Options envisagées

### Option A — `serialize()` PHP natif

**Pour** : universel, capture tout.
**Contre** : illisible (`O:5:"Class":2:{...}`), inutile pour debug humain.

### Option B — `print_r()` / `var_export()`

**Pour** : très lisible.
**Contre** : pas structuré, pas de coloration syntaxique JSON dans l'UI, gestion difficile des références circulaires.

### Option C — `json_encode()`

```php
$payload = json_encode($message, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

**Pour** : standard, facile à colorier, structuré.
**Contre** : échoue sur les objets non-`JsonSerializable` (Closures, ressources, références circulaires).

### Option D — JSON-first avec fallback `var_export`

Tenter `json_encode(JSON_THROW_ON_ERROR)`. Si ça échoue, fallback sur `var_export()` formaté.

**Pour** : robuste (jamais d'erreur), lisible dans la majorité des cas.
**Contre** : 2 chemins de code à tester.

### Option E — Symfony VarDumper

Utiliser `Symfony\Component\VarDumper\Cloner\VarCloner` + `HtmlDumper`.

**Pour** : très lisible, gère les références circulaires, formats riches.
**Contre** : ajoute une dépendance. Sortie HTML, pas du JSON utilisable programmatiquement.

## Décision

**Option D — JSON-first avec fallback** est retenue, avec un flag `format` dans le `DataRecord` pour permettre au template Twig de choisir le bon rendu.

```php
namespace Polysource\Adapter\Messenger\DataSource;

use Polysource\Core\Query\DataRecord;
use Symfony\Component\Messenger\Envelope;

final readonly class EnvelopeMapper
{
    public function map(Envelope $envelope): DataRecord
    {
        $message = $envelope->getMessage();
        $payload = $this->serializeMessage($message);

        return new DataRecord(
            identifier: $this->extractIdentifier($envelope),
            properties: [
                'message_class' => $message::class,
                'failed_at' => $this->extractFailedAt($envelope),
                'exception_class' => $this->extractExceptionClass($envelope),
                'exception_message' => $this->extractExceptionMessage($envelope),
                'payload' => $payload['value'],
                'payload_format' => $payload['format'],   // 'json' | 'var_export'
            ],
            rawSource: $envelope,
        );
    }

    /**
     * @return array{value: string, format: 'json'|'var_export'}
     */
    private function serializeMessage(object $message): array
    {
        try {
            $value = json_encode(
                $message,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            return ['value' => $value, 'format' => 'json'];
        } catch (\JsonException) {
            return ['value' => var_export($message, true), 'format' => 'var_export'];
        }
    }
}
```

### Côté template Twig

```twig
{# templates/field/code.html.twig #}
{% set format = field.value.payload_format ?? 'json' %}
<pre><code class="language-{{ format == 'json' ? 'json' : 'php' }}">
{{ field.value.payload }}
</code></pre>
```

Bootstrap CSS gère la coloration via `prismjs` chargé en CDN dans `layout.html.twig`.

### Tronquage

Pour les payloads très longs (> 50 KB), le mapper tronque avec un message clair :

```php
if (strlen($value) > 50_000) {
    $value = substr($value, 0, 50_000) . "\n\n[... truncated, original size: " . strlen($value) . " bytes]";
}
```

## Conséquences

### Positives

- **Robuste** : ne crashera jamais sur un message exotique.
- **Lisible** : 95 % des cas sont des messages simples qui sérialisent en JSON propre.
- **Extensible** : un futur adapter peut ajouter d'autres formats (XML, MsgPack, etc.).
- **Testable** : 2 paths à couvrir (json OK / json fail).

### Négatives

- Le payload `var_export` n'est pas structuré → pas de filtre JSON-path possible. Acceptable pour v0.1.
- L'utilisateur doit comprendre pourquoi parfois c'est JSON et parfois non. Documenté.

### Tests à écrire en Phase 4

- Message avec POPO simple → JSON
- Message avec `DateTimeImmutable` → JSON (DateTime implémente JsonSerializable)
- Message avec Closure → fallback `var_export`
- Message avec référence circulaire → fallback (`json_encode` throw)
- Message avec 100 KB → tronqué avec marker

### Sécurité

`var_export` n'évalue pas le code (contrairement à `eval()`). Mais il faut s'assurer que la sortie est échappée HTML dans le template Twig (auto avec `{{ ... }}`, mais pas avec `|raw`).

## Références

- [PHP json_encode flags](https://www.php.net/manual/en/function.json-encode.php)
- [Symfony Messenger Envelope](https://symfony.com/doc/current/messenger.html#message-classes)
- EasyAdmin n'a pas ce problème — il ne gère pas Messenger.
