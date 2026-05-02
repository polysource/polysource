<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Security\PermissionAttributes;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Field\FieldDto;
use Polysource\Core\Permission\PermissionInterface;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Front controller for `GET /{prefix}/{resourceName}` (the resource index page).
 *
 * Returns a {@see PolysourceView} consumed by the response listener.
 */
final readonly class IndexController
{
    public function __construct(
        private PermissionInterface $permission,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->assertResourceAccess($context->resource);

        $page = $context->resource->getDataSource()->search($context->query);

        return new PolysourceView(
            template: '@Polysource/index.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'page' => $page,
                'fields' => self::collectFields($context, 'index'),
                'inline_actions' => $this->collectInlineActions($context->resource),
                'bulk_actions' => $this->collectBulkActions($context->resource),
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
            if (!$this->isActionAllowed($action->getPermission())) {
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

    /**
     * @return list<array{name: string, label: string, icon: ?string}>
     */
    private function collectBulkActions(ResourceInterface $resource): array
    {
        $views = [];
        foreach ($resource->configureActions() as $action) {
            if (!$action instanceof BulkActionInterface) {
                continue;
            }
            if (!$this->isActionAllowed($action->getPermission())) {
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

    private function isActionAllowed(?string $attribute): bool
    {
        if (null === $attribute) {
            return $this->permission->isGranted(PermissionAttributes::ACTION_INVOKE);
        }

        return $this->permission->isGranted($attribute);
    }
}
