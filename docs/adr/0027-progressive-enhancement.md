# ADR-027 — Progressive enhancement : tout interactif a un fallback serveur

- **Date** : 2026-05-13
- **Statut** : Accepté
- **Décide pour** : v0.2.0+ (retrofit) puis toute feature interactive future
- **En lien avec** : [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-018 — Admin plugin interface and public contracts](./0018-admin-plugin-interface-and-public-contracts.md), [ADR-019 — Saved views architecture](./0019-saved-views-architecture.md), [ADR-028 — Scope discipline](./0028-scope-discipline.md)

## Contexte

La promesse marketing de Polysource est *drop-in* : `composer require
polysource/easyadmin-filter-bridge`, on n'écrit pas une ligne de JS, et
les filtres de l'app EA existante deviennent immédiatement plus
ergonomiques. Cette promesse n'a de sens que si **toutes** les features
de Polysource fonctionnent dans un hôte qui ne tourne aucune pipeline
JS moderne — apps héritées sur Webpack Encore manuel avec
`.addEntry()`, frontends jQuery / Vue / vanilla JS, ou simplement les
hôtes qui n'ont jamais configuré `@hotwired/stimulus` +
`@symfony/stimulus-bridge`.

La session de dogfooding du 2026-05-12 a révélé que plusieurs features
v0.1.x **violent** cette promesse :

| Feature | Comportement sans Stimulus | Sévérité |
|---|---|---|
| Tabs / groups (`Polysource::tab`, `Polysource::group`) | UI rendue mais inactive — clics sans effet | Bloquant |
| Boutons presets (DateTimeFilter `presets:`) | Boutons visibles mais inertes | Bloquant |
| Boutons quick_ranges (NumericFilter) | Boutons visibles mais inertes | Bloquant |
| Chip × close | × visible, ne ferme rien | Bloquant |
| Subpanel mode | Mode panneau ne s'ouvre jamais | Bloquant |
| Saved-views dropdown | `<select>` ne déclenche pas l'apply | Majeur |

Les "boutons visibles mais inertes" sont *pires* que pas de boutons :
l'utilisateur perd confiance plus vite face à un UI cassé qu'absent. Et
forcer chaque hôte à instrumenter Stimulus pour des features qu'il n'a
peut-être même pas activées casse l'argument *drop-in* dès la première
phrase du README.

Trois approches possibles :

1. **Stimulus comme dépendance dure** — documenter "Stimulus requis",
   bloquer l'install via `composer.json` ou un compiler pass DI qui
   crash à boot si StimulusBundle n'est pas présent. ✗ Tue le scope
   ADR-012 et l'adoption EA (audience EA = tous hôtes EA, dont
   beaucoup sans Stimulus).
2. **Server-side baseline + Stimulus optional** — toute feature
   marche en HTML pur (formulaires, `<a href>`, `<details>`,
   `<input type="radio">:checked`), Stimulus enrichit sans gating.
3. **Statu quo** — accepter que certaines features ne marchent qu'avec
   Stimulus, documenter quelque part. ✗ La dogfooding a montré que ça
   ne se voit pas avant l'intégration réelle, donc ça empoisonne les
   adoptions silencieusement.

## Décision

**Toute feature interactive de Polysource DOIT fonctionner
server-side first.** Stimulus (et tout autre JS) est une couche
d'amélioration *optionnelle* qui peut ajouter du confort UX —
no-reload, animations, focus management, in-place updates — mais
**jamais** une condition de fonctionnement de la feature.

Ce qu'on appelle *server-side baseline* :

- L'action utilisateur traverse un round-trip HTTP (POST de formulaire,
  `<a href>` GET, `<button type="submit" formaction="…">`)
- Le serveur produit l'état suivant (page rerendue ou redirect)
- Aucune dépendance JS pour que ce parcours minimal fonctionne

Stimulus, quand il est chargé, peut **intercepter** ces interactions
pour les exécuter sans page reload, animer la transition, ou gérer le
focus. C'est purement additif.

### Patterns d'implémentation

| Feature | Pattern baseline | Pattern enrichi (Stimulus) |
|---|---|---|
| Tabs / groups | `<input type="radio" name="tab" id="t1"> <label for="t1">…</label> <section class="tab-pane">…</section>` + CSS `:checked ~ .tab-pane`, ou `<details>` HTML5 | Listener qui remplace par `display:none` toggle sans round-trip ; gère l'ARIA |
| Boutons preset / quick-range | `<button type="submit" name="filter[date][value]" value="last_7_days" formaction="?reset">…</button>` — submit le form vers une URL pré-remplie | Action JS qui patche le champ + dispatch submit programmatique |
| Chip × close | `<a href="?filter[name1]=…">×</a>` — URL stripped du filtre supprimé | Click handler qui remove le chip + dispatch submit |
| Subpanel mode | Bootstrap offcanvas natif (`data-bs-toggle` est CSS-only en Bootstrap 5 via le checkbox trick, ou serveur-rendered classe `is-open`) | Animation slide via transitions CSS pilotées par Stimulus |
| Saved-views dropdown | `<select name="view" onchange="this.form.submit()"><option value="abc">…</option></select>` dans un `<form method="GET">` | Click handlers personnalisés + apply en place |
| Filter modal | `<details open>` ou page dédiée `?filter-edit=1` | Modal Bootstrap pilotée par Stimulus |

`onchange="this.form.submit()"` est un inline-handler court. Il est
**tolerable** parce que (a) il ne dépend d'aucune lib externe, (b)
c'est de l'HTML standard que tous les browsers supportent depuis 2005,
(c) il dégrade vers "l'utilisateur doit cliquer un bouton submit" si
le navigateur a JS désactivé — ce qui reste fonctionnel. Pour les
gardes-fous CSP plus stricts, les hôtes peuvent override le template
avec leur propre version sans inline handler.

### Périmètre du retrofit v0.2.0

La v0.2.0 retrofitte cette discipline sur les features v0.1.x
incompatibles :

- Tabs / groups → bascule sur le pattern radio+`:checked` (CSS pur)
- Subpanel mode → CSS body-class déjà serveur-rendered ; reste à
  retirer le contrôleur Stimulus body-class toggle qui n'apporte rien
- Boutons presets / quick_ranges / `show_clear` → *retirés* (cf.
  [ADR-028](./0028-scope-discipline.md), ils ne fillent pas un gap EA
  réel ; les pickers natifs HTML5 + EA Reset font le boulot mieux)
- Saved-views dropdown → déjà server-side compatible depuis v0.1.4 (le
  template `dropdown.html.twig` peut être un `<select>` HTML standard ;
  si une variante Stimulus existe, elle reste optionnelle)
- Chip × close → vérifier que c'est bien `<a href>` ; sinon corriger

Tout `polysource--filter-*` contrôleur Stimulus qui pilote une
feature *encore* présente après v0.2.0 doit être ré-audité : (a)
prouver que la feature marche sans lui, (b) documenter ce que le
contrôleur ajoute vs la baseline, (c) ne pas charger le contrôleur
quand `stimulus-bridge` n'est pas dans l'hôte (déjà géré par la
discovery `assets/controllers.json`).

### Garde-fous CI

- **Test E2E "no-Stimulus"** : un job de la matrice CI démarre le
  showcase avec `STIMULUS_DISABLED=1` (variable d'env qui empêche
  l'import du `bootstrap.js` côté JS, ou simplement un Twig override
  qui supprime le `<script>` Stimulus). Le job rejoue les parcours
  critiques — apply filter, saved-view apply, tabs switch, chip
  remove — et asserte que chacun aboutit à l'état attendu via
  round-trip HTTP. Ajout différé v0.2.0 ou v0.3.0 selon priorité.
- **Checklist PR** : `.github/PULL_REQUEST_TEMPLATE.md` (à créer ou
  étendre) inclut la case "Feature works without JS? Y/N — décrire
  le baseline path".

## Conséquences

### Positives

- **Drop-in promise préservée.** Aucun hôte n'est forcé d'instrumenter
  Stimulus pour que `composer require polysource/...` "marche pour
  de vrai". Adoption EA débloquée.
- **Accessibilité par défaut.** Le pattern `<input type="radio">` +
  `<label for>` est nativement keyboard-navigable et screen-reader
  friendly. Idem pour `<details>`. Stimulus enrichit, ne remplace pas.
- **Resilience CSP / no-JS.** Un user-agent qui désactive JS (audit
  d'accessibilité, scraping légitime, hôte avec CSP strict)
  continue de pouvoir utiliser l'admin.
- **Dette technique réduite.** Moins de contrôleurs Stimulus à
  maintenir = moins de surface bug, moins de matrice de versions JS.

### Négatives / coûts assumés

- **Retrofit v0.2.0 coûteux.** Tabs / subpanel / chip × close à
  reécrire ou re-vérifier sur tous les templates. Le travail est
  borné (~2 jours per [`project_roadmap_v020_to_v050`](../../README.md))
  mais pas trivial.
- **Templates plus verbeux.** Le pattern radio+CSS pour les tabs est
  10-15 lignes Twig vs 3 lignes "data-controller". Compromis assumé :
  la verbosité vit dans `twig-theme`, l'utilisateur final ne la voit
  pas.
- **Inline `onchange="this.form.submit()"` rebute certains hôtes
  CSP-strict.** Mitigation : c'est dans un template overridable ; les
  hôtes concernés peuvent fournir leur propre version.

### Neutres

- **Stimulus reste *first-class* dans l'écosystème Polysource** —
  l'ADR ne le déprécie pas, il refuse juste qu'il devienne une *dep
  dure*. Les contrôleurs `polysource--filter-chips` et
  `polysource--filter-subpanel` continuent de vivre dans
  `packages/filter/assets/` et s'auto-discovrent via
  `@symfony/stimulus-bundle` quand l'hôte l'a installé.
- **Aucun changement de version PHP / Symfony minimale.** Cette ADR
  ne touche pas à ADR-007 / ADR-015.

## Historique

- 2026-05-13 — rédigée après la session de dogfooding qui a révélé les
  6 violations listées plus haut. Roadmap v0.2.0 alignée pour
  retrofitter.
