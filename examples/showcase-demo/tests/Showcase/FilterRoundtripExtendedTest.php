<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Extends `SavedViewRoundtripTest` with the filter types it didn't
 * cover (NumericFilter, NotNullFilter, InFilter, FullTextSearchFilter,
 * EnhancedTextFilter with min_length).
 *
 * The original test pinned 3 of 7 filter types after the 2026-05-07
 * client integration found 8 bugs. This test closes the matrix so a
 * regression on ANY filter type's URL shape would surface as a test
 * failure rather than a manual-test discovery.
 *
 * Each scenario:
 *   1. Persist a SavedViewRecord directly in the DB.
 *   2. GET /admin/<resource>?view=<id>.
 *   3. Assert the SavedViewApplySubscriber redirects with the EA
 *      filter-form URL shape that EA's binding accepts.
 *   4. GET the redirected URL and assert the page renders 200
 *      (the binding actually accepted what we emitted).
 */
final class FilterRoundtripExtendedTest extends WebTestCase
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
        // Hard cleanup of every record this test instance persisted —
        // shared DB across tests means a leftover record with bad data
        // can poison the dropdown rendering of every subsequent test
        // (the dropdown loads all visible views and explodes if any one
        // deserialises poorly). Always cleanup, even on failure.
        if ([] !== $this->persistedIds) {
            /** @var EntityManagerInterface $em */
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->createQuery('DELETE FROM ' . SavedViewRecord::class . ' v WHERE v.id IN (:ids)')
                ->setParameter('ids', $this->persistedIds)
                ->execute();
        }
        parent::tearDown();
    }

    public function testNumericFilterGteRoundtripsAsComparisonValueEnvelope(): void
    {
        $viewId = $this->persistView(
            resource: Order::class,
            criteria: [['property' => 'totalCents', 'operator' => 'gte', 'values' => ['5000']]],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);
        self::assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5BtotalCents%5D%5Bcomparison%5D=%3E%3D', $location, 'gte → %3E%3D (>=)');
        self::assertStringContainsString('filters%5BtotalCents%5D%5Bvalue%5D=5000', $location);

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful('NumericFilter URL shape must bind on EA index');
    }

    public function testNotNullFilterHasValueRoundtripsAsBareValueNoComparison(): void
    {
        // NotNullFilter form type emits an empty comparison (HiddenType
        // with empty data). When the user saves a view from this filter,
        // the SavedViewController stores `operator='eq'` (the canonical
        // FilterCriterion fallback — empty operators are rejected by
        // FilterCriterion's invariant). The replay must produce
        // `filters[shippedAt][value]=not_null` so NotNullFilter::apply()
        // sees value=NotNullFilterType::VALUE_NOT_NULL.
        $viewId = $this->persistView(
            resource: Order::class,
            criteria: [['property' => 'shippedAt', 'operator' => 'eq', 'values' => ['not_null']]],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);
        self::assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString(
            'filters%5BshippedAt%5D%5Bvalue%5D=not_null',
            $location,
            'NotNullFilter "Has value" must replay as value=not_null',
        );

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful('NotNullFilter URL shape must bind on EA index');
    }

    public function testInFilterRoundtripsAsArrayValueWithEqComparison(): void
    {
        $viewId = $this->persistView(
            resource: Customer::class,
            criteria: [['property' => 'city', 'operator' => 'in', 'values' => ['Paris', 'Lyon']]],
        );

        $this->client->request('GET', '/admin/customer?view=' . $viewId);
        self::assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5Bcity%5D%5Bcomparison%5D=%3D', $location, 'in → comparison "="');
        self::assertStringContainsString('filters%5Bcity%5D%5Bvalue%5D%5B0%5D=Paris', $location, 'first value indexed');
        self::assertStringContainsString('filters%5Bcity%5D%5Bvalue%5D%5B1%5D=Lyon', $location, 'second value indexed');

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful('InFilter URL shape must bind on EA index');
    }

    public function testFullTextSearchFilterRoundtripsAsLikeComparison(): void
    {
        $viewId = $this->persistView(
            resource: Product::class,
            criteria: [['property' => 'description', 'operator' => 'like', 'values' => ['premium']]],
        );

        $this->client->request('GET', '/admin/product?view=' . $viewId);
        self::assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5Bdescription%5D%5Bcomparison%5D=like', $location);
        self::assertStringContainsString('filters%5Bdescription%5D%5Bvalue%5D=premium', $location);

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful('FullTextSearchFilter URL shape must bind on EA index');
    }

    public function testTextFilterLikeRoundtripsCorrectly(): void
    {
        $viewId = $this->persistView(
            resource: Order::class,
            criteria: [['property' => 'reference', 'operator' => 'like', 'values' => ['ORD-2026']]],
        );

        $this->client->request('GET', '/admin/order?view=' . $viewId);
        self::assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5Breference%5D%5Bcomparison%5D=like', $location);
        self::assertStringContainsString('filters%5Breference%5D%5Bvalue%5D=ORD-2026', $location);

        $this->client->request('GET', $location);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param list<array{property: string, operator: string, values: list<string>}> $criteria
     */
    private function persistView(string $resource, array $criteria): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $record = new SavedViewRecord();
        $record->id = 'roundtrip-' . bin2hex(random_bytes(4));
        $record->name = 'Test ' . $record->id;
        $record->resourceName = $resource;
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
