<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Fixture;

use Polysource\Core\Resource\AbstractResource;

/**
 * In-memory test resource. Wraps a {@see FakeDataSource} to satisfy the
 * `AbstractResource` constructor without requiring a real adapter.
 */
final class FakeResource extends AbstractResource
{
    public function __construct(
        private readonly string $slug,
        private readonly string $label = 'Fake',
    ) {
        parent::__construct(new FakeDataSource());
    }

    public function getName(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
