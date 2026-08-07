<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use PHPUnit\Framework\Attributes\Test;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use stdClass;

/**
 * End-to-end coverage of `GET /admin/polysource/row-detail/{resource}/{id}`
 * (v1.1.0):
 *
 *  - fragment mode renders the provider's template with the entity
 *    + provider context;
 *  - page mode (no-JS baseline) wraps the same fragment in a
 *    standalone document;
 *  - the provider's permission attribute is checked with the ENTITY
 *    as voter subject (fixture voter denies archived items → 403);
 *  - an entity without a provider 404s (the feature is opt-in);
 *  - unknown ids / unmapped classes 404.
 *
 * Fixture seed (cf. BridgeIntegrationTestCase): item ids 1-30,
 * status = ['draft','published','archived','review'][id % 4] →
 * id 1 = published, id 2 = archived.
 */
final class RowDetailEndpointTest extends BridgeIntegrationTestCase
{
    #[Test]
    public function fragmentModeRendersProviderTemplateWithContext(): void
    {
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/1?fragment=1',
            $this->encodeResource(TestItem::class),
        ));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('Detail for Item 1 (published)', $body, 'Template must receive the entity');
        self::assertStringContainsString('Shouted: ITEM 1', $body, 'Template must receive the provider context');
        self::assertStringNotContainsString('<!DOCTYPE', $body, 'Fragment mode must not wrap in a document');
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function pageModeWrapsFragmentInStandaloneDocument(): void
    {
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/1',
            $this->encodeResource(TestItem::class),
        ));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('<!DOCTYPE html>', $body, 'No-JS baseline must be a full document');
        self::assertStringContainsString('Detail for Item 1 (published)', $body);
    }

    #[Test]
    public function permissionIsCheckedWithEntityAsVoterSubject(): void
    {
        // id 2 → status archived → fixture voter denies.
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/2?fragment=1',
            $this->encodeResource(TestItem::class),
        ));

        self::assertSame(403, $response->getStatusCode(), 'Voter must receive the entity and deny archived items');
    }

    #[Test]
    public function entityWithoutProviderIs404(): void
    {
        // SavedViewRecord is mapped but has no row-detail provider.
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/1?fragment=1',
            $this->encodeResource(SavedViewRecord::class),
        ));

        self::assertSame(404, $response->getStatusCode(), 'Row details are opt-in per entity');
    }

    #[Test]
    public function unknownRecordIs404(): void
    {
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/9999?fragment=1',
            $this->encodeResource(TestItem::class),
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function unmappedClassIs404(): void
    {
        $response = $this->request('GET', \sprintf(
            '/admin/polysource/row-detail/%s/1?fragment=1',
            $this->encodeResource(stdClass::class),
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}
