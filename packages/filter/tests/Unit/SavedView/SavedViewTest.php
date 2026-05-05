<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;

#[CoversClass(SavedView::class)]
final class SavedViewTest extends TestCase
{
    #[Test]
    public function constructsWithMinimalArgsAndExposesProperties(): void
    {
        $view = $this->makeView();

        self::assertSame('view-1', $view->id);
        self::assertSame('My products', $view->name);
        self::assertSame('products', $view->resourceName);
        self::assertSame('alice', $view->ownerId);
        self::assertSame(SavedViewScope::PRIVATE, $view->scope);
        self::assertCount(1, $view->filters);
        self::assertSame([], $view->columns);
        self::assertSame([], $view->sort);
        self::assertNull($view->pageSize);
        self::assertNull($view->teamId);
        self::assertFalse($view->isDefault);
        self::assertNull($view->roleAsDefault);
    }

    #[Test]
    public function rejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeView(id: '');
    }

    #[Test]
    public function rejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeView(name: '');
    }

    #[Test]
    public function rejectsEmptyResourceName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeView(resourceName: '');
    }

    #[Test]
    public function rejectsEmptyOwnerId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeView(ownerId: '');
    }

    #[Test]
    public function teamScopeRequiresTeamId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SavedView with scope TEAM requires a non-empty teamId.');

        $this->makeView(scope: SavedViewScope::TEAM);
    }

    #[Test]
    public function nonTeamScopeRejectsTeamId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeView(scope: SavedViewScope::PRIVATE, teamId: 'team-1');
    }

    #[Test]
    public function isDefaultRequiresRoleAsDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('isDefault requires a non-empty roleAsDefault');

        $this->makeView(isDefault: true);
    }

    #[Test]
    public function roleAsDefaultWithoutIsDefaultIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeView(isDefault: false, roleAsDefault: 'ROLE_USER');
    }

    #[Test]
    public function rejectsInvalidSortDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sort direction must be "asc" or "desc"');

        $this->makeView(sort: ['name' => 'descending']);
    }

    #[Test]
    public function rejectsEmptySortColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeView(sort: ['' => 'asc']);
    }

    #[Test]
    public function rejectsNonPositivePageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeView(pageSize: 0);
    }

    #[Test]
    public function rejectsEmptyColumnEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeView(columns: ['name', '']);
    }

    #[Test]
    public function privateViewIsVisibleToOwnerOnly(): void
    {
        $view = $this->makeView();

        self::assertTrue($view->isVisibleTo('alice'));
        self::assertFalse($view->isVisibleTo('bob'));
        self::assertFalse($view->isVisibleTo('bob', 'team-1'));
    }

    #[Test]
    public function teamViewIsVisibleToSameTeamMembers(): void
    {
        $view = $this->makeView(scope: SavedViewScope::TEAM, teamId: 'team-acme');

        self::assertTrue($view->isVisibleTo('alice', 'team-acme'));
        self::assertTrue($view->isVisibleTo('bob', 'team-acme'));
        self::assertFalse($view->isVisibleTo('bob', 'team-other'));
        self::assertFalse($view->isVisibleTo('bob'));
    }

    #[Test]
    public function publicViewIsVisibleToEveryone(): void
    {
        $view = $this->makeView(scope: SavedViewScope::PUBLIC);

        self::assertTrue($view->isVisibleTo('alice'));
        self::assertTrue($view->isVisibleTo('bob'));
        self::assertTrue($view->isVisibleTo('charlie', 'random-team'));
    }

    /**
     * @param list<string>           $columns
     * @param array<string, string>  $sort
     */
    private function makeView(
        string $id = 'view-1',
        string $name = 'My products',
        string $resourceName = 'products',
        string $ownerId = 'alice',
        SavedViewScope $scope = SavedViewScope::PRIVATE,
        array $columns = [],
        array $sort = [],
        ?int $pageSize = null,
        ?string $teamId = null,
        bool $isDefault = false,
        ?string $roleAsDefault = null,
    ): SavedView {
        return new SavedView(
            id: $id,
            name: $name,
            resourceName: $resourceName,
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            columns: $columns,
            sort: $sort,
            pageSize: $pageSize,
            teamId: $teamId,
            isDefault: $isDefault,
            roleAsDefault: $roleAsDefault,
        );
    }
}
