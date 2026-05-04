<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\DependencyInjection\Compiler\PipelineCompilerPass;
use Polysource\Filter\DependencyInjection\PolysourceFilterExtension;
use Polysource\Filter\Pipeline\Default\NumericMapper;
use Polysource\Filter\Pipeline\Default\TextMapper;
use Polysource\Filter\Pipeline\FilterMapperInterface;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies the DI seam:
 * 1. PolysourceFilterExtension auto-configures FilterMapperInterface
 *    services with the `polysource.filter.mapper` tag.
 * 2. PipelineCompilerPass collects the names declared by tagged
 *    services (via tag attribute or NAME class constant) and patches
 *    the registries' constructors.
 * 3. MapperRegistry/FormatterRegistry/RendererRegistry are publicly
 *    accessible and indexed by the names found at compile time.
 */
final class PipelineCompilerPassTest extends TestCase
{
    public function testDefaultTextMapperIsAutoTaggedAndIndexedByName(): void
    {
        $container = $this->buildContainer([
            TextMapper::class => TextMapper::class,
            NumericMapper::class => NumericMapper::class,
        ]);

        // After compilation, MapperRegistry's known names must contain
        // the NAME constants of the registered services.
        $registry = $container->get(MapperRegistry::class);
        \assert($registry instanceof MapperRegistry);

        self::assertContains('text', $registry->getKnownNames());
        self::assertContains('numeric', $registry->getKnownNames());
        self::assertInstanceOf(TextMapper::class, $registry->forName('text'));
        self::assertInstanceOf(NumericMapper::class, $registry->forName('numeric'));
    }

    public function testExplicitTagNameAttributeTakesPrecedenceOverClassConstant(): void
    {
        $container = $this->makeBaseContainer();
        // Custom mapper class (no NAME constant) — declares its name via tag attribute.
        $container
            ->register('custom_mapper', CustomMapperWithoutConstant::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->addTag('polysource.filter.mapper', ['name' => 'custom-name'])
        ;
        $container->compile();

        $registry = $container->get(MapperRegistry::class);
        \assert($registry instanceof MapperRegistry);
        self::assertContains('custom-name', $registry->getKnownNames());
    }

    public function testThreeRegistriesArePublic(): void
    {
        $container = $this->buildContainer([]);

        self::assertInstanceOf(MapperRegistry::class, $container->get(MapperRegistry::class));
        self::assertInstanceOf(FormatterRegistry::class, $container->get(FormatterRegistry::class));
        self::assertInstanceOf(RendererRegistry::class, $container->get(RendererRegistry::class));
    }

    /**
     * @param array<string, class-string> $serviceMap [serviceId => class]
     */
    private function buildContainer(array $serviceMap): ContainerBuilder
    {
        $container = $this->makeBaseContainer();
        foreach ($serviceMap as $serviceId => $class) {
            $container
                ->register($serviceId, $class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setPublic(true)
            ;
        }
        $container->compile();

        return $container;
    }

    private function makeBaseContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Stub the upstream Symfony services that FilterService autowires.
        $container->register(\Symfony\Component\HttpFoundation\RequestStack::class)->setPublic(true);

        (new PolysourceFilterExtension())->load([], $container);
        $container->addCompilerPass(new PipelineCompilerPass());

        return $container;
    }
}

/**
 * Tiny mapper without the NAME constant — used to verify the tag
 * attribute is the primary source of truth for the filter name.
 */
final class CustomMapperWithoutConstant implements FilterMapperInterface
{
    public function supports(string $name): bool
    {
        return 'custom-name' === $name;
    }

    public function fromRequest(string $property, array $rawValues): \Polysource\Filter\Model\FilterCriterion
    {
        return new \Polysource\Filter\Model\FilterCriterion($property, '=', []);
    }

    public function toFormData(\Polysource\Filter\Model\FilterCriterion $criterion): array
    {
        return [];
    }
}
