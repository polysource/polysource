<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression test for commit d70953b — when the user picks "Any" on a
 * NotNullFilter (tri-state Any / Has value / Empty), the URL ends up
 * with `?filters[shippedAt][comparison]=&filters[shippedAt][value]=`
 * (both empty). The chip macro's `is_slice_applied` rule used to
 * skip slices with no real value, which is correct for accidentally
 * half-filled ComparisonFilters but DROPS legitimate NotNullFilter
 * "Any" selections — the user clicked deliberately and got no
 * feedback.
 *
 * The fix: introspect the FilterConfigDto's FormType. When it's
 * NotNullFilterType, force-render the chip regardless of value
 * emptiness, with a human label ("Any" / "Has value" / "Empty").
 *
 * Pinned scenarios:
 *   1. URL with `comparison=&value=` (Any) renders a chip "Has shipped: Any".
 *   2. URL with `value=not_null` renders "Has shipped: Has value" + filters.
 *   3. URL with `value=null` renders "Has shipped: Empty" + filters.
 */
final class NotNullFilterChipTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $repo = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $repo->findOneBy(['email' => 'admin@shop.co']);
        if (!$admin instanceof User) {
            self::markTestSkipped('admin@shop.co fixture missing');
        }
        $this->client->loginUser($admin);
    }

    public function testNotNullFilterAnyChoiceRendersChipEvenWithEmptyValue(): void
    {
        $crawler = $this->client->request(
            'GET',
            '/admin/order?filters%5BshippedAt%5D%5Bcomparison%5D=&filters%5BshippedAt%5D%5Bvalue%5D=',
        );

        self::assertResponseIsSuccessful();

        $chips = $crawler->filter('.ea-filter-chips-bar .ea-filter-chip');
        self::assertGreaterThan(
            0,
            $chips->count(),
            'a chip must render even when NotNullFilter "Any" produces empty value (regression of d70953b)',
        );

        $shippedAtChip = $crawler->filter('.ea-filter-chip[data-property="shippedAt"]');
        self::assertSame(
            1,
            $shippedAtChip->count(),
            'the shippedAt chip must be present despite empty comparison/value slot',
        );

        $chipText = $shippedAtChip->text();
        self::assertStringContainsString('Has shipped', $chipText, 'chip label must read the filter declared label');
        self::assertStringContainsString('Any', $chipText, 'chip value must render the human "Any" label');
    }

    public function testNotNullFilterHasValueChoiceRendersChipAndFilters(): void
    {
        $crawler = $this->client->request(
            'GET',
            '/admin/order?filters%5BshippedAt%5D%5Bcomparison%5D=&filters%5BshippedAt%5D%5Bvalue%5D=not_null',
        );

        self::assertResponseIsSuccessful();

        $shippedAtChip = $crawler->filter('.ea-filter-chip[data-property="shippedAt"]');
        self::assertSame(1, $shippedAtChip->count());
        self::assertStringContainsString('Has value', $shippedAtChip->text());
    }

    public function testNotNullFilterEmptyChoiceRendersChipAndFilters(): void
    {
        $crawler = $this->client->request(
            'GET',
            '/admin/order?filters%5BshippedAt%5D%5Bcomparison%5D=&filters%5BshippedAt%5D%5Bvalue%5D=null',
        );

        self::assertResponseIsSuccessful();

        $shippedAtChip = $crawler->filter('.ea-filter-chip[data-property="shippedAt"]');
        self::assertSame(1, $shippedAtChip->count());
        self::assertStringContainsString('Empty', $shippedAtChip->text());
    }
}
