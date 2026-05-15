<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Polysource\Audit\Filter\AuditLogFilter;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;

/**
 * Pin the 5 named factories the audit log resource uses (occurredAt,
 * actorId, resourceName, actionName, outcome) and the public DTO
 * mapping. AuditLogFilter is a plain declaration — the query
 * translation lives in `AuditLogDataSource`, so the test surface is
 * limited to: factory output, getters, applyToQuery passthrough,
 * getAsDto() shape.
 */
final class AuditLogFilterTest extends TestCase
{
    public function testOccurredAtFactoryProducesCorrectFilter(): void
    {
        $filter = AuditLogFilter::occurredAt();
        self::assertSame('occurredAt', $filter->getProperty());
        self::assertSame('Occurred at', $filter->getLabel());
        self::assertContains('between', $filter->getSupportedOperators());
    }

    public function testActorIdFactoryProducesCorrectFilter(): void
    {
        $filter = AuditLogFilter::actorId();
        self::assertSame('actorId', $filter->getProperty());
        self::assertSame('Actor', $filter->getLabel());
    }

    public function testResourceNameFactoryProducesCorrectFilter(): void
    {
        $filter = AuditLogFilter::resourceName();
        self::assertSame('resourceName', $filter->getProperty());
    }

    public function testActionNameFactoryProducesCorrectFilter(): void
    {
        $filter = AuditLogFilter::actionName();
        self::assertSame('actionName', $filter->getProperty());
    }

    public function testFactoryAcceptsCustomLabel(): void
    {
        $filter = AuditLogFilter::actorId('Operator');
        self::assertSame('Operator', $filter->getLabel());
    }

    public function testApplyToQueryAppendsCriterion(): void
    {
        $filter = AuditLogFilter::actorId();
        $query = new DataQuery('audit-log');
        $criterion = new FilterCriterion('actorId', FilterOperator::Eq, 'admin@shop.co');

        $applied = $filter->applyToQuery($query, $criterion);

        self::assertSame($criterion, $applied->filters['actorId']);
    }

    public function testGetAsDtoExposesPropertyAndLabel(): void
    {
        $filter = AuditLogFilter::occurredAt('When did it happen');
        $dto = $filter->getAsDto();

        self::assertSame('occurredAt', $dto->property);
        self::assertSame('When did it happen', $dto->label);
    }
}
