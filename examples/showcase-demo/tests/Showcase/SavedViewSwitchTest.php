<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression test for commit 3f91cce — switching from saved view A
 * to saved view B used to land the user on `/admin/order` clean (no
 * filter applied) on the FIRST click. Required clicking again to
 * re-apply.
 *
 * Cause: two subscribers on EA's BeforeCrudActionEvent
 * (SavedViewApplySubscriber priority 100 + FilterSessionPersistenceSubscriber
 * priority 0). EA's StoppableEventTrait isn't PSR-14, so Symfony's
 * dispatcher doesn't break the loop after the first sets a response.
 * The persistence subscriber's "explicit reset" branch (referer has
 * filters, current URL has none) fired and overrode the saved-view
 * redirect with a clean URL.
 *
 * The fix: bail at top of FilterSessionPersistenceSubscriber if
 * propagation is stopped OR if `?view=` is in the query.
 *
 * This test pins the fix by simulating the exact user flow:
 *   1. Click view A → 302 to `?filters[A]=...`
 *   2. From the filtered URL (Referer), click view B → 302 to `?filters[B]=...`
 *      (NOT to `/admin/order` clean)
 */
final class SavedViewSwitchTest extends WebTestCase
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

    public function testSwitchingViewAToViewBOnFirstClickRedirectsToBsFilters(): void
    {
        $viewA = $this->persistSavedView('view-a-multi', [
            ['property' => 'status', 'operator' => 'in', 'values' => ['paid', 'preparing']],
        ]);

        $viewB = $this->persistSavedView('view-b-single', [
            ['property' => 'status', 'operator' => 'eq', 'values' => ['delivered']],
        ]);

        // Step 1 — apply view A. Land on the filtered URL.
        $this->client->request('GET', '/admin/order?view=' . $viewA);
        self::assertResponseRedirects();
        $filterUrlA = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5Bstatus%5D', $filterUrlA);

        // Step 2 — click view B FROM the filtered URL of A.
        // The Referer header carries the previous filter URL; the
        // FilterSessionPersistenceSubscriber's reset detection used to
        // fire here and clobber the apply redirect.
        $this->client->request(
            'GET',
            '/admin/order?view=' . $viewB,
            server: ['HTTP_REFERER' => 'http://localhost' . $filterUrlA],
        );

        self::assertResponseRedirects(
            null,
            302,
            'switching from one saved view to another must produce a redirect, not a 200',
        );
        $filterUrlB = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringNotContainsString(
            'view=',
            $filterUrlB,
            'the redirect must drop the view= param, not preserve it',
        );
        self::assertStringContainsString(
            'filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=delivered',
            $filterUrlB,
            'the redirect must encode view B filters, not land on a clean URL',
        );
    }

    /**
     * @param list<array{property: string, operator: string, values: list<string>}> $criteria
     */
    private function persistSavedView(string $idPrefix, array $criteria): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $record = new SavedViewRecord();
        $record->id = $idPrefix . '-' . bin2hex(random_bytes(4));
        $record->name = 'Test ' . $record->id;
        $record->resourceName = Order::class;
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
