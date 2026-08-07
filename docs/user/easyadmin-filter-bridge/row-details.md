# Expandable row details

*Since v1.1.0 — `polysource/easyadmin-filter-bridge`*

Let a listing row expand in place to show detail content loaded
lazily from the server — extra fields, a custom panel, or a table of
related records — without leaving the page:

```text
▸ Order #123    Customer A    150 €    PAID
▾ Order #124    Customer B     90 €    PAID
  ┌──────────────────────────────────────────────┐
  │ Order items                                  │
  │ Product A        2 × 25 €                    │
  │ Product B        1 × 40 €                    │
  └──────────────────────────────────────────────┘
▸ Order #125    Customer C    210 €    REFUNDED
```

The feature is **opt-in per entity**: a listing without a provider is
byte-identical to before. Nothing is preloaded — the detail is
fetched only when the user opens the row, then kept client-side for
subsequent reopenings.

## 1. Declare a provider

A provider maps one Doctrine entity to its detail content. The
minimal form is a template name:

```php
use App\Entity\Order;
use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;

final class OrderRowDetailProvider extends AbstractRowDetailProvider
{
    public function getSupportedEntity(): string
    {
        return Order::class;
    }

    protected function template(): string
    {
        return 'admin/order/_row_detail.html.twig';
    }
}
```

With `autoconfigure: true` (the Symfony default) the service is
picked up automatically — the class just needs to be registered as a
service. No route, no controller, no JS to write.

## 2. Add the expansion column

Yield the chevron field first in your CrudController:

```php
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;

public function configureFields(string $pageName): iterable
{
    yield Polysource::rowDetail();
    yield IdField::new('id');
    // ...
}
```

The cell renders only on the index page, and only when a provider
exists for the CRUD's entity (and the permission below allows it) —
so the field is safe to yield unconditionally.

## 3. Write the template

The template receives the row's entity as `entity`, plus whatever
your provider's `context()` returns:

### Example A — a few extra fields

```twig
{# templates/admin/user/_row_detail.html.twig #}
<dl class="row mb-0">
    <dt class="col-sm-3">Last login</dt>
    <dd class="col-sm-9">{{ entity.lastLoginAt|date('Y-m-d H:i') }}</dd>
    <dt class="col-sm-3">Notes</dt>
    <dd class="col-sm-9">{{ entity.internalNotes|nl2br }}</dd>
</dl>
```

### Example B — custom panel with extra context

```php
protected function context(object $entity): array
{
    return ['timeline' => $this->auditReader->timelineFor($entity)];
}
```

```twig
<ul class="timeline">
    {% for event in timeline %}
        <li>{{ event.happenedAt|date }} — {{ event.label }}</li>
    {% endfor %}
</ul>
```

### Example C — related records ("nested listing")

Render the row's children as a table — the most common master/detail
shape:

```twig
{# templates/admin/order/_row_detail.html.twig #}
<table class="table table-sm mb-0">
    <thead>
        <tr><th>Product</th><th>Qty</th><th>Price</th></tr>
    </thead>
    <tbody>
        {% for item in entity.items %}
            <tr>
                <td>{{ item.productName }}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ item.price|format_currency('EUR') }}</td>
            </tr>
        {% endfor %}
    </tbody>
</table>
```

Because the template renders lazily server-side, `entity.items`
initializes its Doctrine collection for **one** row per request —
opening a row never triggers an N+1 across the listing.

> A *full* embedded Polysource listing (own filters, sorting,
> pagination inside the detail zone) is deliberately not part of
> v1.1 — it needs request-context isolation that is on the roadmap.
> The related-records table above covers the read-only 80 % case.

## Permissions

Seeing a row does not have to imply seeing its detail. Declare a
voter attribute on the provider:

```php
public function getPermission(): ?string
{
    return 'ORDER_DETAIL_VIEW';
}
```

The attribute is checked with the **row's entity as voter subject**,
twice: before rendering the chevron (cosmetic), and on the backend
endpoint before rendering content (authoritative). Your regular
Symfony voter decides per record:

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    return $subject->getOwner() === $token->getUser();
}
```

Fail-closed rule: a declared attribute with no security layer wired
denies access.

## Refresh behavior

By default the first response is cached client-side; closing and
reopening a row does not refetch. For volatile content, opt into
refetching on every open:

```php
yield Polysource::rowDetail()->reloadOnOpen();
```

## Without JavaScript

Per [ADR-027](../../adr/0027-progressive-enhancement.md), the
chevron is a real link: without Stimulus it navigates to a
standalone page rendering the same detail content with a back link.
The `polysource--row-details` controller (auto-registered via
AssetMapper like the bridge's other controllers) upgrades it to
in-place expansion with loading / error / retry states, ARIA
attributes, and multi-row support.

## Requirements

- Bridge routes imported (automatic since v0.5.4 via `Bundle::boot()`,
  or via the manual `routes.php` import) — the endpoint is
  `GET /admin/polysource/row-detail/{entityFqcn}/{id}`.
- Works identically on EasyAdmin 4.24+ and 5.x — the chevron is a
  regular EA field, no template fork involved.
