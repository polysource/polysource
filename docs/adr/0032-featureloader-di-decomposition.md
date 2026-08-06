# ADR-0032 — Décomposition des extensions DI en FeatureLoaders

- **Date** : 2026-08-06
- **Statut** : Accepté
- **Décide pour** : `polysource/filter`, `polysource/easyadmin-filter-bridge` (et tout futur package multi-features)
- **En lien avec** : audit task #67 / M2 (`docs/maintainers/v0.9.0-architectural-cleanup.md`), [ADR-018](./0018-admin-plugin-interface-and-public-contracts.md)

## Contexte

Deux extensions DI concentraient le câblage de toutes les features
optionnelles de leur package : `PolysourceFilterExtension` (~440
lignes, 14 toggles) et `PolysourceEasyAdminFilterBridgeExtension`
(~550 lignes, 20 toggles). Chaque feature optionnelle (saved views,
préférences de colonnes, export, tokens d'URL…) y mêlait son gate
(`FeatureGate::…` + `class_exists`) et son wiring, dans une méthode
`load()` monolithique. La phase 1 (v0.10) a nommé les gates ; cette
phase 2 isole chaque feature dans sa classe.

## Décision

1. **Un `FeatureLoaderInterface` interne** (`@internal`), défini dans
   `Polysource\Filter\DependencyInjection` et réutilisé par le bridge
   (qui dépend déjà de `polysource/filter` — même précédent que
   `FeatureGate`) :

   ```php
   /** @internal */
   interface FeatureLoaderInterface
   {
       /** @param array<string, mixed>|mixed $bundles kernel.bundles */
       public function supports(mixed $bundles): bool;

       public function load(ContainerBuilder $container, mixed $bundles): void;
   }
   ```

   `$bundles` est passé aussi à `load()` : certaines features (les
   extensions Twig au pattern service-nullable) enregistrent un
   service DIFFÉREMMENT selon les bundles présents — le pattern
   v0.1.4 « toujours enregistré sous TwigBundle, arguments null sans
   storage » vit à l'intérieur du loader, pas dans l'extension.

2. **Un loader par feature optionnelle**, nommé `<Feature>Loader`,
   dans `<Package>\DependencyInjection\Loader\`. Le loader porte les
   commentaires « pourquoi » de sa feature (historique des frictions
   inclus) — ils déménagent avec le code, ils ne sont pas résumés.

3. **L'extension devient une table des matières** : ses gates
   transverses (le C1 EasyAdmin pour le bridge, la config
   `auto_register_routes`) puis :

   ```php
   foreach ($this->featureLoaders() as $loader) {
       if ($loader->supports($bundles)) {
           $loader->load($container, $bundles);
       }
   }
   ```

   La liste `featureLoaders()` est déclarée en dur dans l'extension —
   pas de découverte par tag : le câblage DI doit rester lisible par
   lecture séquentielle (même argument que « no global registries »
   du README).

4. **`supports()` porte le gate ENTIER de la feature** (bundles +
   `class_exists`/`interface_exists`), `load()` ne re-teste rien —
   sauf les variations internes du pattern service-nullable, qui
   sont du wiring, pas du gating.

5. **Aucun changement de comportement.** Mêmes services, mêmes
   alias, mêmes tags, mêmes paramètres, mêmes conditions. Les tests
   conteneur existants (unit DI + functional Container + TestKernel
   d'intégration) sont le harnais de la migration et ne changent pas.

## Conséquences

- Ajouter une feature optionnelle = ajouter une classe + une ligne
  dans `featureLoaders()` ; le diff de revue est local à la feature.
- Les `prepend()` restent dans les extensions (config d'autres
  bundles, ordre sensible, volume faible).
- `bulk-async` / `search` / les adapters gardent leur extension
  monolithique : une seule feature chacun, le découpage n'aurait
  pas d'objet (règle de trois, comme ADR-0031).
