<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Story\AuditEntriesStory;
use App\Story\BulkJobsStory;
use App\Story\CatalogStory;
use App\Story\CustomersStory;
use App\Story\DefaultUsersStory;
use App\Story\LoginAttemptsStory;
use App\Story\OrdersStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Single fixtures entry point. Stories own the actual data shape;
 * this class just decides their order so foreign keys resolve.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        DefaultUsersStory::load();
        CatalogStory::load();
        CustomersStory::load();
        OrdersStory::load();
        LoginAttemptsStory::load();
        AuditEntriesStory::load();
        BulkJobsStory::load();
    }
}
