<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\EasyAdminFilterBridge\Export\Exporter;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generic export endpoint — given a resource FQCN + a format
 * (`csv` or `xlsx`), iterates rows of the entity via Doctrine and
 * streams the result.
 *
 * Filter-awareness (since v0.5.0): the controller reads the URL's
 * `?filters[...]` slice (the same one EA's index page uses) and
 * applies it via {@see UrlFilterApplier}. Supported shapes: scalar
 * equality, expanded `{value, comparison}` with `=`/`!=`/`<`/`<=`/
 * `>`/`>=`/`between`/`like`, and list-style multi-select. Hosts who
 * need richer filter support (relation joins, FullTextSearch,
 * NotNull) wire a custom EA Action on their CrudController and call
 * the {@see Exporter} service directly with EA's filter-aware
 * QueryBuilder — see `docs/user/easyadmin-filter-bridge/filter-aware-export.md`.
 *
 * Security: hosts MUST gate the route behind their EA firewall /
 * voters — the controller does NOT do its own access checks (it
 * trusts the host's security configuration to deny anonymous /
 * unauthorised requests upstream).
 *
 * @since 0.3.0 (unfiltered), 0.5.0 (filter-aware via UrlFilterApplier)
 */
final class ExportController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Exporter $exporter,
        private readonly UrlFilterApplier $filterApplier,
    ) {
    }

    #[Route(
        path: '/admin/polysource/export/{resource}.{format}',
        name: 'polysource_export',
        methods: ['GET'],
        requirements: [
            // The resource regex MUST NOT include `.` because the URL
            // pattern uses `{resource}.{format}` with the dot as
            // separator. Including `.` makes the matcher greedy: it
            // consumes `Item.csv` as a single resource segment and
            // leaves nothing for `{format}`, yielding 404. Surfaced
            // 2026-05-14 by dogfooding round 2 against a fresh
            // `composer require polysource/easyadmin-filter-bridge:^0.5`.
            'resource' => '[A-Za-z0-9_\\\\:-]+',
            'format' => 'csv|xlsx',
        ],
    )]
    public function export(Request $request, string $resource, string $format): StreamedResponse
    {
        $entityClass = $this->resolveEntityClass($resource);

        $metadata = $this->em->getClassMetadata($entityClass);
        $fieldNames = array_values($metadata->getFieldNames());
        $headers = $fieldNames;

        // Stream rows lazily via Doctrine's iterate-style API. Keeps
        // memory bounded even on 100k-row tables; the writer flushes
        // on every row.
        $rows = $this->iterateEntities($entityClass, $fieldNames, $request);

        $filename = \sprintf(
            '%s-%s.%s',
            $metadata->getReflectionClass()->getShortName(),
            date('Y-m-d'),
            $format,
        );

        try {
            return match ($format) {
                'csv' => $this->exporter->streamCsv($rows, $headers, $filename),
                'xlsx' => $this->exporter->streamXlsx($rows, $headers, $filename),
                default => throw new BadRequestHttpException(\sprintf('Unsupported export format "%s".', $format)),
            };
        } catch (RuntimeException $e) {
            // openspout not installed — surface a clean error
            // instead of a fatal in the streaming callback.
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }

    /**
     * @param class-string $entityClass
     * @param list<string> $fieldNames
     *
     * @return iterable<array<string, mixed>>
     */
    private function iterateEntities(string $entityClass, array $fieldNames, Request $request): iterable
    {
        // Select each scalar field individually rather than the entity
        // alias (`select('e')`). Doctrine ORM 2.x has a known
        // limitation: `toIterable()` + `HYDRATE_ARRAY` + full-entity
        // select silently yields ZERO rows — the iterator exits
        // immediately because the array hydrator can't stream
        // entity objects, only flat scalar rows. ORM 3.x relaxed
        // this, but we support 2.x. Surfaced 2026-05-14 dogfooding
        // round 3 (friction C7): export endpoint produced a CSV
        // with only the header row regardless of how many entities
        // matched.
        $select = implode(', ', array_map(static fn (string $f): string => 'e.' . $f, $fieldNames));
        $qb = $this->em->createQueryBuilder()
            ->select($select)
            ->from($entityClass, 'e')
        ;

        // Apply the `?filters[...]` URL slice (since v0.5.0). Only
        // properties mapped by Doctrine are honoured; unknown ones
        // are silently dropped by UrlFilterApplier — no DQL injection
        // risk.
        $this->filterApplier->apply(
            $qb,
            $request->query->all(),
            $this->em->getClassMetadata($entityClass),
            'e',
        );

        // With scalar field selects, ARRAY hydration yields a flat
        // associative row per record where keys = field names —
        // exactly what the CSV writer expects. Streaming-safe under
        // ORM 2.x AND 3.x.
        $query = $qb->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        foreach ($query->toIterable() as $row) {
            if (!\is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $out */
            $out = [];
            foreach ($fieldNames as $field) {
                $value = \array_key_exists($field, $row) ? $row[$field] : null;
                $out[$field] = $value;
            }

            yield $out;
        }
    }

    /**
     * @return class-string
     */
    private function resolveEntityClass(string $resource): string
    {
        // Symfony's routing requirements pattern `[A-Za-z0-9_\\\\:.-]+`
        // accepts either a `\\`-escaped FQCN as it would appear in
        // PHP source (`App\\Entity\\Order`) or a colon-encoded form
        // (`App:Order`, EA's short alias style). For v0.3.0 we
        // support the FQCN form only — colon aliases are EA's
        // internal identifier convention and require resolving via
        // EA's CrudUrlGenerator which we don't want to depend on.
        $entityClass = str_replace('\\\\', '\\', $resource);

        if (!class_exists($entityClass)) {
            throw new NotFoundHttpException(\sprintf('Unknown entity class "%s".', $entityClass));
        }

        // Same inverted-boolean trap as MatchingCountController had
        // (friction C5, 2026-05-14): the old code combined
        // `!hasMetadataFor && !isTransient` which threw on valid
        // mapped entities whose metadata wasn't yet loaded in the
        // current request. `isTransient()` alone is the authoritative
        // "not mapped" signal regardless of metadata-cache state.
        if ($this->em->getMetadataFactory()->isTransient($entityClass)) {
            // Doctrine has no mapping for it — refuse rather than crash
            // on getClassMetadata().
            throw new NotFoundHttpException(\sprintf('Entity "%s" is not mapped by Doctrine.', $entityClass));
        }

        return $entityClass;
    }
}
