<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Minimal mapped entity for bridge integration tests.
 *
 * Covers a representative mix of column types so the export and
 * matching-count endpoints exercise their full coercion chain:
 *
 * - int (id, stock)
 * - string (name, status)
 * - bool (isActive)
 * - datetime_immutable (createdAt) — locks in the v0.5.7 C8 ISO 8601
 *   contract via export-format assertion
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_item')]
class TestItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 100)]
    public string $name = '';

    #[ORM\Column(type: 'string', length: 32)]
    public string $status = 'draft';

    #[ORM\Column(type: 'integer')]
    public int $stock = 0;

    #[ORM\Column(type: 'boolean')]
    public bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    public function __construct(int $id, string $name, string $status, int $stock, bool $isActive, DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->stock = $stock;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
    }
}
