<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\PolysourceFilter;
use ReflectionClass;
use stdClass;

/**
 * Behavioural tests for {@see PolysourceFilter}.
 *
 * Verifies:
 * - fluent setters return $this
 * - each setter writes the right BridgeOptions key on the wrapped FilterDto
 * - apply() forwards to the wrapped filter
 * - getAsDto() returns the wrapped filter's DTO
 * - renderer() also updates FilterDto::formType (declarative + runtime sync)
 */
final class PolysourceFilterTest extends TestCase
{
    public function testTabWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $result = $proxy->tab('Visibility');

        self::assertSame($proxy, $result);
        self::assertSame('Visibility', $filter->getAsDto()->getCustomOption(BridgeOptions::TAB));
    }

    public function testGroupWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $result = $proxy->group('Active state');

        self::assertSame($proxy, $result);
        self::assertSame('Active state', $filter->getAsDto()->getCustomOption(BridgeOptions::GROUP));
    }

    public function testChipFormatterWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $callable = static fn (mixed $v): string => 'X';

        PolysourceFilter::on($filter)->chipFormatter($callable);

        self::assertSame($callable, $filter->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER));
    }

    public function testRendererSyncsFormTypeAndCustomOption(): void
    {
        $filter = TextFilter::new('name');

        PolysourceFilter::on($filter)->renderer(stdClass::class);

        self::assertSame(stdClass::class, $filter->getAsDto()->getCustomOption(BridgeOptions::RENDERER));
        self::assertSame(stdClass::class, $filter->getAsDto()->getFormType());
    }

    public function testFluentChainAccumulatesAllOptions(): void
    {
        $filter = TextFilter::new('name');
        $cb = static fn (mixed $v): string => 'X';

        PolysourceFilter::on($filter)
            ->tab('T')
            ->group('G')
            ->chipFormatter($cb)
            ->meta('host.custom', 42);

        $dto = $filter->getAsDto();
        self::assertSame('T', $dto->getCustomOption(BridgeOptions::TAB));
        self::assertSame('G', $dto->getCustomOption(BridgeOptions::GROUP));
        self::assertSame($cb, $dto->getCustomOption(BridgeOptions::CHIP_FORMATTER));
        self::assertSame(42, $dto->getCustomOption('host.custom'));
    }

    public function testApplyForwardsToWrappedFilter(): void
    {
        $sentinel = new stdClass();
        $sentinel->invoked = false;
        $inner = new class($sentinel) implements \EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface {
            public function __construct(private stdClass $sentinel)
            {
            }

            public function apply(QueryBuilder $qb, FilterDataDto $d, ?\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto $f, EntityDto $e): void
            {
                $this->sentinel->invoked = true;
            }

            public function getAsDto(): FilterDto
            {
                return new FilterDto();
            }

            public function __toString(): string
            {
                return 'inner';
            }
        };

        PolysourceFilter::on($inner)->apply(
            $this->createMock(QueryBuilder::class),
            $this->makeFilterData(),
            null,
            (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor(),
        );

        self::assertTrue($sentinel->invoked);
    }

    public function testToStringReturnsWrappedFilterToString(): void
    {
        self::assertSame('name', (string) PolysourceFilter::on(TextFilter::new('name')));
    }

    // ──────────────────────────────────────────────────────────────
    // EA FilterTrait fluent-setter proxies.
    //
    // The decorator must transparently expose EA's own filter
    // configuration surface so hosts can mix bridge-specific
    // setters (tab/group/chipFormatter) with EA-native ones
    // (setFormTypeOption, setLabel, etc.) in a single fluent chain
    // — the documented pattern in `whats-new.md`. Before these
    // proxies existed, the chain crashed with
    // "Attempted to call an undefined method named setFormTypeOption
    //  of class PolysourceFilter".
    // ──────────────────────────────────────────────────────────────

    public function testSetFormTypeOptionProxiesToDto(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $result = $proxy->setFormTypeOption('placeholder', 'Search…');

        self::assertSame($proxy, $result);
        self::assertSame('Search…', $filter->getAsDto()->getFormTypeOption('placeholder'));
    }

    public function testSetFormTypeOptionIfNotSetSkipsExistingKey(): void
    {
        $filter = TextFilter::new('name');
        $filter->setFormTypeOption('placeholder', 'Original');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setFormTypeOptionIfNotSet('placeholder', 'Override');

        self::assertSame('Original', $filter->getAsDto()->getFormTypeOption('placeholder'));
    }

    public function testSetFormTypeOptionIfNotSetWritesNewKey(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setFormTypeOptionIfNotSet('placeholder', 'Default');

        self::assertSame('Default', $filter->getAsDto()->getFormTypeOption('placeholder'));
    }

    public function testSetFormTypeOptionsMergesIntoExistingOptions(): void
    {
        // Mirrors EA's own contract: FilterDto::setFormTypeOptions
        // delegates to KeyValueStore::setAll, which iterates and
        // calls set() per key — a merge, not a wholesale replace.
        // Hosts relying on this proxy keep that behaviour.
        $filter = TextFilter::new('name');
        $filter->setFormTypeOption('placeholder', 'Existing');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setFormTypeOptions(['help' => 'Help text', 'required' => false]);

        $options = $filter->getAsDto()->getFormTypeOptions();
        self::assertSame('Help text', $options['help']);
        self::assertFalse($options['required']);
        self::assertSame(
            'Existing',
            $options['placeholder'],
            'setFormTypeOptions must merge with existing options, matching EA contract.'
        );
    }

    public function testSetFormTypeOnProxyWritesDtoFormTypeWithoutCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setFormType(stdClass::class);

        self::assertSame(stdClass::class, $filter->getAsDto()->getFormType());
        // Distinguishing setFormType (plain proxy) from renderer()
        // (also writes BridgeOptions::RENDERER): only renderer()
        // sets the customOption.
        self::assertNull($filter->getAsDto()->getCustomOption(BridgeOptions::RENDERER));
    }

    public function testSetLabelProxiesToDto(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setLabel('Visible label');

        self::assertSame('Visible label', $filter->getAsDto()->getLabel());
    }

    public function testSetPropertyProxiesToDto(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $proxy->setProperty('renamed');

        self::assertSame('renamed', $filter->getAsDto()->getProperty());
    }

    /**
     * The flagship `whats-new.md` example — a single fluent chain
     * mixing EA setters and bridge setters. Pre-fix, this crashed
     * on the first EA setter encountered after `Polysource::filter()`.
     */
    public function testDocsFlagshipChainCompilesAndAppliesAllOptions(): void
    {
        $filter = TextFilter::new('status');

        PolysourceFilter::on($filter)
            ->tab('Lifecycle')
            ->group('Status')
            ->setFormTypeOption('choices', ['Draft' => 'draft', 'Published' => 'published'])
            ->setLabel('Status (multi)');

        $dto = $filter->getAsDto();
        self::assertSame('Lifecycle', $dto->getCustomOption(BridgeOptions::TAB));
        self::assertSame('Status', $dto->getCustomOption(BridgeOptions::GROUP));
        self::assertSame(
            ['Draft' => 'draft', 'Published' => 'published'],
            $dto->getFormTypeOption('choices'),
        );
        self::assertSame('Status (multi)', $dto->getLabel());
    }

    private function makeFilterData(): FilterDataDto
    {
        $dto = new FilterDto();
        $dto->setProperty('p');

        return FilterDataDto::new(0, $dto, 'e', ['comparison' => '=', 'value' => null]);
    }
}
