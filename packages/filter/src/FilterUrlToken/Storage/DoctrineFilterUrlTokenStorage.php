<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;
use Polysource\Filter\FilterUrlToken\Storage\Doctrine\FilterUrlTokenRecord;

/**
 * Doctrine ORM implementation of {@see FilterUrlTokenStorageInterface}.
 *
 * @since 0.5.0
 */
final class DoctrineFilterUrlTokenStorage implements FilterUrlTokenStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(FilterUrlToken $token): void
    {
        $existing = $this->em->find(FilterUrlTokenRecord::class, $token->token);
        if (!$existing instanceof FilterUrlTokenRecord) {
            $existing = new FilterUrlTokenRecord();
            $existing->token = $token->token;
            $this->em->persist($existing);
        }
        $existing->resourceName = $token->resourceName;
        $existing->filtersSliceJson = json_encode(
            $token->filtersSlice,
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );
        $existing->createdAt = $token->createdAt;

        $this->em->flush();
    }

    public function find(string $token): ?FilterUrlToken
    {
        $record = $this->em->find(FilterUrlTokenRecord::class, $token);
        if (!$record instanceof FilterUrlTokenRecord) {
            return null;
        }

        $raw = json_decode($record->filtersSliceJson, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($raw)) {
            return null;
        }
        $slice = [];
        foreach ($raw as $key => $value) {
            if (\is_string($key)) {
                $slice[$key] = $value;
            }
        }
        if ([] === $slice) {
            return null;
        }

        return new FilterUrlToken(
            token: $record->token,
            resourceName: $record->resourceName,
            filtersSlice: $slice,
            createdAt: $record->createdAt,
        );
    }
}
