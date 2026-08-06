<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SavedView wiring (cf. ADR-019). The service stack requires
 * DoctrineBundle (storage) + SecurityBundle (voter); the Twig
 * extension follows the v0.1.4 nullable-service pattern: registered
 * UNCONDITIONALLY when TwigBundle is loaded so templates calling
 * `saved_views_dropdown()` still parse on bridge-alone installs
 * (the v0.1.1 install-time crash), with all-null arguments when the
 * storage isn't wired — the function then returns an empty string.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class SavedViewLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return FeatureGate::hasTwigBundle($bundles) || self::storageAvailable($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $hasStorage = self::storageAvailable($bundles);

        if ($hasStorage) {
            $container
                ->register(\Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\SavedView\Storage\SavedViewStorageInterface::class,
                \Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage::class,
            );

            $container
                ->register(\Polysource\Filter\SavedView\SavedViewService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            // SavedViewApplyService — shared core for the two listeners
            // (`SavedViewApplySubscriber` in EA bridge, `PolysourceSavedViewApplyListener`
            // in the standalone admin) that translate `?view=<id>` into
            // a filtered URL redirect. Extracted in v0.10.0 per audit
            // task #66 (MEDIUM DRY).
            $container
                ->register(\Polysource\Filter\SavedView\SavedViewApplyService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            $container
                ->register(\Polysource\Filter\SavedView\Security\SavedViewVoter::class)
                ->setAutowired(true)
                ->addTag('security.voter')
            ;

            // ClearSavedViewListener — consumes `?clear-view=1` to wipe
            // the session-remembered last-used view + redirect to a
            // clean URL. Without it the dropdown's "Clear current
            // view" item only strips the URL query, leaving the
            // session entry dangling so `defaultFor()` resurrects
            // the view as `current` on the next render.
            $container
                ->register(\Polysource\Filter\SavedView\EventListener\ClearSavedViewListener::class)
                ->setAutowired(true)
                ->addTag('kernel.event_subscriber')
            ;
        }

        // SavedViewExtension — Twig function `saved_views_dropdown()`.
        if (FeatureGate::hasTwigBundle($bundles)) {
            $extensionDef = $container
                ->register(\Polysource\Filter\SavedView\Twig\SavedViewExtension::class)
                ->addTag('twig.extension')
            ;
            if ($hasStorage) {
                // Autowire the full deps (SavedViewService, Twig
                // Environment, optional Router + TeamResolver).
                $extensionDef->setAutowired(true);
            } else {
                // Construct with all-null deps so the function is
                // registered but returns empty when called.
                // setAutowired stays false so missing services
                // don't fail DI compilation.
                // v0.9.0 PR 1: 5th arg is the optional CsrfTokenManager;
                // `polysource_csrf_token()` returns '' when null.
                $extensionDef->setArguments([null, null, null, null, null]);
            }
        }
    }

    private static function storageAvailable(mixed $bundles): bool
    {
        return interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::savedViewsAvailable($bundles);
    }
}
