# ADR-006 — `EnvelopeMapper` payload serialization

- **Date** : 2026-05-02
- **Statut** : Accepté (révisé en Phase 4 — fallback `print_r` au lieu de `var_export`)
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

### Option D — JSON-first avec fallback `print_r`

Tenter `json_encode(JSON_THROW_ON_ERROR)`. Si ça échoue, fallback sur `print_r($message, true)`.

**Pour** : robuste (jamais d'erreur, jamais de warning), lisible dans la majorité des cas. `print_r` détecte les références circulaires (`*RECURSION*`) et tolère les ressources.
**Contre** : 2 chemins de code à tester.

### Option D' — JSON-first avec fallback `var_export` (rejeté en Phase 4)

Variante initialement envisagée. Rejetée à l'implémentation parce que `var_export` :
- Émet un `E_WARNING` sur les références circulaires (échoue avec `failOnWarning="true"` dans PHPUnit)
- Retourne la chaîne littérale `'NULL'` pour les `resource` PHP au lieu de signaler l'erreur

`print_r` n'a aucun de ces deux problèmes — d'où le swap.

### Option E — Symfony VarDumper

Utiliser `Symfony\Component\VarDumper\Cloner\VarCloner` + `HtmlDumper`.

**Pour** : très lisible, gère les références circulaires, formats riches.
**Contre** : ajoute une dépendance. Sortie HTML, pas du JSON utilisable programmatiquement.

## Décision

**Option D — JSON-first avec fallback `print_r`** est retenue, avec un flag `format` dans le `DataRecord` pour permettre au template Twig de choisir le bon rendu.

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
                'payload_format' => $payload['format'],   // 'json' | 'print_r'
            ],
            rawSource: $envelope,
        );
    }

    /**
     * @return array{value: string, format: 'json'|'print_r'}
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
            return ['value' => print_r($message, true), 'format' => 'print_r'];
        }
    }
}
```

### Côté template Twig

```twig
{# templates/field/code.html.twig #}
{% set format = record.get('payload_format') ?? 'json' %}
<pre><code class="language-{{ format == 'json' ? 'json' : 'plain' }}">
{{ record.get('payload') }}
</code></pre>
```

Le template `code.html.twig` shippé en Phase 3 fait déjà ce branchement implicite (JSON pretty-printed via `json_encode`, `print_r` rendu tel quel). La coloration syntaxique via Prism arrivera en Phase 9.

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

### Tests à écrire en Phase 4 (livrés)

- Message avec POPO simple → JSON ✅
- Message avec `DateTimeImmutable` → JSON (DateTime implémente JsonSerializable) ✅
- Message avec `Closure` → **JSON** ⚠️ : surprise à l'implémentation, `json_encode` n'échoue pas sur les Closures — il les sérialise en `{}` (pas de propriétés publiques). Le test correspondant a été ajusté pour assert `'json'` au lieu de `'var_export'`.
- Message avec référence circulaire → fallback `print_r` (qui imprime `*RECURSION*`) ✅
- Message avec resource handle → fallback `print_r` ✅
- Message avec 100 KB → tronqué avec marker ✅

### Sécurité

`print_r` ne fait pas d'évaluation (contrairement à `eval()`). Sa sortie reste de la chaîne pure — aucune injection possible. Le template Twig échappe automatiquement la sortie via `{{ ... }}` ; ne **jamais** la rendre via `|raw` côté theme.

## Références

- [PHP json_encode flags](https://www.php.net/manual/en/function.json-encode.php)
- [Symfony Messenger Envelope](https://symfony.com/doc/current/messenger.html#message-classes)
- EasyAdmin n'a pas ce problème — il ne gère pas Messenger.
