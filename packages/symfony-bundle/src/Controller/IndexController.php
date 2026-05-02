<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Field\FieldDto;
use Polysource\Core\Resource\ResourceInterface;

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
                'inline_actions' => self::collectInlineActions($context->resource),
                'bulk_actions' => self::collectBulkActions($context->resource),
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

    /**
     * @return list<array{name: string, label: string, icon: ?string}>
     */
    private static function collectInlineActions(ResourceInterface $resource): array
    {
        $views = [];
        foreach ($resource->configureActions() as $action) {
            if ($action instanceof InlineActionInterface) {
                $views[] = [
                    'name' => $action->getName(),
                    'label' => $action->getLabel(),
                    'icon' => $action->getIcon(),
                ];
            }
        }

        return $views;
    }

    /**
     * @return list<array{name: string, label: string, icon: ?string}>
     */
    private static function collectBulkActions(ResourceInterface $resource): array
    {
        $views = [];
        foreach ($resource->configureActions() as $action) {
            if ($action instanceof BulkActionInterface) {
                $views[] = [
                    'name' => $action->getName(),
                    'label' => $action->getLabel(),
                    'icon' => $action->getIcon(),
                ];
            }
        }

        return $views;
    }
}
