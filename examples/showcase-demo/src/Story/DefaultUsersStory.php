<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

/**
 * Seeds the 3 demo accounts referenced in the README + 5 additional
 * ops users so the audit log has authors variety.
 *
 * Password is "shopco" for every seeded user.
 */
final class DefaultUsersStory extends Story
{
    public function build(): void
    {
        UserFactory::createOne([
            'email' => 'admin@shop.co',
            'firstName' => 'Alice',
            'lastName' => 'Anderson',
            'roles' => ['ROLE_ADMIN'],
        ]);

        UserFactory::createOne([
            'email' => 'ops@shop.co',
            'firstName' => 'Olivier',
            'lastName' => 'Operator',
            'roles' => ['ROLE_OPS'],
        ]);

        UserFactory::createOne([
            'email' => 'viewer@shop.co',
            'firstName' => 'Vera',
            'lastName' => 'Viewer',
            'roles' => ['ROLE_VIEWER'],
        ]);

        // Five extra ops users to give the audit log a realistic
        // multi-author distribution.
        UserFactory::createMany(5, ['roles' => ['ROLE_OPS']]);
    }
}
