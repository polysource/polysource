<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Field\FieldDto;

/**
 * Front controller for `GET /{prefix}/{resourceName}` (the resource index page).
 *
 * Returns a {@see PolysourceView} consumed by the response listener.
 */
final class IndexController
{
    public function __invoke(AdminContext $context): PolysourceView
    {
        // TODO(Phase-6): enforce $context->resource->getPermission() via
        // PermissionInterface before reaching the data source.

        $page = $context->resource->getDataSource()->search($context->query);

        return new PolysourceView(
            template: '@Polysource/index.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'page' => $page,
                'fields' => self::collectFields($context, 'index'),
            ],
        );
    }

    /**
     * @return list<FieldDto>
     */
    private static function collectFields(AdminContext $context, string $page): array
    {
        $fields = [];
        foreach ($context->resource->configureFields($page) as $field) {
            $dto = $field->getAsDto();
            if ($dto->isOnPage($page)) {
                $fields[] = $dto;
            }
        }

        return $fields;
    }
}
