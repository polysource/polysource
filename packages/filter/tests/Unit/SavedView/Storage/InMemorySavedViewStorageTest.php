<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView\Storage;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;

#[CoversClass(InMemorySavedViewStorage::class)]
final class InMemorySavedViewStorageTest extends TestCase
{
    #[Test]
    public function findReturnsNullForUnknownId(): void
    {
        $storage = new InMemorySavedViewStorage();
        self::assertNull($storage->find('unknown'));
    }

    #[Test]
    public function saveAndFindRoundtrip(): void
    {
        $storage = new InMemorySavedViewStorage();
        $view = $this->makeView('view-1');
        $storage->save($view);

        $found = $storage->find('view-1');
        self::assertNotNull($found);
        self::assertSame('view-1', $found->id);
        self::assertSame('My products', $found->name);
    }

    #[Test]
    public function saveOverwritesExistingViewById(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('view-1', name: 'First'));
        $storage->save($this->makeView('view-1', name: 'Second'));

        $found = $storage->find('view-1');
        self::assertNotNull($found);
        self::assertSame('Second', $found->name);
    }

    #[Test]
    public function deleteRemovesTheView(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('view-1'));
        $storage->delete('view-1');

        self::assertNull($storage->find('view-1'));
    }

    #[Test]
    public function deleteOnUnknownIdIsNoOp(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->delete('never-existed');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function listVisibleScopesByResource(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', resourceName: 'products'));
        $storage->save($this->makeView('b', resourceName: 'orders'));

        $visible = iterator_to_array($this->toIterable(
            $storage->listVisible('products', 'alice'),
        ));
        self::assertCount(1, $visible);
        self::assertSame('a', $visible[0]->id);
    }

    #[Test]
    public function listVisibleAppliesPrivateScope(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));
        $storage->save($this->makeView('b', ownerId: 'bob'));

        $aliceSees = iterator_to_array($this->toIterable(
            $storage->listVisible('products', 'alice'),
        ));
        self::assertCount(1, $aliceSees);
        self::assertSame('a', $aliceSees[0]->id);
    }

    #[Test]
    public function listVisibleAppliesTeamScope(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView(
            'a',
            ownerId: 'alice',
            scope: SavedViewScope::TEAM,
            teamId: 'team-acme',
        ));
        $storage->save($this->makeView(
            'b',
            ownerId: 'bob',
            scope: SavedViewScope::TEAM,
            teamId: 'team-other',
        ));

        // Charlie in team-acme sees Alice's view, not Bob's.
        $charlieSees = iterator_to_array($this->toIterable(
            $storage->listVisible('products', 'charlie', 'team-acme'),
        ));
        self::assertCount(1, $charlieSees);
        self::assertSame('a', $charlieSees[0]->id);

        // No team → team-scoped views are hidden.
        $anonSees = iterator_to_array($this->toIterable(
            $storage->listVisible('products', 'charlie'),
        ));
        self::assertCount(0, $anonSees);
    }

    #[Test]
    public function listVisibleIncludesPublicViews(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice', scope: SavedViewScope::PUBLIC));

        $bobSees = iterator_to_array($this->toIterable(
            $storage->listVisible('products', 'bob'),
        ));
        self::assertCount(1, $bobSees);
        self::assertSame('a', $bobSees[0]->id);
    }

    private function makeView(
        string $id,
        string $name = 'My products',
        string $resourceName = 'products',
        string $ownerId = 'alice',
        SavedViewScope $scope = SavedViewScope::PRIVATE,
        ?string $teamId = null,
    ): SavedView {
        return new SavedView(
            id: $id,
            name: $name,
            resourceName: $resourceName,
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection($resourceName, [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            teamId: $teamId,
        );
    }

    /**
     * Bridges any iterable to a Generator so iterator_to_array() is
     * happy on PHP 8.1 (cf. ADR-015 / phpVersion=80100).
     *
     * @param iterable<SavedView> $iterable
     *
     * @return Generator<int, SavedView>
     */
    private function toIterable(iterable $iterable): Generator
    {
        $i = 0;
        foreach ($iterable as $view) {
            yield $i++ => $view;
        }
    }
}
