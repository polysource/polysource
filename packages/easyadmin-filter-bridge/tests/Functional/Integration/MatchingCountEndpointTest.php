<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use PHPUnit\Framework\Attributes\Test;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;

/**
 * End-to-end coverage for `GET /admin/polysource/matching-count/{resource}`.
 *
 * Locks in regression coverage for the C5 (cold metadata cache 404),
 * C7 (toIterable + HYDRATE_ARRAY + entity-alias select yields 0),
 * C10 (IN / NOT IN / IS [NOT] NULL silently dropped) fixes from v0.5.7.
 */
final class MatchingCountEndpointTest extends BridgeIntegrationTestCase
{
    #[Test]
    public function returnsCountAndSamplesForUnfilteredQuery(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/matching-count/' . $resource . '?samples=3');

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);

        self::assertSame(30, $json['count']);
        self::assertIsArray($json['samples']);
        self::assertCount(3, $json['samples']);
        // C7 regression — samples must contain real entity data, not
        // empty arrays. Pre-fix, the iterator yielded zero rows and
        // samples was [].
        self::assertSame('Item 1', $json['samples'][0]['name']);
    }

    #[Test]
    public function returns404OnUnmappedEntity(): void
    {
        $response = $this->request('GET', '/admin/polysource/matching-count/NotAnEntity');
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function resolvesMappedEntityEvenOnColdMetadataCache(): void
    {
        // C5 regression — `resolveEntityClass` previously had inverted
        // boolean logic that 404'd valid mapped entities whose metadata
        // hadn't been warmed in the current request's cache. This test
        // freshly resolves the entity for the first time in a brand-new
        // request scope and asserts success.
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/matching-count/' . $resource);

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        self::assertSame(30, $json['count']);
    }

    #[Test]
    public function filtersByScalarEqualityViaExpandedShape(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/matching-count/%s?filters[status][comparison]==&filters[status][value]=published&samples=5',
            $resource,
        ));

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        self::assertGreaterThan(0, $json['count']);
        self::assertLessThan(30, $json['count']);
        self::assertIsArray($json['samples']);
        foreach ($json['samples'] as $sample) {
            self::assertSame('published', $sample['status']);
        }
    }

    #[Test]
    public function filtersByInComparisonAcrossMultipleValues(): void
    {
        // C10 regression — `comparison=IN` + `value[]=...` was silently
        // dropped by the UrlFilterApplier match block. Pre-fix, this
        // query returned 30 (every row). Post-fix, it returns only
        // the rows matching draft OR published.
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/matching-count/%s?filters[status][comparison]=IN&filters[status][value][]=draft&filters[status][value][]=published',
            $resource,
        ));

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        // 30 items, 4 status buckets — draft + published is roughly half.
        self::assertGreaterThan(10, $json['count']);
        self::assertLessThan(30, $json['count']);
    }

    #[Test]
    public function filtersByIsNotNullComparison(): void
    {
        // C10 regression — `comparison=IS NOT NULL` was silently dropped.
        // Every TestItem has a non-null createdAt, so the predicate
        // matches every row (30). The DQL `IS NOT NULL` clause MUST
        // actually fire (visible via the explain plan / no exception).
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/matching-count/%s?filters[createdAt][comparison]=IS NOT NULL',
            $resource,
        ));

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        self::assertSame(30, $json['count']);
    }

    #[Test]
    public function dropsUnknownFilterPropertySilently(): void
    {
        // SQL injection probe — unknown property must be ignored by
        // the applier's `hasField` guard. Defensive contract.
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/matching-count/%s?filters[unknownProperty][value]=42&filters[unknownProperty][comparison]==',
            $resource,
        ));

        self::assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        self::assertSame(30, $json['count'], 'unknown property must be silently dropped — all rows returned');
    }

    #[Test]
    public function clampsSamplesQueryParameterToBoundedRange(): void
    {
        // Defensive bound: max(0, min(50, request samples)).
        $resource = $this->encodeResource(TestItem::class);

        $response = $this->request('GET', '/admin/polysource/matching-count/' . $resource . '?samples=100');
        $json = $this->decodeJsonResponse($response);
        // Clamp to 50, bounded by actual count (30).
        self::assertIsArray($json['samples']);
        self::assertCount(30, $json['samples']);

        $response = $this->request('GET', '/admin/polysource/matching-count/' . $resource . '?samples=-5');
        $json = $this->decodeJsonResponse($response);
        self::assertSame([], $json['samples'], 'negative samples must clamp to 0');
    }
}
