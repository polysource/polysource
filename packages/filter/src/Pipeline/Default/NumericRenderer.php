<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Default;

use Polysource\Filter\Pipeline\FilterRendererInterface;

/**
 * Default renderer for `numeric` filters — uses Symfony Form's stock
 * `NumberType` for the `value` widget. The composition (comparison
 * select + value + value2) is handled by `FilterCollectionType`.
 */
final class NumericRenderer implements FilterRendererInterface
{
    public const NAME = 'numeric';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function getFormType(): string
    {
        return \Symfony\Component\Form\Extension\Core\Type\NumberType::class;
    }
}
