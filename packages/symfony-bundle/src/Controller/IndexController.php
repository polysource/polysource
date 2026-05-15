<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;

/**
 * Front controller for `GET /{prefix}/{resourceName}` (the resource index page).
 *
 * Returns a {@see PolysourceView} consumed by the response listener.
 */
final class IndexController
{
    public function __construct(
        private readonly ControllerSupport $support,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->support->assertResourceAccess($context->resource);

        $page = $context->resource->getDataSource()->search($context->query);
        $actions = $this->support->collectActionViews($context->resource);

        // Empty-fields fallback: when the Resource declared no fields,
        // synthesise them from the first record's properties so the UI
        // doesn't render rows-without-columns. Cf. ControllerSupport::synthesiseFieldsFromRecord.
        $fields = $this->support->collectFields($context->resource, 'index');
        if ([] === $fields) {
            $first = self::peekFirstRecord($page->items);
            if (null !== $first) {
                $fields = ControllerSupport::synthesiseFieldsFromRecord($first);
            }
        }

        return new PolysourceView(
            template: '@Polysource/index.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'page' => $page,
                'fields' => $fields,
                'inline_actions' => $actions['inline'],
                'bulk_actions' => $actions['bulk'],
            ],
        );
    }

    /**
     * @param iterable<\Polysource\Core\Query\DataRecord> $items
     */
    private static function peekFirstRecord(iterable $items): ?\Polysource\Core\Query\DataRecord
    {
        // Arrays + Countable iterables can be peeked without
        // consuming. For a one-shot Generator we DON'T peek — the
        // template foreach would then see zero rows. Hosts using
        // generators MUST declare fields explicitly.
        if (\is_array($items)) {
            return $items[array_key_first($items)] ?? null;
        }

        return null;
    }
}
