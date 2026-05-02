<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Security\PermissionAttributes;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Field\FieldDto;
use Polysource\Core\Permission\PermissionInterface;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shared logic for the three Polysource controllers — kept in one place
 * so a security or permission patch lands once instead of three times.
 *
 * Pure functions (modulo the {@see PermissionInterface} dependency).
 * Controllers receive this as a service rather than reimplementing the
 * helpers as static methods.
 */
final readonly class ControllerSupport
{
    public function __construct(
        private PermissionInterface $permission,
    ) {
    }

    /**
     * Throw {@see AccessDeniedHttpException} when the current user
     * cannot view the resource.
     */
    public function assertResourceAccess(ResourceInterface $resource): void
    {
        $attribute = $resource->getPermission() ?? PermissionAttributes::RESOURCE_VIEW;
        if (!$this->permission->isGranted($attribute, $resource)) {
            throw new AccessDeniedHttpException(\sprintf('Access denied on resource "%s" (attribute %s).', $resource->getName(), $attribute));
        }
    }

    /**
     * Throw {@see AccessDeniedHttpException} when the current user
     * cannot invoke the action.
     */
    public function assertActionAccess(ActionInterface $action): void
    {
        if (!$this->isActionAllowed($action)) {
            $attribute = $action->getPermission() ?? PermissionAttributes::ACTION_INVOKE;
            throw new AccessDeniedHttpException(\sprintf('Access denied on action "%s" (attribute %s).', $action->getName(), $attribute));
        }
    }

    /**
     * Whether the current user is allowed to invoke the action
     * (used to decide whether to render its button in the index).
     */
    public function isActionAllowed(ActionInterface $action): bool
    {
        $attribute = $action->getPermission() ?? PermissionAttributes::ACTION_INVOKE;

        return $this->permission->isGranted($attribute);
    }

    /**
     * @return list<FieldDto>
     */
    public function collectFields(ResourceInterface $resource, string $page): array
    {
        $fields = [];
        foreach ($resource->configureFields($page) as $field) {
            $dto = $field->getAsDto();
            if ($dto->isOnPage($page)) {
                $fields[] = $dto;
            }
        }

        return $fields;
    }

    /**
     * Single-pass partition of `configureActions()` into inline + bulk
     * lists, filtered by permission. Avoids the double-iteration bug
     * when a Resource yields a Generator from `configureActions()`.
     *
     * @return array{inline: list<array{name: string, label: string, icon: ?string}>, bulk: list<array{name: string, label: string, icon: ?string}>}
     */
    public function collectActionViews(ResourceInterface $resource): array
    {
        $inline = [];
        $bulk = [];
        foreach ($resource->configureActions() as $action) {
            if (!$this->isActionAllowed($action)) {
                continue;
            }
            $view = [
                'name' => $action->getName(),
                'label' => $action->getLabel(),
                'icon' => $action->getIcon(),
            ];
            if ($action instanceof InlineActionInterface) {
                $inline[] = $view;
            }
            if ($action instanceof BulkActionInterface) {
                $bulk[] = $view;
            }
        }

        return ['inline' => $inline, 'bulk' => $bulk];
    }
}
