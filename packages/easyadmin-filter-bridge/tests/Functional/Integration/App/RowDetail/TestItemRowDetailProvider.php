<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\RowDetail;

use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;

/**
 * Row-detail fixture for {@see TestItem}: template + a permission
 * attribute so the endpoint's voter-subject gate is exercised
 * (cf. {@see TestItemRowDetailVoter} — denies archived items).
 */
final class TestItemRowDetailProvider extends AbstractRowDetailProvider
{
    public function getSupportedEntity(): string
    {
        return TestItem::class;
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_TEST_ROW_DETAIL';
    }

    protected function template(): string
    {
        return 'row_detail/test_item.html.twig';
    }

    protected function context(object $entity): array
    {
        return ['shouted_name' => strtoupper($entity instanceof TestItem ? $entity->name : '')];
    }
}
