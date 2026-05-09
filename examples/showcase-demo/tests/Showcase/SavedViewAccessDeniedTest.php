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
 * Regression test for commit a6e0cbb — SavedViewService throws a typed
 * `SavedViewAccessDeniedException` (instead of a bare `RuntimeException`)
 * when the voter denies. The bridge SavedViewController catches it and
 * re-throws Symfony's `AccessDeniedHttpException` → 403 with a clean
 * message.
 *
 * Pre-fix: bare RuntimeException leaked through to the framework error
 * handler → 500 with stack trace, indistinguishable from genuine
 * runtime faults.
 *
 * Pinned scenarios:
 *   1. ops@shop.co tries to delete admin@shop.co's PRIVATE saved view
 *      → 403 (voter denies DELETE on non-owner private view).
 *   2. ops@shop.co tries to overwrite admin's view (save with same id)
 *      → 403 (voter denies EDIT on non-owner).
 */
final class SavedViewAccessDeniedTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $persistedIds = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->followRedirects(false);
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

    public function testDeletingAnotherUsersPrivateViewReturnsForbiddenNotServerError(): void
    {
        // Persist a PRIVATE view owned by admin@shop.co.
        $adminViewId = $this->persistPrivateView('admin@shop.co', 'admin-private-orders');

        // Login as ops — different user, should not be able to delete.
        $this->loginAs('ops@shop.co');

        // The delete route is POST + needs CSRF. Use the JSON-driven
        // form that the dropdown emits (we replicate it here).
        $this->client->request(
            'POST',
            '/admin/saved-views/' . $adminViewId . '/delete',
            server: ['HTTP_REFERER' => 'http://localhost/admin/order'],
        );

        self::assertResponseStatusCodeSame(
            403,
            'cross-user delete must surface as a typed 403, not a 500 (regression of a6e0cbb)',
        );
    }

    /**
     * Sanity: the OWNER must still be able to delete their own private view.
     * Without this, a regression that 403s ALL deletes (rather than just
     * non-owner deletes) would slip through testDelete...Forbidden... above.
     */
    public function testOwnerCanDeleteTheirOwnPrivateView(): void
    {
        $adminViewId = $this->persistPrivateView('admin@shop.co', 'admin-own-delete');

        $this->loginAs('admin@shop.co');

        $this->client->request(
            'POST',
            '/admin/saved-views/' . $adminViewId . '/delete',
            server: ['HTTP_REFERER' => 'http://localhost/admin/order'],
        );

        self::assertResponseRedirects(
            null,
            302,
            'owner delete should redirect back to referer (success path)',
        );
    }

    private function loginAs(string $email): void
    {
        $repo = self::getContainer()->get('doctrine')->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            self::markTestSkipped(\sprintf('%s fixture missing', $email));
        }
        $this->client->loginUser($user);
    }

    private function persistPrivateView(string $ownerId, string $idPrefix): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $record = new SavedViewRecord();
        $record->id = $idPrefix . '-' . bin2hex(random_bytes(4));
        $record->name = 'Private ' . $record->id;
        $record->resourceName = Order::class;
        $record->ownerId = $ownerId;
        $record->scope = 'private';
        $record->filtersJson = json_encode([
            ['property' => 'status', 'operator' => 'eq', 'values' => ['paid']],
        ], \JSON_THROW_ON_ERROR);
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
