<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * H4 of the manual test plan — saved views work on Polysource-native
 * routes (the `/admin/polysource/<resource>` family), via
 * `Polysource\Filter\EventListener\PolysourceSavedViewApplyListener`
 * (moved out of polysource/symfony-bundle in commit 30697ca).
 *
 * The Polysource-native URL shape uses `?filter[...]` (singular,
 * Polysource-decoded by `AdminContextResolver`), distinct from EA's
 * `?filters[...]` (plural). Pinning the round-trip catches:
 *   - Listener moved/renamed without updating DI registration.
 *   - Polysource AdminContextResolver no longer accepting the URL shape.
 *   - Saved view scoping by Polysource resource slug (vs EA entity FQCN).
 */
final class PolysourceSavedViewApplyTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $persistedIds = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->followRedirects(false);

        $repo = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $repo->findOneBy(['email' => 'admin@shop.co']);
        if (!$admin instanceof User) {
            self::markTestSkipped('admin@shop.co fixture missing');
        }
        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        if ([] !== $this->persistedIds) {
            /** @var EntityManagerInterface $em */
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->createQuery('DELETE FROM ' . SavedViewRecord::class . ' v WHERE v.id IN (:ids)')
                ->setParameter('ids', $this->persistedIds)
                ->execute();
        }
        parent::tearDown();
    }

    public function testApplyingASavedViewOnAuditLogRedirectsToFilterUrl(): void
    {
        // Audit log is a Polysource standalone resource — slug "audit-log",
        // NOT an EA entity FQCN. The view's resourceName must match
        // the slug or the listener silently bails.
        $viewId = $this->persistView(
            resourceName: 'audit-log',
            criteria: [['property' => 'outcome', 'operator' => 'eq', 'values' => ['success']]],
        );

        $this->client->request('GET', '/admin/polysource/audit-log?view=' . $viewId);

        self::assertResponseRedirects(
            null,
            302,
            'PolysourceSavedViewApplyListener must emit a 302 on view= param (regression of 30697ca move)',
        );

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringNotContainsString(
            'view=',
            $location,
            'redirect URL must drop the view= param',
        );

        // Polysource-native URL shape: ?filter[<prop>][op]=...&[value]=...
        // (singular `filter`, distinct from EA's plural `filters`).
        self::assertStringContainsString(
            'filter%5Boutcome%5D',
            $location,
            'redirect must encode Polysource-native filter URL shape (singular `filter`)',
        );
    }

    public function testApplyingAViewBoundToADifferentResourceLeavesPageUnredirected(): void
    {
        // View belongs to bulk-jobs but URL is on audit-log → listener
        // must bail (cross-resource theft guard).
        $viewId = $this->persistView(
            resourceName: 'bulk-jobs',
            criteria: [['property' => 'status', 'operator' => 'eq', 'values' => ['completed']]],
        );

        $this->client->request('GET', '/admin/polysource/audit-log?view=' . $viewId);

        // The listener leaves the request alone; AdminContextResolver
        // sees ?view= as an unknown query param, EA-equivalent: 200.
        self::assertResponseIsSuccessful(
            'cross-resource view ids on Polysource pages must NOT redirect',
        );
    }

    /**
     * @param list<array{property: string, operator: string, values: list<string>}> $criteria
     */
    private function persistView(string $resourceName, array $criteria): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $record = new SavedViewRecord();
        $record->id = 'polysource-' . bin2hex(random_bytes(4));
        $record->name = 'Test ' . $record->id;
        $record->resourceName = $resourceName;
        $record->ownerId = 'admin@shop.co';
        $record->scope = 'public';
        $record->filtersJson = json_encode($criteria, \JSON_THROW_ON_ERROR);
        $record->columnsJson = '[]';
        $record->sortJson = '[]';
        $record->pageSize = null;
        $record->teamId = null;
        $record->isDefault = false;
        $record->roleAsDefault = null;

        $em->persist($record);
        $em->flush();

        $this->persistedIds[] = $record->id;

        return $record->id;
    }
}
