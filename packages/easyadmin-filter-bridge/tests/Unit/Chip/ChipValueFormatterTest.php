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
class StubProduct {}

/**
 * @internal
 */
class StubCategory {}

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
                    return 'C'.$this->id;
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
     * Builds a FilterInterface implementation indexed by `(string)`
     * to its property name — `FilterConfigDto::addFilter()` keys
     * by `(string) $filter`, which `FilterTrait` aliases to
     * `getProperty()`.
     */
    private function makeFilterStub(string $fqcn, string $property = 'unused'): FilterInterface
    {
        $dto = new FilterDto();
        $dto->setFqcn($fqcn);
        $dto->setProperty($property);

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
     * @param array<string, FilterInterface> $filtersMap
     *
     * @return AdminContext<object>
     */
    private function makeContext(array $filtersMap, string $entityFqcn = 'App\\Anything'): AdminContext
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
