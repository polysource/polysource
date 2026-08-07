<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\RowDetail;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;
use stdClass;

final class AbstractRowDetailProviderTest extends TestCase
{
    #[Test]
    public function composesTemplateAndContextIntoTheValueObject(): void
    {
        $provider = new class extends AbstractRowDetailProvider {
            public function getSupportedEntity(): string
            {
                return stdClass::class;
            }

            protected function template(): string
            {
                return 'admin/_detail.html.twig';
            }

            protected function context(object $entity): array
            {
                return ['extra' => 42];
            }
        };

        $detail = $provider->getRowDetail(new stdClass());

        self::assertSame('admin/_detail.html.twig', $detail->template);
        self::assertSame(['extra' => 42], $detail->context);
        self::assertNull($provider->getPermission(), 'Default: no permission attribute');
    }
}
