<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Exception\ResourceNotFoundException;
use Polysource\Core\Field\FieldDto;

/**
 * Front controller for `GET /{prefix}/{resourceName}/{id}` (single record detail).
 */
final class DetailController
{
    public function __invoke(AdminContext $context): PolysourceView
    {
        // TODO(Phase-6): enforce $context->resource->getPermission() via
        // PermissionInterface before reaching the data source.

        if (null === $context->recordId) {
            throw new ResourceNotFoundException(\sprintf('Detail route for resource "%s" requires an "id" parameter.', $context->resource->getName()));
        }

        $record = $context->resource->getDataSource()->find($context->recordId);
        if (null === $record) {
            throw new ResourceNotFoundException(\sprintf('Record "%s" not found in resource "%s".', $context->recordId, $context->resource->getName()));
        }

        return new PolysourceView(
            template: '@Polysource/detail.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'record' => $record,
                'fields' => self::collectFields($context, 'detail'),
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
