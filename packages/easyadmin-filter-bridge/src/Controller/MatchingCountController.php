<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generic dry-run endpoint for bulk actions — returns the count of
 * rows matching the current `?filters[...]` URL slice + the first
 * N samples.
 *
 * Pays the technical debt left by v0.4.0's `polysource_bulk_dry_run_url`
 * helper (which shipped the URL builder but no count logic). Hosts
 * wire this endpoint URL as the destination of the dry-run helper
 * (or override per resource for richer/business-aware previews).
 *
 * ### Response shape
 *
 *     {
 *         "count": 1234,
 *         "samples": [
 *             { "...mapped fields..." },
 *             ...
 *         ]
 *     }
 *
 * `samples` defaults to the first 5 matching rows (configurable via
 * `?samples=N`, capped at 50 to avoid abuse).
 *
 * Same security contract as ExportController: hosts MUST gate the
 * route behind their EA firewall / voters.
 *
 * Same filter-coverage limitations as the v0.5.0 ExportController
 * (mapped scalar properties only — no relation joins, no
 * FullTextSearch / NotNull custom filters; hosts who need those
 * wire a custom action).
 *
 * @since 0.5.0
 */
final class MatchingCountController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlFilterApplier $filterApplier,
    ) {
    }

    #[Route(
        path: '/admin/polysource/matching-count/{resource}',
        name: 'polysource_matching_count',
        methods: ['GET'],
        requirements: ['resource' => '[A-Za-z0-9_\\\\:.-]+'],
    )]
    public function count(Request $request, string $resource): JsonResponse
    {
        $entityClass = $this->resolveEntityClass($resource);

        $sampleLimit = max(0, min(50, (int) $request->query->get('samples', '5')));

        $count = $this->buildCountQuery($entityClass, $request)->getSingleScalarResult();
        $samples = $sampleLimit > 0
            ? $this->buildSamplesQuery($entityClass, $request, $sampleLimit)
            : [];

        return new JsonResponse([
            'count' => (int) $count,
            'samples' => $samples,
        ]);
    }

    /**
     * @param class-string $entityClass
     */
    private function buildCountQuery(string $entityClass, Request $request): \Doctrine\ORM\Query
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($entityClass, 'e')
        ;
        $this->filterApplier->apply(
            $qb,
            $request->query->all(),
            $this->em->getClassMetadata($entityClass),
            'e',
        );

        return $qb->getQuery();
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<array<string, mixed>>
     */
    private function buildSamplesQuery(string $entityClass, Request $request, int $limit): array
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e')
            ->setMaxResults($limit)
        ;
        $this->filterApplier->apply(
            $qb,
            $request->query->all(),
            $metadata,
            'e',
        );

        $query = $qb->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $fieldNames = array_values($metadata->getFieldNames());
        $out = [];
        foreach ($query->toIterable() as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $entry = [];
            foreach ($fieldNames as $field) {
                $entry[$field] = \array_key_exists($field, $row) ? $row[$field] : null;
            }
            $out[] = $entry;
        }

        return $out;
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

        if (!$this->em->getMetadataFactory()->hasMetadataFor($entityClass)
            && !$this->em->getMetadataFactory()->isTransient($entityClass)
        ) {
            throw new NotFoundHttpException(\sprintf('Entity "%s" is not mapped by Doctrine.', $entityClass));
        }

        return $entityClass;
    }
}
