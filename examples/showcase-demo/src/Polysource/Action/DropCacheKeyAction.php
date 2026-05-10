<?php

declare(strict_types=1);

namespace App\Polysource\Action;

use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Action\StyledActionInterface;
use Polysource\Core\Query\DataRecord;
use Throwable;

/**
 * Drop a single cached entry — equivalent to `redis-cli DEL <key>`,
 * but auditable + permission-gated through Polysource. The
 * RedisHashDataSource on the showcase is configured with the
 * `shopco:cache:` prefix, so the inline action only ever targets
 * keys under that namespace; the framework's own pool keys live
 * under another prefix and are unreachable here.
 */
final class DropCacheKeyAction implements InlineActionInterface, StyledActionInterface
{
    private const KEY_PREFIX = 'shopco:cache:';

    public function __construct(
        private readonly RedisHashClientInterface $client,
    ) {
    }

    public function getName(): string
    {
        return 'drop';
    }

    public function getLabel(): string
    {
        return 'Drop';
    }

    public function getIcon(): string
    {
        return 'trash';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_CACHE_DROP';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function getCssVariant(): string
    {
        return 'danger';
    }

    public function getConfirmation(): string
    {
        return 'Drop this cache entry? Will be re-populated lazily on next read.';
    }

    public function execute(DataRecord $record): ActionResult
    {
        $key = self::KEY_PREFIX . $record->identifier;

        try {
            $this->client->del($key);

            return ActionResult::success(\sprintf('Cache key "%s" dropped.', $record->identifier));
        } catch (Throwable $e) {
            return ActionResult::failure(\sprintf('Could not drop "%s": %s', $record->identifier, $e->getMessage()));
        }
    }
}
