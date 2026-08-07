<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_row_detail_available(fqcn, entity)`
 * — whether the row-detail chevron should render for one row:
 * a provider is registered for the entity class AND the provider's
 * permission attribute (if any) is granted with the entity as voter
 * subject.
 *
 * This is a RENDER gate only; the backend endpoint re-checks the
 * permission authoritatively (never trust the frontend). When the
 * provider declares an attribute and no authorization checker is
 * wired (kernel without SecurityBundle), the gate fails closed.
 *
 * @since 1.1.0
 */
final class RowDetailExtension extends AbstractExtension
{
    public function __construct(
        private readonly RowDetailRegistry $registry,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_row_detail_available', $this->isAvailable(...)),
        ];
    }

    public function isAvailable(string $entityFqcn, ?object $entity): bool
    {
        if (null === $entity) {
            return false;
        }

        $provider = $this->registry->providerFor($entityFqcn);
        if (null === $provider) {
            return false;
        }

        $permission = $provider->getPermission();
        if (null === $permission) {
            return true;
        }

        return null !== $this->authorizationChecker
            && $this->authorizationChecker->isGranted($permission, $entity);
    }
}
