<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Tests\Fixture;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test entity used by DoctrineDataSource tests — kept inside the
 * tests folder to avoid leaking a test-only mapping into the
 * production autoload (see composer.json's autoload-dev section).
 */
#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    public string $name = '';

    #[ORM\Column(type: 'string', length: 32)]
    public string $sku = '';

    #[ORM\Column(type: 'integer')]
    public int $priceCents = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
