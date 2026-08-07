# ADR-0033 — Expandable row details : champ EA virtuel + provider par entité, pas de fork de table

- **Date** : 2026-08-07
- **Statut** : Accepté
- **Décide pour** : v1.1.0+ (`polysource/easyadmin-filter-bridge`, prérequis dans `polysource/symfony-bundle`)
- **En lien avec** : [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-027 — Progressive enhancement](./0027-progressive-enhancement.md), [ADR-028 — Scope discipline](./0028-scope-discipline.md), [ADR-0032 — FeatureLoader DI decomposition](./0032-featureloader-di-decomposition.md)

## Contexte

Besoin récurrent côté hôtes EA : déplier une ligne de listing pour
afficher un contenu de détail (champs supplémentaires, panneau
custom, enregistrements liés) sans quitter la page. ADR-028 place le
« detail-page UX » explicitement dans le périmètre ; la borne à
respecter est le read-only (le « quick edit popover » est décliné).

Trois architectures étaient possibles pour insérer le contrôle
d'expansion dans la table EA :

1. **Fork du block `table_body_row`** (ce que fait le showcase pour
   ses démos) — ✗ le markup du row diffère entre EA 4.24 et EA 5.x
   (composants `<twig:ea:*>` 5-only) : le bridge devrait maintenir
   deux forks synchronisés avec l'upstream, exactement l'anti-pattern
   que le bridge a toujours évité (il n'a jamais possédé une ligne de
   markup de table).
2. **Injection JS pure** (le contrôleur Stimulus crée la colonne) —
   ✗ viole ADR-027 : sans JS, pas de feature.
3. **Champ EA virtuel** (`RowDetailField`) rendu par le système de
   fields d'EA — ✓ EA rend lui-même une cellule par ligne via le
   template du champ, identique sur 4.24 et 5.x ; la baseline no-JS
   est un vrai lien.

Côté contenu, le détail doit être configurable par entité sans
imposer un contrôleur/route par ressource, et la permission doit se
décider **par ligne** (voir un enregistrement n'implique pas voir
son détail).

## Décision

### API : un provider par entité + un champ facade

- `RowDetailProviderInterface` (`getSupportedEntity()`,
  `getPermission()`, `getRowDetail(object): RowDetail`), autoconfiguré
  sur le tag `polysource.row_detail_provider`, indexé par
  `RowDetailRegistry` (dernier enregistré gagne — sémantique
  d'override DI).
- `AbstractRowDetailProvider` couvre le cas 80 % : template + contexte.
- `RowDetail` est un VO template+contexte. Volontairement
  template-only en v1.1 : « quelques champs », « panneau custom » et
  « table d'enregistrements liés » sont tous des templates Twig du
  point de vue du renderer.
- `Polysource::rowDetail()` / `RowDetailField::new()` — champ EA
  virtuel (`onlyOnIndex`, label false, template de cellule dédié),
  option `reloadOnOpen()`.

### Transport : endpoint générique lazy

`GET /admin/polysource/row-detail/{entityFqcn}/{id}` (même moule que
matching-count / export, wired par `RowDetailLoader` per ADR-0032,
listé dans `routes.php` ET `BundleRouteLoader` — les deux listes
doivent rester synchrones). Deux modes : `?fragment=1` (fragment nu
pour l'injection) et pleine page standalone (baseline ADR-027, back
link via `SafeReferer`). Réponses `no-store`.

### Permission : attribut du provider, entité en subject, fail-closed

L'attribut déclaré par le provider est vérifié avec **l'entité de la
ligne comme subject de voter** — au rendu du chevron (cosmétique) et
sur l'endpoint (autoritaire). Attribut déclaré + pas de couche
sécurité câblée = refus. Contrairement aux endpoints v0.3-v0.5 qui
délèguent tout au firewall hôte, le row detail embarque son gate :
c'est du contenu par enregistrement, le niveau resource ne suffit pas.

### Prérequis livré dans le même minor : gating par ligne côté bundle

`ControllerSupport` passe désormais le `DataRecord` en subject des
checks d'actions inline (rendu ET exécution) et peuple le contexte
`isDisplayed()` (`record`, `subject` = rawSource, `page`). Corrige au
passage les boutons de transition workflow invisibles (contexte vide
→ `ApplyTransitionAction::isDisplayed()` retournait toujours false).

### Frontend : un contrôleur Stimulus par chevron

`polysource--row-details` : preventDefault + stopPropagation sur le
lien, états `collapsed/loading/expanded/error` (exposés via
`data-polysource-row-detail-state` sur le `<tr>` injecté), cache
mémoire après le premier chargement, erreur locale + retry sans
casser le listing, `aria-expanded`, plusieurs lignes ouvertes
simultanément. Labels passés en values Stimulus depuis le template
(le JS reste sans catalogue).

### Moteur natif (phase 2, même minor)

Le même modèle est porté sur le listing natif :
`HasRowDetailsInterface` opt-in sur la Resource (`hasRowDetail()`
gate par ligne appelé au rendu de l'index — donc bon marché —,
`getRowDetail()` appelé uniquement par l'endpoint lazy,
`getRowDetailPermission()` avec le `DataRecord` en subject),
5e route générée `GET {prefix}/{slug}/{id}/detail-panel` servie par
`RowDetailPanelController` sur le pipeline `PolysourceView` (qui
gagne un champ additif `headers` pour le `no-store`). Le VO
`RowDetail` déménage dans `polysource/core` (2 consommateurs =
budget ADR-018 respecté) et le contrôleur Stimulus dans
`polysource/filter` (dépendance commune, précédent
`saved_views_dropdown` v0.1.4).

### Listing imbriqué (phase 3, même minor) — le blocker contexte est levé PAR DESIGN

`RowDetail::listing(resource, parentFilters, pageSize)` embarque un
listing Polysource natif comme détail. Le blocker documenté
(`AdminContextProvider` mono-contexte) ne s'applique **pas** :
l'`EmbeddedListingRenderer` construit sa `DataQuery` et rend la
table sans repasser par `IndexController`/`AdminContextResolver` —
aucun second `AdminContext` n'est créé. Chaque panneau étant sa
propre requête HTTP (architecture lazy-fetch), la pagination
embarquée vit sur un paramètre dédié `rd_page` de l'URL du panneau
et ne peut pas entrer en collision avec la query-string du listing
extérieur. Sans JS, les liens du pager naviguent en pleine page
(baseline ADR-027) ; injectés, ils sont interceptés et rafraîchissent
le panneau en place.

Bornes du listing embarqué : read-only strict (table + pager — pas
d'actions, pas de bulk, pas de chevrons imbriqués, donc pas de
récursion), permission de vue de la ressource enfant vérifiée, tri
et filtres utilisateur internes hors périmètre v1.1 (le scoping
vient de `parentFilters` uniquement). Côté bridge, le renderer est
une référence optionnelle : `RowDetail::listing()` sur un hôte
bridge-alone lève une `LogicException` explicite demandant
d'installer `polysource/symfony-bundle`.

## Conséquences

- Un listing sans provider est strictement inchangé (opt-in, zéro
  coût). Aucun breaking change ; tout est `@since 1.1.0`.
- Le mode accordéon (`multiple=false`) n'est pas retenu : chaque
  chevron est autonome, un mode exclusif imposerait un état partagé
  inter-contrôleurs pour un gain UX discutable.
- La duplication `routes.php` / `BundleRouteLoader::CONTROLLERS`
  gagne un 7e élément — le test `BundleRouteLoaderTest` casse si on
  oublie l'un des deux.
- Correctif embarqué : les contrôleurs du bundle jetaient la
  `ResourceNotFoundException` de core (RuntimeException nue → 500)
  pour un record inconnu ; ils jettent désormais
  `NotFoundHttpException` → 404 corrects sur detail, action et
  detail-panel.
