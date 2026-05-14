<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Storage;

use Polysource\Filter\ColumnPreference\Model\ColumnPreference;

/**
 * In-memory implementation for unit tests.
 */
final class InMemoryColumnPreferenceStorage implements ColumnPreferenceStorageInterface
{
    /** @var array<string, ColumnPreference> */
    private array $byKey = [];

    public function find(string $ownerId, string $resourceName): ?ColumnPreference
    {
        return $this->byKey[$this->key($ownerId, $resourceName)] ?? null;
    }

    public function save(ColumnPreference $preference): void
    {
        $this->byKey[$this->key($preference->ownerId, $preference->resourceName)] = $preference;
    }

    public function delete(string $ownerId, string $resourceName): void
    {
        unset($this->byKey[$this->key($ownerId, $resourceName)]);
    }

    private function key(string $ownerId, string $resourceName): string
    {
        return $ownerId . "\0" . $resourceName;
    }
}
