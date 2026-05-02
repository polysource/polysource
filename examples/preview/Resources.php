<?php

declare(strict_types=1);

namespace Polysource\Examples\Preview;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Concrete fields used by the preview app.
 *
 * They live here (rather than in `polysource/core`) because v0.1 ships
 * only the abstract `FieldInterface` + `FieldTrait`. Dedicated field
 * types arrive in a later phase.
 */
final class TextField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/text.html.twig');
    }
}

final class IdField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/id.html.twig');
    }
}

final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/boolean.html.twig');
    }
}

final class DateTimeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/datetime.html.twig');
    }
}

final class CodeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/code.html.twig');
    }
}

/**
 * In-memory DataSource backing the preview app.
 */
final class InMemoryDataSource implements DataSourceInterface
{
    /**
     * @param list<DataRecord> $records
     */
    public function __construct(
        private readonly array $records,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        return new DataPage(items: $this->records, total: \count($this->records));
    }

    public function find(string|int $identifier): ?DataRecord
    {
        foreach ($this->records as $record) {
            if ((string) $record->identifier === (string) $identifier) {
                return $record;
            }
        }

        return null;
    }

    public function count(DataQuery $query): int
    {
        return \count($this->records);
    }
}

/**
 * Feature-flags resource. Hand-rolled records exercise every field
 * template shipped by polysource/twig-theme (text, boolean, datetime,
 * code, id, generic).
 */
final class FlagsResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource(self::seedRecords()));
    }

    public function getName(): string
    {
        return 'flags';
    }

    public function getLabel(): string
    {
        return 'Feature flags';
    }

    public function configureFields(string $page): iterable
    {
        yield IdField::new('id', 'ID');
        yield TextField::new('name', 'Name');
        yield BooleanField::new('enabled', 'Enabled');
        yield DateTimeField::new('updated_at', 'Last updated');
        yield TextField::new('description', 'Description')->onlyOnDetail();
        yield CodeField::new('config', 'Config')->onlyOnDetail();
    }

    /**
     * @return list<DataRecord>
     */
    private static function seedRecords(): array
    {
        return [
            new DataRecord('checkout-v2', [
                'id' => 'checkout-v2',
                'name' => 'Checkout v2',
                'enabled' => true,
                'updated_at' => '2026-04-28T10:32:00+00:00',
                'description' => 'Two-step checkout funnel rolled out to 30% of EU traffic.',
                'config' => [
                    'rollout_percent' => 30,
                    'regions' => ['EU'],
                    'fallback' => 'checkout-v1',
                ],
            ]),
            new DataRecord('dark-mode', [
                'id' => 'dark-mode',
                'name' => 'Dark mode',
                'enabled' => true,
                'updated_at' => '2026-03-12T14:05:00+00:00',
                'description' => 'OS-aware dark theme. Generally available.',
                'config' => ['default' => 'system'],
            ]),
            new DataRecord('export-csv', [
                'id' => 'export-csv',
                'name' => 'CSV export',
                'enabled' => false,
                'updated_at' => '2026-02-04T08:00:00+00:00',
                'description' => 'Disabled while we migrate the report worker.',
                'config' => ['reason' => 'worker-migration'],
            ]),
            new DataRecord('beta-search', [
                'id' => 'beta-search',
                'name' => 'Beta search',
                'enabled' => true,
                'updated_at' => '2026-04-30T22:15:00+00:00',
                'description' => 'Meilisearch-backed product search. Limited to internal users.',
                'config' => ['provider' => 'meilisearch', 'audience' => 'internal'],
            ]),
        ];
    }
}
