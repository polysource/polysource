<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Chip;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use ReflectionClass;
use ReflectionProperty;
use Stringable;
use Symfony\Component\Translation\Translator;

/**
 * Behavioural tests for {@see ChipValueFormatter}.
 *
 * Covers:
 * - no AdminContext / no Crud → defensive stringify
 * - Filter not found for property → defensive stringify
 * - BooleanFilter: 1/'1'/true → Yes (translated), 0/'0'/false → No, null/'' → Empty
 * - EntityFilter: PK → entity __toString via Doctrine
 * - EntityFilter list → comma-joined __toStrings
 * - Other filter types → stringify
 *
 * The Translator + EntityManager are real instances with minimal
 * stubs so the tests exercise the formatter end-to-end without
 * mocking the entire boundary.
 */
/**
 * Test stub entities — real classes so `class_exists()` checks
 * inside ChipValueFormatter pass.
 *
 * @internal
 */
class StubProduct
{
}

/**
 * @internal
 */
class StubCategory
{
}

final class ChipValueFormatterTest extends TestCase
{
    public function testNoContextFallsBackToStringify(): void
    {
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn(null);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('42', $formatter->format('any', 42));
        self::assertSame('a, b', $formatter->format('any', ['a', 'b']));
        self::assertSame('', $formatter->format('any', null));
    }

    public function testFilterNotFoundFallsBackToStringify(): void
    {
        $context = $this->makeContext(filtersMap: []);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('foo', $formatter->format('unknown', 'foo'));
    }

    public function testBooleanFilterYesNoEmpty(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
        $translator->addResource('array', [
            'label.true' => 'Yes',
            'label.false' => 'No',
            'label.null' => 'Empty',
        ], 'en', 'EasyAdminBundle');

        $context = $this->makeContext(filtersMap: [
            'isActive' => $this->makeFilterStub(BooleanFilter::class),
        ]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            $translator,
        );

        self::assertSame('Yes', $formatter->format('isActive', '1'));
        self::assertSame('Yes', $formatter->format('isActive', 1));
        self::assertSame('Yes', $formatter->format('isActive', true));
        self::assertSame('No', $formatter->format('isActive', '0'));
        self::assertSame('No', $formatter->format('isActive', 0));
        self::assertSame('No', $formatter->format('isActive', false));
        self::assertSame('Empty', $formatter->format('isActive', ''));
        self::assertSame('Empty', $formatter->format('isActive', null));
    }

    public function testNonBooleanNonEntityFallsBackToStringify(): void
    {
        $context = $this->makeContext(filtersMap: [
            'price' => $this->makeFilterStub(NumericFilter::class),
        ]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('50', $formatter->format('price', '50'));
    }

    public function testEntityFilterResolvesPkToString(): void
    {
        $entity = new class implements Stringable {
            public function __toString(): string
            {
                return 'Books';
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(StubProduct::class);
        $metadata->mapManyToOne([
            'fieldName' => 'category',
            'targetEntity' => StubCategory::class,
        ]);
        $em->method('getClassMetadata')->with(StubProduct::class)->willReturn($metadata);
        $em->method('find')->with(StubCategory::class, '2')->willReturn($entity);

        $context = $this->makeContext(
            filtersMap: ['category' => $this->makeFilterStub(EntityFilter::class)],
            entityFqcn: StubProduct::class,
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter($provider, $em, new Translator('en'));

        self::assertSame('Books', $formatter->format('category', '2'));
    }

    public function testEntityFilterListJoinsToStrings(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(StubProduct::class);
        $metadata->mapManyToOne([
            'fieldName' => 'category',
            'targetEntity' => StubCategory::class,
        ]);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('find')->willReturnCallback(static function (string $cls, mixed $id): Stringable {
            $idStr = \is_scalar($id) ? (string) $id : '?';

            return new class($idStr) implements Stringable {
                public function __construct(private string $id)
                {
                }

                public function __toString(): string
                {
                    return 'C' . $this->id;
                }
            };
        });

        $context = $this->makeContext(
            filtersMap: ['category' => $this->makeFilterStub(EntityFilter::class)],
            entityFqcn: StubProduct::class,
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter($provider, $em, new Translator('en'));

        self::assertSame('C1, C2', $formatter->format('category', ['1', '2']));
    }

    /**
     * Stage 1 — Filter customOption(CHIP_FORMATTER) wins over
     * everything else. Even if the FormType says boolean, the
     * host's callable takes precedence.
     */
    public function testStage1FilterChipFormatterCallableWins(): void
    {
        $filter = $this->makeFilterStub(BooleanFilter::class, 'isVisible');
        $filter->getAsDto()->setCustomOption(
            \Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions::CHIP_FORMATTER,
            static fn (mixed $v): string => 'CUSTOM-' . (\is_scalar($v) ? (string) $v : 'null'),
        );

        $context = $this->makeContext(filtersMap: ['isVisible' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        // Stage 1 fires before stage 3's boolean translation.
        self::assertSame('CUSTOM-1', $formatter->format('isVisible', '1'));
        self::assertSame('CUSTOM-0', $formatter->format('isVisible', '0'));
    }

    /**
     * Stage 2 — Field customOption(CHIP_FORMATTER) wins over
     * stages 3-5. Demonstrates table↔chip coherence: the host
     * declares ONE callable on the field and both layers consume it.
     */
    public function testStage2FieldChipFormatterCallableWinsOverFormTypeMatch(): void
    {
        $filter = $this->makeFilterStub(BooleanFilter::class, 'isVisible');

        // Build a Field stub with a chipFormatter custom option.
        $fieldDto = (new ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class))
            ->newInstanceWithoutConstructor();
        // FieldDto's constructor initialises the customOptions
        // KeyValueStore — without it, getCustomOption() segfaults.
        // Reflection-poke the property.
        $rp = new ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class, 'customOptions');
        $rp->setValue($fieldDto, \EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore::new());
        $rp = new ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class, 'propertyName');
        $rp->setValue($fieldDto, 'isVisible');
        $fieldDto->setCustomOption(
            \Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions::CHIP_FORMATTER,
            static fn (mixed $v): string => 'FIELD-' . (\is_scalar($v) ? (string) $v : 'null'),
        );

        $context = $this->makeContext(
            filtersMap: ['isVisible' => $filter],
            fields: [$fieldDto],
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        // Stage 2 (Field) fires before Stage 3 (BooleanFilterType match).
        self::assertSame('FIELD-1', $formatter->format('isVisible', '1'));
    }

    /**
     * Stage 1 (interface variant) — A {@see ChipFormatterInterface}
     * service registered as the filter's chip formatter is invoked
     * just like a callable. Demonstrates DI-based formatters per
     * ADR-016: hosts can `chipFormatter($this->myService)` instead
     * of inline closures, getting Translator/EM/etc. via the
     * service constructor.
     */
    public function testStage1FilterChipFormatterInterfaceWins(): void
    {
        $filter = $this->makeFilterStub(BooleanFilter::class, 'isVisible');
        $service = new class implements \Polysource\Filter\Bridge\Contract\ChipFormatterInterface {
            public function format(mixed $rawValue): string
            {
                return 'IFACE-' . (\is_scalar($rawValue) ? (string) $rawValue : 'null');
            }
        };
        $filter->getAsDto()->setCustomOption(
            \Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions::CHIP_FORMATTER,
            $service,
        );

        $context = $this->makeContext(filtersMap: ['isVisible' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('IFACE-1', $formatter->format('isVisible', '1'));
    }

    /**
     * Stage 2 (interface variant) — Same DI shape on the field side.
     * Confirms `lookupFieldChipFormatter()` returns the service to
     * the dispatcher, and the dispatcher routes via the
     * ChipFormatterInterface branch.
     */
    public function testStage2FieldChipFormatterInterfaceWins(): void
    {
        $filter = $this->makeFilterStub(BooleanFilter::class, 'isVisible');

        $fieldDto = (new ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class))
            ->newInstanceWithoutConstructor();
        $rp = new ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class, 'customOptions');
        $rp->setValue($fieldDto, \EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore::new());
        $rp = new ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto::class, 'propertyName');
        $rp->setValue($fieldDto, 'isVisible');

        $service = new class implements \Polysource\Filter\Bridge\Contract\ChipFormatterInterface {
            public function format(mixed $rawValue): string
            {
                return 'FIELD-IFACE-' . (\is_scalar($rawValue) ? (string) $rawValue : 'null');
            }
        };
        $fieldDto->setCustomOption(
            \Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions::CHIP_FORMATTER,
            $service,
        );

        $context = $this->makeContext(
            filtersMap: ['isVisible' => $filter],
            fields: [$fieldDto],
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('FIELD-IFACE-1', $formatter->format('isVisible', '1'));
    }

    /**
     * Stage 3 — Custom FilterInterface using BooleanFilterType
     * (the user's `FreewheelCreativeIsSentFilter` case). The
     * filter's FQCN doesn't match BooleanFilter::class but its
     * formType DOES, so the boolean translation kicks in.
     */
    public function testStage3CustomFilterUsingBooleanFilterTypeGetsBooleanResolution(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
        $translator->addResource('array', [
            'label.true' => 'Envoyé',
            'label.false' => 'Non envoyé',
        ], 'en', 'EasyAdminBundle');

        $filter = $this->makeFilterStub(
            'Backend\\Controller\\Admin\\Filter\\FreewheelCreativeIsSentFilter',
            'isSent',
            \EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType::class,
        );

        $context = $this->makeContext(filtersMap: ['isSent' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter($provider, $this->createMock(EntityManagerInterface::class), $translator);

        self::assertSame('Envoyé', $formatter->format('isSent', '1'));
        self::assertSame('Non envoyé', $formatter->format('isSent', '0'));
    }

    /**
     * Stage 4 — Auto-detect Doctrine association on the property
     * even when the filter's FQCN/formType say nothing about
     * being an entity filter. Covers the user's
     * `AssociationByIdFilter` case.
     */
    public function testStage4DoctrineAssociationAutoDetectionResolvesEntity(): void
    {
        $entity = new class implements Stringable {
            public function __toString(): string
            {
                return 'Order #42';
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(StubProduct::class);
        $metadata->mapManyToOne([
            'fieldName' => 'macroOrder',
            'targetEntity' => StubCategory::class,  // any class for the test
        ]);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('find')->willReturn($entity);

        $filter = $this->makeFilterStub(
            'Backend\\Controller\\Admin\\Filter\\AssociationByIdFilter',
            'macroOrder',
        );

        $context = $this->makeContext(
            filtersMap: ['macroOrder' => $filter],
            entityFqcn: StubProduct::class,
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter($provider, $em, new Translator('en'));

        self::assertSame('Order #42', $formatter->format('macroOrder', '42'));
    }

    public function testEntityFilterReturnsRawValueWhenAssociationMissing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(StubProduct::class);
        // no association mapped — `category` is unknown
        $em->method('getClassMetadata')->willReturn($metadata);

        $context = $this->makeContext(
            filtersMap: ['category' => $this->makeFilterStub(EntityFilter::class)],
            entityFqcn: StubProduct::class,
        );
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter($provider, $em, new Translator('en'));

        self::assertSame('99', $formatter->format('category', '99'));
    }

    /**
     * Builds a FilterInterface stub. Stage 3 of the chip chain
     * routes by `formType`, not by FQCN — when the FQCN matches
     * an EA built-in we map to its conventional formType
     * automatically so existing tests (which only pass FQCN)
     * keep working. Custom callers can pass `$formType`
     * explicitly.
     */
    private function makeFilterStub(string $fqcn, string $property = 'unused', ?string $formType = null): FilterInterface
    {
        $autoFormTypeMap = [
            BooleanFilter::class => \EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType::class,
            EntityFilter::class => \EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType::class,
            NumericFilter::class => \EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType::class,
        ];

        $dto = new FilterDto();
        $dto->setFqcn($fqcn);
        $dto->setProperty($property);
        $effectiveFormType = $formType ?? ($autoFormTypeMap[$fqcn] ?? null);
        if (null !== $effectiveFormType) {
            $dto->setFormType($effectiveFormType);
        }

        return new class($dto) implements FilterInterface {
            public function __construct(private readonly FilterDto $dto)
            {
            }

            public function apply(\Doctrine\ORM\QueryBuilder $qb, \EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto $d, ?\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto $f, EntityDto $e): void
            {
            }

            public function getAsDto(): FilterDto
            {
                return $this->dto;
            }

            public function __toString(): string
            {
                return $this->dto->getProperty();
            }
        };
    }

    /**
     * NotNullFilter tri-state resolution moved from the chips-bar
     * template into stage 3 (2026-08 host regression: the template
     * pre-resolved the label and shadowed stage-1/2 chipFormatters).
     */
    public function testNotNullFilterResolvesTriStateDefaultLabels(): void
    {
        $filter = $this->makeFilterStub(
            \Polysource\EasyAdminFilterBridge\Filter\NotNullFilter::class,
            'archivedAt',
            \Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType::class,
        );

        $context = $this->makeContext(filtersMap: ['archivedAt' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        // The default labels are translation keys resolved in the
        // bridge's domain — mirror the shipped en catalogue.
        $translator = new Translator('en');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
        $translator->addResource('array', [
            'polysource.filter.not_null.any' => 'Any',
            'polysource.filter.not_null.has_value' => 'Has value',
            'polysource.filter.not_null.empty' => 'Empty',
        ], 'en', 'PolysourceEasyAdminFilterBridge');

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            $translator,
        );

        self::assertSame('Has value', $formatter->format('archivedAt', 'not_null'));
        self::assertSame('Empty', $formatter->format('archivedAt', 'null'));
        self::assertSame('Any', $formatter->format('archivedAt', ''));
        self::assertSame('Any', $formatter->format('archivedAt', null));
    }

    public function testNotNullFilterHonoursCustomLabelsOption(): void
    {
        $filter = $this->makeFilterStub(
            \Polysource\EasyAdminFilterBridge\Filter\NotNullFilter::class,
            'deletedAt',
            \Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType::class,
        );
        $filter->getAsDto()->setFormTypeOption('labels', [
            'any' => 'All',
            'not_null' => 'Soft-deleted',
            'null' => 'Active',
        ]);

        $context = $this->makeContext(filtersMap: ['deletedAt' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        self::assertSame('Soft-deleted', $formatter->format('deletedAt', 'not_null'));
        self::assertSame('Active', $formatter->format('deletedAt', 'null'));
        self::assertSame('All', $formatter->format('deletedAt', ''));
    }

    public function testStage1ChipFormatterWinsOverNotNullLabels(): void
    {
        $filter = $this->makeFilterStub(
            \Polysource\EasyAdminFilterBridge\Filter\NotNullFilter::class,
            'parent',
            \Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType::class,
        );
        $filter->getAsDto()->setCustomOption(
            \Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions::CHIP_FORMATTER,
            static fn (mixed $v): string => 'not_null' === $v ? 'Avec parent' : 'Sans parent',
        );

        $context = $this->makeContext(filtersMap: ['parent' => $filter]);
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $formatter = new ChipValueFormatter(
            $provider,
            $this->createMock(EntityManagerInterface::class),
            new Translator('en'),
        );

        // The host formatter must shadow the stage-3 labels resolution.
        self::assertSame('Avec parent', $formatter->format('parent', 'not_null'));
        self::assertSame('Sans parent', $formatter->format('parent', 'null'));
    }

    /**
     * @param array<string, FilterInterface>                                $filtersMap
     * @param list<\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto>|null       $fields  null → no FieldCollection on EntityDto
     *
     * @return AdminContext<object>
     */
    private function makeContext(array $filtersMap, string $entityFqcn = 'App\\Anything', ?array $fields = null): AdminContext
    {
        $filtersConfig = new FilterConfigDto();
        foreach ($filtersMap as $property => $filter) {
            // The filter stub already carries property; ensure it
            // matches the map key so addFilter() indexes correctly.
            $filter->getAsDto()->setProperty($property);
            $filtersConfig->addFilter($filter);
        }

        $crud = (new ReflectionClass(CrudDto::class))->newInstanceWithoutConstructor();
        $rpfc = new ReflectionProperty(CrudDto::class, 'filters');
        $rpfc->setValue($crud, $filtersConfig);

        $entity = (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
        $entityFqcnProp = new ReflectionProperty(EntityDto::class, 'fqcn');
        $entityFqcnProp->setValue($entity, $entityFqcn);

        if (null !== $fields) {
            // FieldCollection's constructor expects FieldInterface
            // implementations; we have raw FieldDtos. Reflection-poke
            // the internal array.
            $fieldCollection = (new ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection::class))
                ->newInstanceWithoutConstructor();
            $indexed = [];
            foreach ($fields as $i => $fieldDto) {
                $indexed['stub_' . $i] = $fieldDto;
            }
            $fieldsArr = new ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection::class, 'fields');
            $fieldsArr->setValue($fieldCollection, $indexed);

            $fieldsProp = new ReflectionProperty(EntityDto::class, 'fields');
            $fieldsProp->setValue($entity, $fieldCollection);
        }

        /** @var AdminContext<object> $ctx */
        $ctx = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
        $crudCtx = new \EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext(
            crudDto: $crud,
            entityDto: $entity,
            searchDto: null,
            adminControllers: $this->createMock(\EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface::class),
        );
        $rpc = new ReflectionProperty(AdminContext::class, 'crudContext');
        $rpc->setValue($ctx, $crudCtx);

        return $ctx;
    }
}
