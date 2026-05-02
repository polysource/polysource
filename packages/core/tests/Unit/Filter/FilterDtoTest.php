<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Filter\FilterDto;

#[CoversClass(FilterDto::class)]
final class FilterDtoTest extends TestCase
{
    #[Test]
    public function exposesItsConfigurationFromTheConstructor(): void
    {
        $dto = new FilterDto(
            property: 'status',
            label: 'Status',
            supportedOperators: ['eq', 'in'],
            template: '@App/filter/status.html.twig',
            customOptions: ['multiple' => true],
        );

        self::assertSame('status', $dto->property);
        self::assertSame('Status', $dto->label);
        self::assertSame(['eq', 'in'], $dto->supportedOperators);
        self::assertSame('@App/filter/status.html.twig', $dto->template);
        self::assertSame(['multiple' => true], $dto->customOptions);
    }
}
