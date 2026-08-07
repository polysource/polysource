<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\EasyAdminFilterBridge\Http\SafeReferer;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Lazy row-detail endpoint: resolves the entity, gates it through
 * the provider's permission attribute (entity as voter subject —
 * this is the authoritative check, the chevron's render gate is
 * cosmetic), and renders the provider's template.
 *
 * Two rendering modes:
 *  - `?fragment=1` (what the Stimulus controller requests): bare
 *    HTML fragment, injected under the row;
 *  - without it (the chevron's no-JS `href`): the same fragment
 *    wrapped in a minimal standalone page with a same-host back
 *    link — the ADR-027 server-side baseline.
 *
 * Responses are `no-store`: detail content is row-fresh data and
 * per-user (permission-gated) — shared caches must never keep it.
 * Frontend "cache on first open" is an in-memory Stimulus concern,
 * not an HTTP one.
 *
 * @since 1.1.0
 */
final class RowDetailController
{
    public function __construct(
        private readonly RowDetailRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    #[Route(
        path: '/admin/polysource/row-detail/{resource}/{id}',
        name: 'polysource_row_detail',
        methods: ['GET'],
        requirements: ['resource' => '[A-Za-z0-9_\\\\:.-]+', 'id' => '[^/]+'],
    )]
    public function show(Request $request, string $resource, string $id): Response
    {
        $entityClass = $this->resolveEntityClass($resource);

        $provider = $this->registry->providerFor($entityClass);
        if (null === $provider) {
            throw new NotFoundHttpException(\sprintf('No row-detail provider registered for "%s".', $entityClass));
        }

        $entity = $this->em->find($entityClass, $id);
        if (null === $entity) {
            throw new NotFoundHttpException(\sprintf('Record "%s" not found for "%s".', $id, $entityClass));
        }

        $permission = $provider->getPermission();
        if (null !== $permission) {
            // Fail closed: a declared attribute with no security
            // layer wired must deny, not silently allow.
            if (null === $this->authorizationChecker || !$this->authorizationChecker->isGranted($permission, $entity)) {
                throw new AccessDeniedHttpException(\sprintf('Access denied on row detail for "%s" (attribute %s).', $entityClass, $permission));
            }
        }

        $detail = $provider->getRowDetail($entity);
        $html = $this->twig->render($detail->template, ['entity' => $entity] + $detail->context);

        if (!$request->query->getBoolean('fragment')) {
            $html = $this->twig->render(
                '@PolysourceEasyAdminFilterBridge/crud/row_detail_page.html.twig',
                [
                    'content' => $html,
                    'back_url' => SafeReferer::resolve($request, '/admin'),
                ],
            );
        }

        $response = new Response($html);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * @return class-string
     */
    private function resolveEntityClass(string $resource): string
    {
        $entityClass = str_replace('\\\\', '\\', $resource);

        if (!class_exists($entityClass)) {
            throw new NotFoundHttpException(\sprintf('Unknown entity class "%s".', $entityClass));
        }

        // Same isTransient() rationale as MatchingCountController —
        // authoritative regardless of metadata-cache warmth.
        if ($this->em->getMetadataFactory()->isTransient($entityClass)) {
            throw new NotFoundHttpException(\sprintf('Entity "%s" is not mapped by Doctrine.', $entityClass));
        }

        return $entityClass;
    }
}
