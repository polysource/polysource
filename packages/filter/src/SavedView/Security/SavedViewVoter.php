<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Security;

use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends Voter<string, SavedView>
 *
 * Symfony voter for saved view permissions.
 *
 * Default rules per ADR-019 §5:
 *   - VIEW   → owner | team-member | public
 *   - EDIT   → owner only
 *   - DELETE → owner only
 *   - SHARE  → owner only (changing scope across PRIVATE/TEAM/PUBLIC)
 *
 * Hosts that need broader policies (e.g. ROLE_ADMIN can edit any
 * view) compose by adding their own voter — Symfony aggregates votes
 * across all voters for the same attribute.
 *
 * @since 0.1.0
 */
final class SavedViewVoter extends Voter
{
    /** Read access to a saved view (list it in the dropdown, apply it). */
    public const VIEW = 'POLYSOURCE_VIEW_SAVED_VIEW';

    /** Update name / filters / columns / sort / pageSize. */
    public const EDIT = 'POLYSOURCE_EDIT_SAVED_VIEW';

    /** Delete the saved view. */
    public const DELETE = 'POLYSOURCE_DELETE_SAVED_VIEW';

    /** Change the visibility scope (private ↔ team ↔ public). */
    public const SHARE = 'POLYSOURCE_SHARE_SAVED_VIEW';

    /** @var list<string> */
    private const SUPPORTED = [
        self::VIEW,
        self::EDIT,
        self::DELETE,
        self::SHARE,
    ];

    public function __construct(
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof SavedView
            && \in_array($attribute, self::SUPPORTED, true);
    }

    /**
     * 4th parameter (`$vote`) is the optional `Vote` object Symfony 8
     * passes for richer messaging. Typed `mixed` so the override is
     * compatible across Sf 5.4 / 6.x / 7.x (3-param parent signature)
     * AND Sf 8 (4-param parent with `?Vote`). We don't consume it.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        \assert($subject instanceof SavedView);

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            // Anonymous users can VIEW public saved views; everything
            // else requires a real user.
            return self::VIEW === $attribute && SavedViewScope::PUBLIC === $subject->scope;
        }

        $userId = $user->getUserIdentifier();
        $isOwner = $subject->ownerId === $userId;

        return match ($attribute) {
            self::EDIT, self::DELETE, self::SHARE => $isOwner,
            self::VIEW => $isOwner || $this->isVisibleByScope($subject, $user),
            default => false, // Unreachable — supports() filters this out.
        };
    }

    private function isVisibleByScope(SavedView $view, UserInterface $user): bool
    {
        return match ($view->scope) {
            SavedViewScope::PRIVATE => false, // owner check already happened upstream
            SavedViewScope::PUBLIC => true,
            SavedViewScope::TEAM => $this->matchesTeam($view, $user),
        };
    }

    private function matchesTeam(SavedView $view, UserInterface $user): bool
    {
        if (null === $this->teamResolver) {
            return false;
        }

        $teamId = $this->teamResolver->teamIdFor($user);

        return null !== $teamId && $view->teamId === $teamId;
    }
}
