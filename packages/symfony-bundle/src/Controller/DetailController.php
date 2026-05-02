<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Security\PermissionAttributes;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Exception\ResourceNotFoundException;
use Polysource\Core\Field\FieldDto;
use Polysource\Core\Permission\PermissionInterface;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Front controller for `GET /{prefix}/{resourceName}/{id}` (single record detail).
 */
final readonly class DetailController
{
    public function __construct(
        private PermissionInterface $permission,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->assertResourceAccess($context->resource);

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
                'inline_actions' => $this->collectInlineActions($context->resource),
            ],
        );
    }

    private function assertResourceAccess(ResourceInterface $resource): void
    {
        $attribute = $resource->getPermission() ?? PermissionAttributes::RESOURCE_VIEW;
        if (!$this->permission->isGranted($attribute, $resource)) {
            throw new AccessDeniedHttpException(\sprintf('Access denied on resource "%s" (attribute %s).', $resource->getName(), $attribute));
        }
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
    private function collectInlineActions(ResourceInterface $resource): array
    {
        $views = [];
        foreach ($resource->configureActions() as $action) {
            if (!$action instanceof InlineActionInterface) {
                continue;
            }
            $attribute = $action->getPermission() ?? PermissionAttributes::ACTION_INVOKE;
            if (!$this->permission->isGranted($attribute)) {
                continue;
            }
            $views[] = [
                'name' => $action->getName(),
                'label' => $action->getLabel(),
                'icon' => $action->getIcon(),
            ];
        }

        return $views;
    }
}
