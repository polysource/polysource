<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Virtual EA field rendering the row-expansion control (chevron)
 * as the row's first cell. Add it at the top of `configureFields()`
 * on any CRUD whose entity has a registered
 * {@see \Polysource\EasyAdminFilterBridge\RowDetail\RowDetailProviderInterface}:
 *
 *     public function configureFields(string $pageName): iterable
 *     {
 *         yield Polysource::rowDetail();
 *         yield IdField::new('id');
 *         // ...
 *     }
 *
 * Going through EA's own field system (instead of overriding EA's
 * `table_body_row` block) keeps the bridge byte-identical with the
 * upstream table markup on both EA 4.24 and 5.x.
 *
 * The cell renders nothing when no provider is registered for the
 * CRUD's entity, or when the provider's permission attribute is
 * denied for the row — a listing without providers is visually
 * unchanged.
 *
 * Server-side baseline (ADR-027): the chevron is a real link to the
 * standalone row-detail page; the `polysource--row-details` Stimulus
 * controller upgrades it to lazy in-place expansion.
 *
 * @since 1.1.0
 */
final class RowDetailField implements FieldInterface
{
    use FieldTrait;

    /**
     * When true, re-fetch the detail on every expansion instead of
     * reusing the first response (for volatile content — live
     * statuses, queue depths, …).
     */
    public const OPTION_RELOAD_ON_OPEN = 'polysourceRowDetailReloadOnOpen';

    /**
     * Virtual property name — never resolved against the entity.
     */
    public const PROPERTY = '__polysource_row_detail';

    /**
     * Both parameters exist only to satisfy EA's `FieldInterface`
     * signature — the property is always virtual and the cell has
     * no meaningful header, so callers just use
     * `RowDetailField::new()` / `Polysource::rowDetail()`.
     */
    public static function new(string $propertyName = self::PROPERTY, TranslatableInterface|string|bool|null $label = false): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setSortable(false)
            ->setVirtual(true)
            ->onlyOnIndex()
            ->setTemplatePath('@PolysourceEasyAdminFilterBridge/crud/field/row_detail.html.twig')
            ->addCssClass('polysource-row-detail-cell')
            ->setCustomOption(self::OPTION_RELOAD_ON_OPEN, false);
    }

    public function reloadOnOpen(bool $reload = true): self
    {
        $this->setCustomOption(self::OPTION_RELOAD_ON_OPEN, $reload);

        return $this;
    }
}
