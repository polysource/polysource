<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;
use Polysource\Filter\Pipeline\FilterMapperInterface;
use Polysource\Filter\Pipeline\FilterRendererInterface;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use RuntimeException;

/**
 * Contract for the 3 pipeline registries: each indexes its services
 * by filter `name` via the `supports()` predicate, exposes O(1)
 * lookup via `forName()`, and surfaces the known names list.
 */
final class RegistryTest extends TestCase
{
    public function testMapperRegistryIndexesBySupportsPredicate(): void
    {
        $textMapper = $this->makeMapper('text');
        $dateMapper = $this->makeMapper('datetime');

        $registry = new MapperRegistry([$textMapper, $dateMapper], ['text', 'datetime']);

        self::assertSame($textMapper, $registry->forName('text'));
        self::assertSame($dateMapper, $registry->forName('datetime'));
        self::assertTrue($registry->has('text'));
        self::assertFalse($registry->has('unknown'));
        self::assertSame(['text', 'datetime'], $registry->getKnownNames());
    }

    public function testMapperRegistryThrowsWhenNoServiceSupportsName(): void
    {
        $registry = new MapperRegistry([$this->makeMapper('text')], ['text']);

        $this->expectException(RuntimeException::class);
        $registry->forName('does_not_exist');
    }

    public function testFirstSupportingMapperWinsWhenMultipleMatch(): void
    {
        // First mapper claims to support 'text'; second one too. The
        // first one registered should win — host overrides via
        // service decoration / priority, not via registry choice.
        $first = $this->makeMapper('text', label: 'first');
        $second = $this->makeMapper('text', label: 'second');

        $registry = new MapperRegistry([$first, $second], ['text']);

        self::assertSame($first, $registry->forName('text'));
    }

    public function testFormatterRegistryWorksTheSameWay(): void
    {
        $textFormatter = new class implements FilterFormatterInterface {
            public function supports(string $name): bool
            {
                return 'text' === $name;
            }

            public function format(FilterCriterion $criterion): string
            {
                return 'formatted';
            }
        };

        $registry = new FormatterRegistry([$textFormatter], ['text', 'numeric']);

        self::assertSame($textFormatter, $registry->forName('text'));
        self::assertFalse($registry->has('numeric'));
        self::assertSame(['text'], $registry->getKnownNames());
    }

    public function testRendererRegistryWorksTheSameWay(): void
    {
        $renderer = new class implements FilterRendererInterface {
            public function supports(string $name): bool
            {
                return 'choice' === $name;
            }

            public function getFormType(): string
            {
                return 'Symfony\\Component\\Form\\Extension\\Core\\Type\\ChoiceType';
            }
        };

        $registry = new RendererRegistry([$renderer], ['choice']);

        self::assertSame($renderer, $registry->forName('choice'));
        self::assertSame(['choice'], $registry->getKnownNames());
    }

    private function makeMapper(string $supportedName, string $label = ''): FilterMapperInterface
    {
        return new class($supportedName, $label) implements FilterMapperInterface {
            public function __construct(private readonly string $supportedName, public readonly string $label)
            {
            }

            public function supports(string $name): bool
            {
                return $this->supportedName === $name;
            }

            public function fromRequest(string $property, array $rawValues): FilterCriterion
            {
                return new FilterCriterion($property, '=', []);
            }

            public function toFormData(FilterCriterion $criterion): array
            {
                return [];
            }
        };
    }
}
