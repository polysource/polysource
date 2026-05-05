<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Storage;

use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;

/**
 * In-memory storage for tests + adopters who only need ephemeral
 * persistence (e.g. console scripts that build views on the fly).
 *
 * Not registered as a service by default — hosts wire it explicitly
 * via services.yaml when they want it.
 *
 * @since 0.1.0
 */
final class InMemorySavedViewStorage implements SavedViewStorageInterface
{
    /** @var array<string, SavedView> */
    private array $byId = [];

    public function save(SavedView $view): void
    {
        $this->byId[$view->id] = $view;
    }

    public function find(string $id): ?SavedView
    {
        return $this->byId[$id] ?? null;
    }

    public function listVisible(string $resourceName, string $ownerId, ?string $teamId = null): iterable
    {
        $matches = [];
        foreach ($this->byId as $view) {
            if ($view->resourceName !== $resourceName) {
                continue;
            }
            if (!$this->matchesScope($view, $ownerId, $teamId)) {
                continue;
            }
            $matches[] = $view;
        }

        return $matches;
    }

    public function delete(string $id): void
    {
        unset($this->byId[$id]);
    }

    private function matchesScope(SavedView $view, string $ownerId, ?string $teamId): bool
    {
        return match ($view->scope) {
            SavedViewScope::PRIVATE => $view->ownerId === $ownerId,
            SavedViewScope::TEAM => null !== $teamId && $view->teamId === $teamId,
            SavedViewScope::PUBLIC => true,
        };
    }
}
