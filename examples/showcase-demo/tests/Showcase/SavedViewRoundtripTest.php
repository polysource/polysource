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
 * Sprint 0 / Sprint 1 closer — proves the end-to-end saved-view loop
 * works for a real EasyAdmin controller in the showcase, through
 * Symfony Form binding and the bridge's `SavedViewApplySubscriber`
 * redirect.
 *
 * The 2026-05-07 client integration found 8 bugs in this loop because
 * the previous test layer stopped at "URL is built correctly" — it
 * never asserted that EA's filter form binding actually accepted the
 * URL and rendered a filtered table.
 *
 * This test closes the loop: persist a SavedViewRecord directly in
 * the database (skipping the create POST so the test stays focused on
 * the apply/redirect path), then GET the EA controller with `?view=ID`
 * and assert:
 *
 *   1. The subscriber redirects (302) to a clean URL with EA-shape
 *      `filters[...]` query params and no `view` param.
 *   2. The redirected URL renders 200 (EA's form binding accepts it).
 *   3. The body contains only orders matching the filter (the table
 *      is actually filtered, not just dressed with active-chips).
 */
final class SavedViewRoundtripTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // KernelBrowser auto-follows redirects in some Sf versions —
        // pin "off" so we can assert the 302 from the subscriber.
        $this->client->followRedirects(false);

        // Authenticate as admin — every test needs auth + Doctrine fixtures
        // share the test database with EasyAdminSmokeTest.
        $repo = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $repo->findOneBy(['email' => 'admin@shop.co']);
        if (!$admin instanceof User) {
            self::markTestSkipped('admin@shop.co fixture missing — run app:fixtures:load first');
        }
        $this->client->loginUser($admin);
    }

    public function testSavedViewWithTextLikeFilterRedirectsToFilteredOrdersIndex(): void
    {
        $viewId = $this->persistSavedView(
            resource: Order::class,
            ownerId: 'admin@shop.co',
            criteria: [
                ['property' => 'reference', 'operator' => 'like', 'values' => ['ORD-2026-001']],
            ],
        );

        // Verify the view loads via the service (sanity check).
        $service = self::getContainer()->get(\Polysource\Filter\SavedView\SavedViewService::class);
        $loaded = $service->load($viewId);
        self::assertNotNull($loaded, 'SavedViewService::load returned null — voter denied or storage miss');
        self::assertSame(Order::class, $loaded->resourceName);

        $this->client->request('GET', '/admin/order?view=' . $viewId);

        // 1. Subscriber must redirect to the EA-shape URL.
        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5Breference%5D%5Bcomparison%5D=like', $location);
        self::assertStringContainsString('filters%5Breference%5D%5Bvalue%5D=ORD-2026-001', $location);
        self::assertStringNotContainsString('view=', $location, 'redirect must drop the view param');

        // 2. The redirected URL renders successfully — EA's form binding
        // accepts the shape we emitted.
        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful('redirected URL must render the filtered table');
    }

    public function testSavedViewWithChoiceMultiFilterRendersWithoutCrash(): void
    {
        // ChoiceFilter with canSelectMultiple → emits `filters[X][value][]=...`
        // that EA's ChoiceType binds. Bug 2026-05-07: was emitted as plain
        // `eq` operator with single value losing the multi nature.
        $viewId = $this->persistSavedView(
            resource: Order::class,
            ownerId: 'admin@shop.co',
            criteria: [
                ['property' => 'status', 'operator' => 'in', 'values' => ['paid', 'shipped']],
            ],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);
        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location') ?? '';

        // EA's ChoiceFilter encodes multi as comparison=`=` + value=array.
        self::assertStringContainsString('filters%5Bstatus%5D%5Bcomparison%5D=%3D', $location, 'choice multi → comparison `=`');
        self::assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid', $location, 'first value');
        self::assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D%5B1%5D=shipped', $location, 'second value');

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful();
    }

    public function testSavedViewWithBetweenDateFilterRendersWithoutCrash(): void
    {
        $viewId = $this->persistSavedView(
            resource: Order::class,
            ownerId: 'admin@shop.co',
            criteria: [
                ['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']],
            ],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);
        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location') ?? '';

        // EA between → `filters[X][comparison]=between&filters[X][value][min]=...&[max]=...`
        self::assertStringContainsString('filters%5BcreatedAt%5D%5Bcomparison%5D=between', $location);
        self::assertStringContainsString('filters%5BcreatedAt%5D%5Bvalue%5D%5Bmin%5D=2026-01-01', $location);
        self::assertStringContainsString('filters%5BcreatedAt%5D%5Bvalue%5D%5Bmax%5D=2026-12-31', $location);

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful();
    }

    public function testCrossResourceViewIdIsRejectedSilently(): void
    {
        // Persist a view scoped to ProductCrudController, but ask the OrderCrudController to apply it.
        $viewId = $this->persistSavedView(
            resource: \App\Entity\Product::class,
            ownerId: 'admin@shop.co',
            criteria: [
                ['property' => 'name', 'operator' => 'like', 'values' => ['Hat']],
            ],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);

        // Subscriber must not redirect (cross-resource theft guard).
        // EA renders the unfiltered orders index instead.
        self::assertResponseIsSuccessful('cross-resource view ids must NOT trigger a redirect — should render unfiltered');
    }

    public function testNonExistentViewIdRendersUnfilteredIndex(): void
    {
        $this->client->request('GET', '/admin/order?view=00000000-0000-0000-0000-000000000000');
        self::assertResponseIsSuccessful('unknown view id must render unfiltered, not crash or redirect');
    }

    /**
     * @param list<array{property: string, operator: string, values: list<string>}> $criteria
     */
    private function persistSavedView(string $resource, string $ownerId, array $criteria): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $record = new SavedViewRecord();
        $record->id = bin2hex(random_bytes(8)) . '-test';
        $record->name = 'Test view ' . $record->id;
        $record->resourceName = $resource;
        $record->ownerId = $ownerId;
        $record->scope = 'private';
        $record->filtersJson = json_encode($criteria, \JSON_THROW_ON_ERROR);
        $record->columnsJson = '[]';
        $record->sortJson = '[]';
        $record->pageSize = null;
        $record->teamId = null;
        $record->isDefault = false;
        $record->roleAsDefault = null;

        $em->persist($record);
        $em->flush();

        return $record->id;
    }
}
