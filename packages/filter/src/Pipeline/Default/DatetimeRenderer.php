<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Default;

use Polysource\Filter\Pipeline\FilterRendererInterface;

/**
 * Default renderer for `datetime` filters — uses Symfony Form's stock
 * `DateTimeType` for the `value` widget. The composition (comparison
 * select + value + value2) is handled by `FilterCollectionType`.
 */
final class DatetimeRenderer implements FilterRendererInterface
{
    public const NAME = 'datetime';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function getFormType(): string
    {
        return \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class;
    }
}
