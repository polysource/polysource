<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Entity;

use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
#[ORM\Table(name: 'category')]
class Category implements Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
