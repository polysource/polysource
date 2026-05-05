<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Storage;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;

/**
 * Doctrine ORM implementation of {@see SavedViewStorageInterface}.
 *
 * Per ADR-019 §4 this ships as the v0.1 default storage when the host
 * has Doctrine installed. The DI extension only registers the service
 * if `class_exists(EntityManagerInterface::class)` is true, so hosts
 * without Doctrine never instantiate this class.
 *
 * Hosts MUST run a migration to create the `polysource_saved_views`
 * table (cf. {@see SavedViewRecord} + the SQL snippet in
 * `docs/user/filter/saved-views.md`). Polysource does not auto-migrate.
 *
 * @since 0.1.0
 */
final class DoctrineSavedViewStorage implements SavedViewStorageInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SavedView $view): void
    {
        $record = $this->entityManager->find(SavedViewRecord::class, $view->id);
        if (null === $record) {
            $record = new SavedViewRecord();
            $record->id = $view->id;
        }

        $this->hydrateRecord($record, $view);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    public function find(string $id): ?SavedView
    {
        $record = $this->entityManager->find(SavedViewRecord::class, $id);

        return null === $record ? null : $this->toView($record);
    }

    public function listVisible(string $resourceName, string $ownerId, ?string $teamId = null): iterable
    {
        // Storage-level pre-filter: owner's PRIVATE views, plus PUBLIC,
        // plus TEAM views matching the user's teamId. The voter remains
        // the authoritative gate (cf. interface PHPDoc).
        $qb = $this->entityManager->createQueryBuilder()
            ->select('v')
            ->from(SavedViewRecord::class, 'v')
            ->where('v.resourceName = :resource')
            ->setParameter('resource', $resourceName)
        ;

        if (null !== $teamId) {
            $qb->andWhere(
                '(v.scope = :public)'
                . ' OR (v.scope = :private AND v.ownerId = :owner)'
                . ' OR (v.scope = :team AND v.teamId = :team_id)',
            );
            $qb->setParameter('public', SavedViewScope::PUBLIC->value);
            $qb->setParameter('private', SavedViewScope::PRIVATE->value);
            $qb->setParameter('owner', $ownerId);
            $qb->setParameter('team', SavedViewScope::TEAM->value);
            $qb->setParameter('team_id', $teamId);
        } else {
            $qb->andWhere(
                '(v.scope = :public)'
                . ' OR (v.scope = :private AND v.ownerId = :owner)',
            );
            $qb->setParameter('public', SavedViewScope::PUBLIC->value);
            $qb->setParameter('private', SavedViewScope::PRIVATE->value);
            $qb->setParameter('owner', $ownerId);
        }

        /** @var list<SavedViewRecord> $records */
        $records = $qb->getQuery()->getResult();

        $views = [];
        foreach ($records as $record) {
            $views[] = $this->toView($record);
        }

        return $views;
    }

    public function delete(string $id): void
    {
        $record = $this->entityManager->find(SavedViewRecord::class, $id);
        if (null === $record) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    private function hydrateRecord(SavedViewRecord $record, SavedView $view): void
    {
        $record->name = $view->name;
        $record->resourceName = $view->resourceName;
        $record->ownerId = $view->ownerId;
        $record->scope = $view->scope->value;
        $record->filtersJson = $this->encodeJson($this->serializeFilters($view->filters));
        $record->columnsJson = $this->encodeJson($view->columns);
        $record->sortJson = $this->encodeJson($view->sort);
        $record->pageSize = $view->pageSize;
        $record->teamId = $view->teamId;
        $record->isDefault = $view->isDefault;
        $record->roleAsDefault = $view->roleAsDefault;
    }

    private function toView(SavedViewRecord $record): SavedView
    {
        return new SavedView(
            id: $record->id,
            name: $record->name,
            resourceName: $record->resourceName,
            ownerId: $record->ownerId,
            scope: SavedViewScope::from($record->scope),
            filters: $this->deserializeFilters($record->resourceName, $record->filtersJson),
            columns: $this->decodeStringList($record->columnsJson),
            sort: $this->decodeSortMap($record->sortJson),
            pageSize: $record->pageSize,
            teamId: $record->teamId,
            isDefault: $record->isDefault,
            roleAsDefault: $record->roleAsDefault,
        );
    }

    /**
     * @return list<array{property: string, operator: string, values: list<mixed>}>
     */
    private function serializeFilters(FilterCollection $collection): array
    {
        $payload = [];
        foreach ($collection as $criterion) {
            $payload[] = [
                'property' => $criterion->property,
                'operator' => $criterion->operator,
                'values' => $criterion->values,
            ];
        }

        return $payload;
    }

    private function deserializeFilters(string $collectionId, string $json): FilterCollection
    {
        $raw = $this->decodeJson($json);
        if (!\is_array($raw)) {
            throw new InvalidArgumentException('Filters JSON must decode to a list.');
        }

        $criteria = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)
                || !isset($entry['property'], $entry['operator'], $entry['values'])
                || !\is_string($entry['property'])
                || !\is_string($entry['operator'])
            ) {
                throw new InvalidArgumentException('Malformed saved-view filters payload.');
            }
            $values = $entry['values'];
            if (!\is_array($values) || !array_is_list($values)) {
                throw new InvalidArgumentException('Saved-view filters values must be a list.');
            }
            $criteria[] = new FilterCriterion($entry['property'], $entry['operator'], $values);
        }

        return new FilterCollection($collectionId, $criteria);
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(string $json): array
    {
        $raw = $this->decodeJson($json);
        if (!\is_array($raw) || !array_is_list($raw)) {
            return [];
        }

        $list = [];
        foreach ($raw as $item) {
            if (\is_string($item) && '' !== $item) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @return array<string, string>
     */
    private function decodeSortMap(string $json): array
    {
        $raw = $this->decodeJson($json);
        if (!\is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $column => $direction) {
            if (\is_string($column) && '' !== $column
                && \in_array($direction, ['asc', 'desc'], true)
            ) {
                $map[$column] = $direction;
            }
        }

        return $map;
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function decodeJson(string $json): mixed
    {
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
