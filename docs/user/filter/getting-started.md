# `polysource/filter` — getting started

This page walks you from zero to a working, session-persisted filter
form on a non-EasyAdmin Symfony controller. If you're integrating with
EasyAdmin (4.24+ or 5.0+), install
[`polysource/easyadmin-filter-bridge`](../easyadmin-filter-bridge/whats-new.md)
instead — it composes the same primitives and wires them into EA's
extension points. This guide is for hosts who want to use the
primitives directly.

## What you get

- A `FilterCollection` immutable model (a list of `FilterCriterion`s).
- A `FilterCollectionType` Symfony Form type that renders one widget
  per declared filter, hydrates from / to the model on submit, and
  exposes the data via `$form->getData()`.
- A `FilterService` that persists/restores collections in the HTTP
  session, scoped by an arbitrary id you choose (typically the
  controller class FQCN).
- A Twig `filter_tags(collection, definitions)` function that renders
  the active filters as removable chips above your list.
- Two Stimulus controllers — `polysource--filter-chips` (chip removal,
  overflow toggle) and `polysource--filter-subpanel` (modal/offcanvas
  open/close, tabs, focus management).
- A 3-tag DI pipeline (`polysource.filter.mapper`,
  `…formatter`, `…renderer`) that lets you add custom filter types
  without touching the bundle's internals.

What you do **not** get from this package: a query applier. A
`FilterCollection` is a description of the user's intent — translating
that to a SQL `WHERE`, a Redis scan, or a Meilisearch facet query is
the host's job (or the bridge's, in the EasyAdmin case). See
[ADR-014](../../adr/0014-datasource-lifecycle-deferred.md) for the
roadmap.

## 1. Install

```bash
composer require polysource/filter
```

Register the bundle in `config/bundles.php`:

```php
return [
    // …
    Polysource\Filter\PolysourceFilterBundle::class => ['all' => true],
];
```

The bundle ships **three** Stimulus controllers in
`assets/controllers/`:
`polysource--filter-modal-layout` (tab + group filter layout),
`polysource--filter-chips` (chip × close button),
`polysource--filter-subpanel` (right-anchored slide-in panel mode).

**v0.1.x — manual registration.** Auto-discovery via the standard
`extra.symfony.controllers` manifest is scheduled for v0.2 (cf. the
B3a follow-up tracked in `CHANGELOG.md`). For now, register the
controllers manually in your host's Stimulus application:

```js
// assets/bootstrap.js (or wherever you start Stimulus)
import { Application } from '@hotwired/stimulus';
import FilterModalLayoutController
    from '../vendor/polysource/filter/assets/controllers/filter_modal_layout_controller.js';
import FilterChipsController
    from '../vendor/polysource/filter/assets/controllers/filter_chips_controller.js';
import FilterSubpanelController
    from '../vendor/polysource/filter/assets/controllers/filter_subpanel_controller.js';

const app = Application.start();
app.register('polysource--filter-modal-layout', FilterModalLayoutController);
app.register('polysource--filter-chips', FilterChipsController);
app.register('polysource--filter-subpanel', FilterSubpanelController);
```

**Without Stimulus** the bundle still renders filters server-side
(SQL `WHERE`, chips bar markup, session persistence) — only the
client-side widgets (tab layout, chip × button, subpanel mode) go
inert. See [installation.md](../installation.md) for the full
Stimulus prerequisite matrix.

## 2. Declare a filter collection

`FilterDefinition` describes one available filter. The collection of
definitions is just a `list<FilterDefinition>` — you can keep it in
your controller, in a service, in a configuration file, anywhere.

```php
use Polysource\Filter\Model\FilterDefinition;

$definitions = [
    FilterDefinition::new('text', 'name', 'Name'),

    FilterDefinition::new('numeric', 'price', 'Price')
        ->withFormSpec([
            'quick_ranges' => [
                ['label' => '< 50€',     'min' => null, 'max' => 50],
                ['label' => '50–200€',   'min' => 50,   'max' => 200],
                ['label' => '200–400€',  'min' => 200,  'max' => 400],
            ],
        ]),

    FilterDefinition::new('datetime', 'createdAt', 'Created at')
        ->withFormSpec([
            'presets' => ['today', 'last_7_days', 'this_month'],
            'show_clear' => true,
        ]),

    FilterDefinition::new('choice', 'status', 'Status')
        ->withGroup('Status')                           // <- multi-group UI
        ->withFormSpec([
            'choices' => [
                'Draft'     => 'draft',
                'Published' => 'published',
                'Archived'  => 'archived',
            ],
            'multiple' => true,
        ]),
];
```

The `name` field is the routing key into the pipeline — the bundle
ships seven default mappers/formatters/renderers (`text`, `numeric`,
`datetime`, `boolean`, `choice`, `array_list`, `entity`). Hosts add
their own by tagging services `polysource.filter.{mapper,formatter,renderer}`
(see [§5 — custom filter types](#5--custom-filter-types) below).

The `formSpec` is forwarded to the FormType chosen by the renderer;
its shape depends on which FormType that is. The seven defaults all
accept the standard Symfony Form options (`label`, `placeholder`,
`required`, …) and add the bridge-specific ones documented in the
[bridge what's-new](../easyadmin-filter-bridge/whats-new.md) matrix.

## 3. Render the form in a controller

```php
use Polysource\Filter\Form\Type\FilterCollectionType;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Service\FilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

final class ProductsController extends AbstractController
{
    public function __construct(private readonly FilterService $filters) {}

    public function index(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $collectionId = self::class;                       // any string scope
        $collection = $this->filters->load($collectionId)  // restore previous
            ?? new FilterCollection($collectionId);

        $form = $this->createForm(FilterCollectionType::class, $collection, [
            'collection_id' => $collectionId,
            'definitions'   => $this->definitions(),       // <- from §2
            // 'mode' => 'subpanel',                       // optional, default 'integrated'
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var FilterCollection $collection */
            $collection = $form->getData();
            $this->filters->save($collection);
        }

        return $this->render('products/index.html.twig', [
            'form'        => $form->createView(),
            'collection'  => $collection,
            'definitions' => $this->definitions(),
            'rows'        => $this->load($collection),     // your data load
        ]);
    }
}
```

The form **always returns a `FilterCollection`**, not an associative
array — `FilterHydrator` does the round-trip for you. Both
`load()` and `save()` are no-ops when no session is available (CLI,
stateless route): your controller stays interactable.

## 4. Render in Twig

The two ingredients you need: the form (which renders the inputs) and
the chips bar (which shows what's currently applied and lets users
remove individual filters).

```twig
{# templates/products/index.html.twig #}

{# 1. Active-filter chips, above the list #}
{{ filter_tags(collection, definitions) }}

{# 2. The filter form itself #}
{{ form_start(form, { attr: { method: 'GET' } }) }}
    {{ form_widget(form) }}
    <button type="submit" class="btn btn-primary">Apply</button>
{{ form_end(form) }}

{# 3. The list itself — entirely up to you #}
<table>
    {% for row in rows %}
        <tr>{# … #}</tr>
    {% endfor %}
</table>
```

The default form theme is `@PolysourceFilter/modes/integrated.html.twig`.
For the side subpanel mode, pass `mode: 'subpanel'` when creating the
form *and* point your template at the matching theme:

```twig
{% form_theme form '@PolysourceFilter/modes/subpanel.html.twig' %}
```

The subpanel template ships with Bootstrap 5 markup (offcanvas-style)
and uses the `polysource--filter-subpanel` Stimulus controller for
open/close/ESC/focus management. Override the template if you don't
use Bootstrap.

## 5. Multi-group filters (optional)

Add `->withGroup('Group label')` to a definition and the integrated
template renders each group in its own collapsible `<details>` section;
the subpanel template renders one tab per group. Definitions without
a group land in an unlabelled section displayed first.

## 6. Custom filter types

The bundle's pipeline is three independent service tags. To add a
custom filter type, register one of each:

```yaml
# config/services.yaml
services:
    App\Filter\GeoRadiusMapper:
        tags: [{ name: polysource.filter.mapper, key: geo_radius }]

    App\Filter\GeoRadiusFormatter:
        tags: [{ name: polysource.filter.formatter, key: geo_radius }]

    App\Filter\GeoRadiusRenderer:
        tags: [{ name: polysource.filter.renderer, key: geo_radius }]
```

The `key` attribute is the routing name. Alternatively, define a
public `NAME` constant on each class — the compiler pass falls back
to it when no `key` attribute is provided.

Now use it like any default:

```php
FilterDefinition::new('geo_radius', 'location', 'Within radius')
    ->withFormSpec(['form_type' => App\Form\GeoRadiusType::class]);
```

The contracts the three services must respect:

- `FilterMapperInterface` — `fromRequest(toFormData(c)) == c`
  (round-trip invariant). Reject malformed input with
  `\InvalidArgumentException`; the form-level error handling is on the
  caller.
- `FilterFormatterInterface::format()` — **MUST return plain text**
  (no HTML, no script). The chips template handles escaping; hosts
  who template the output elsewhere would otherwise expose XSS.
- `FilterRendererInterface::getFormType()` — return a class-string
  pointing to a `FormTypeInterface`. The `FormType` reads the
  `formSpec` keys verbatim as its options.

## 7. Persisted-filter id strategy

`FilterService::save()` stores the collection under
`polysource.filter.{xxh128(collection.id)}`. The id is your call —
`self::class` (controller FQCN) is a sensible default for one-off
admins. For tenant-scoped admins, use `self::class . ':' . $tenantId`.
If you want different lists in the same controller to remember
different filters (e.g. "drafts" vs "published"), suffix accordingly.

The hash keeps session keys short (32 hex chars) regardless of how
long your scope id grows.

## What's deferred to v0.2+

- A cookie-cutter "add a filter to a list" controller wrapper. Today
  you wire `FilterCollectionType` + `FilterService` by hand.
- A datasource-side appliers (Doctrine, Redis, Meilisearch). Today the
  bridge owns the EasyAdmin-on-Doctrine path; other adapters will
  arrive alongside the
  [datasource lifecycle](../../adr/0014-datasource-lifecycle-deferred.md).
- A `FilterCollection` URL-query serializer (so links can carry filters
  without a session). Today the form roundtrips through the request,
  but persistent links require a tiny custom encoder.

## See also

- [ADR-013](../../adr/0013-filter-package-architecture.md) — design
  rationale for the form/datasource separation and the 3-tag pipeline.
- [ADR-014](../../adr/0014-datasource-lifecycle-deferred.md) — the
  Factory→Builder→Loader datasource lifecycle deferred to v0.2+.
- [Bridge what's-new](../easyadmin-filter-bridge/whats-new.md) — the
  EasyAdmin-side surface: which built-in filters are upgraded and how.
