<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Resource exposing two inline actions — one always visible, one
 * with `isDisplayed()` toggled at runtime via a static flag — so
 * the ActionIsDisplayedTest can flip the flag and assert the
 * action button appears/disappears in the rendered index.
 */
final class ActionDisplayedTestResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'item-one']),
        ]));
    }

    public function getName(): string
    {
        return 'gated-actions';
    }

    public function getLabel(): string
    {
        return 'Gated actions';
    }

    public function configureActions(): iterable
    {
        yield new AlwaysShownAction();
        yield new ConditionallyShownAction();
    }
}
