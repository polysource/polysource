<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\RowDetail\RowDetail;
use Polysource\EasyAdminFilterBridge\Controller\RowDetailController;
use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A provider returning `RowDetail::listing()` on a bridge-alone host
 * (no polysource/symfony-bundle → no embedded-listing renderer) is a
 * wiring mistake — the controller must say so explicitly instead of
 * rendering nothing or crashing on a null template.
 */
final class RowDetailControllerListingTest extends TestCase
{
    #[Test]
    public function listingDetailWithoutBundleRendererIsExplicitLogicException(): void
    {
        $provider = new class extends AbstractRowDetailProvider {
            public function getSupportedEntity(): string
            {
                return stdClass::class;
            }

            public function getRowDetail(object $entity): RowDetail
            {
                return RowDetail::listing('order-items', ['orderId' => 1]);
            }

            protected function template(): string
            {
                return 'unused.html.twig';
            }
        };

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('isTransient')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('find')->willReturn(new stdClass());

        $controller = new RowDetailController(
            new RowDetailRegistry([$provider]),
            $em,
            new Environment(new ArrayLoader([])),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/requires polysource\/symfony-bundle/');

        $controller->show(Request::create('/admin/polysource/row-detail/stdClass/1?fragment=1'), 'stdClass', '1');
    }
}
