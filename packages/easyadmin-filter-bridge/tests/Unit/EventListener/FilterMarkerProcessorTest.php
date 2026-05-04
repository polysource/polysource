<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use LogicException;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
use Polysource\EasyAdminFilterBridge\EventListener\FilterMarkerProcessor;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Behavioural tests for {@see FilterMarkerProcessor}.
 *
 * Sets up a real CrudDto with a populated FilterConfigDto, runs
 * the processor, and asserts on the rebuilt config: markers
 * removed, customOptions propagated correctly.
 */
final class FilterMarkerProcessorTest extends TestCase
{
    public function testNoOpWhenNoContext(): void
    {
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn(null);

        // Should not throw — just early-return.
        (new FilterMarkerProcessor($provider))->onKernelController($this->makeEvent());
        $this->expectNotToPerformAssertions();
    }

    public function testNoOpWhenNoMarkersPresent(): void
    {
        $original = new FilterConfigDto();
        $original->addFilter(TextFilter::new('name'));
        $original->addFilter(TextFilter::new('description'));

        $crud = $this->makeCrudWithFilters($original);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());

        // No rebuild — same config object.
        self::assertSame($original, $crud->getFiltersConfig());
    }

    public function testTabMarkerPropagatesToSubsequentFilters(): void
    {
        $config = new FilterConfigDto();
        $config->addFilter(Polysource::tab('Visibility'));
        $config->addFilter(TextFilter::new('isVisible'));
        $config->addFilter(TextFilter::new('isPublished'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());

        $rebuilt = $crud->getFiltersConfig();
        // Marker removed.
        self::assertCount(2, $rebuilt->all());

        $isVisible = $rebuilt->getFilter('isVisible');
        self::assertInstanceOf(FilterInterface::class, $isVisible);
        self::assertSame('Visibility', $isVisible->getAsDto()->getCustomOption(BridgeOptions::TAB));

        $isPublished = $rebuilt->getFilter('isPublished');
        self::assertInstanceOf(FilterInterface::class, $isPublished);
        self::assertSame('Visibility', $isPublished->getAsDto()->getCustomOption(BridgeOptions::TAB));
    }

    public function testGroupMarkerPropagatesAndTabResetsGroup(): void
    {
        $config = new FilterConfigDto();
        $config->addFilter(Polysource::tab('Visibility'));
        $config->addFilter(Polysource::group('Active state'));
        $config->addFilter(TextFilter::new('isActive'));        // tab + group inherited
        $config->addFilter(Polysource::tab('Dates'));            // tab change → group reset
        $config->addFilter(TextFilter::new('createdAt'));        // tab="Dates", no group

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());

        $rebuilt = $crud->getFiltersConfig();

        $isActive = $rebuilt->getFilter('isActive');
        self::assertInstanceOf(FilterInterface::class, $isActive);
        self::assertSame('Visibility', $isActive->getAsDto()->getCustomOption(BridgeOptions::TAB));
        self::assertSame('Active state', $isActive->getAsDto()->getCustomOption(BridgeOptions::GROUP));

        $createdAt = $rebuilt->getFilter('createdAt');
        self::assertInstanceOf(FilterInterface::class, $createdAt);
        self::assertSame('Dates', $createdAt->getAsDto()->getCustomOption(BridgeOptions::TAB));
        self::assertNull($createdAt->getAsDto()->getCustomOption(BridgeOptions::GROUP));
    }

    public function testExplicitFilterTabOverridesInheritedMarkerTab(): void
    {
        $config = new FilterConfigDto();
        $config->addFilter(Polysource::tab('Visibility'));
        $explicit = TextFilter::new('explicit');
        $explicit->getAsDto()->setCustomOption(BridgeOptions::TAB, 'Override');
        $config->addFilter($explicit);
        $config->addFilter(TextFilter::new('inheritor'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());

        $rebuilt = $crud->getFiltersConfig();

        $explicitOut = $rebuilt->getFilter('explicit');
        self::assertInstanceOf(FilterInterface::class, $explicitOut);
        self::assertSame('Override', $explicitOut->getAsDto()->getCustomOption(BridgeOptions::TAB));

        $inheritorOut = $rebuilt->getFilter('inheritor');
        self::assertInstanceOf(FilterInterface::class, $inheritorOut);
        self::assertSame('Visibility', $inheritorOut->getAsDto()->getCustomOption(BridgeOptions::TAB));
    }

    public function testIdempotentRunDoesntChangeAnything(): void
    {
        $config = new FilterConfigDto();
        $config->addFilter(Polysource::tab('Visibility'));
        $config->addFilter(TextFilter::new('isActive'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());
        $afterFirst = $crud->getFiltersConfig();

        $processor->onKernelController($this->makeEvent());
        $afterSecond = $crud->getFiltersConfig();

        // Second run is no-op (no markers left).
        self::assertSame($afterFirst, $afterSecond);
    }

    public function testThrowsWhenTabsUsedButSomeFiltersOrphan(): void
    {
        // Two filters BEFORE the first tab marker → orphans
        // (no tab inherited). Once a tab marker fires, strict mode
        // demands every filter end up under a tab.
        $config = new FilterConfigDto();
        $config->addFilter(TextFilter::new('orphanA'));
        $config->addFilter(TextFilter::new('orphanB'));
        $config->addFilter(Polysource::tab('Visibility'));
        $config->addFilter(TextFilter::new('isVisible'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"orphanA", "orphanB"');

        $processor->onKernelController($this->makeEvent());
    }

    public function testNoExceptionWhenNoTabsUsed(): void
    {
        // No tab markers → flat layout, no strict-mode constraint.
        $config = new FilterConfigDto();
        $config->addFilter(TextFilter::new('a'));
        $config->addFilter(Polysource::group('Misc'));
        $config->addFilter(TextFilter::new('b'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());
        $this->expectNotToPerformAssertions();
    }

    public function testMarkersAreNoLongerInTheRebuiltConfig(): void
    {
        $config = new FilterConfigDto();
        $tabMarker = Polysource::tab('Visibility');
        $groupMarker = Polysource::group('Active state');
        $config->addFilter($tabMarker);
        $config->addFilter($groupMarker);
        $config->addFilter(TextFilter::new('isActive'));

        $crud = $this->makeCrudWithFilters($config);
        $processor = new FilterMarkerProcessor($this->makeProvider($crud));

        $processor->onKernelController($this->makeEvent());

        $rebuilt = $crud->getFiltersConfig();
        self::assertCount(1, $rebuilt->all());
        // Marker properties (synthetic UUIDs) are gone.
        self::assertNull($rebuilt->getFilter($tabMarker->getAsDto()->getProperty()));
        self::assertNull($rebuilt->getFilter($groupMarker->getAsDto()->getProperty()));
    }

    private function makeCrudWithFilters(FilterConfigDto $config): CrudDto
    {
        $crud = (new ReflectionClass(CrudDto::class))->newInstanceWithoutConstructor();
        $rp = new ReflectionProperty(CrudDto::class, 'filters');
        $rp->setValue($crud, $config);

        return $crud;
    }

    private function makeProvider(CrudDto $crud): AdminContextProviderInterface
    {
        $entity = (new ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto::class))->newInstanceWithoutConstructor();
        $crudCtx = new CrudContext(
            crudDto: $crud,
            entityDto: $entity,
            searchDto: null,
            adminControllers: $this->createMock(AdminControllerRegistryInterface::class),
        );

        $ctx = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
        $rpc = new ReflectionProperty(AdminContext::class, 'crudContext');
        $rpc->setValue($ctx, $crudCtx);

        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($ctx);

        return $provider;
    }

    private function makeEvent(): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            // PHP 8.1 doesn't support `: never` return type on arrow
            // functions; use a regular closure that throws instead.
            static function () {
                throw new LogicException('not invoked');
            },
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
