<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\ChipFormatter;

use Polysource\Filter\Bridge\Contract\ChipFormatterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service-based chip formatter for the `isVisible` boolean filter on
 * Category. Demonstrates ADR-016 — the bridge accepts both inline
 * closures (cf. ProductCrudController) and {@see ChipFormatterInterface}
 * services with constructor DI (this controller).
 *
 * In a real app a service-based formatter is the right choice when:
 *  - the chip text needs to be translated (Symfony Translator);
 *  - the formatter has shared logic that another CrudController also
 *    needs;
 *  - the formatter does I/O (entity lookup, cached enums) that
 *    belongs in a real service.
 *
 * For one-off cases without DI, an inline closure remains simpler.
 */
final class VisibilityChipFormatter implements ChipFormatterInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function format(mixed $rawValue): string
    {
        $isShown = true === $rawValue || '1' === $rawValue || 1 === $rawValue;

        // The translation key is invented for the demo — the messages
        // resolve via `messages.en.yaml` and friends. Falls back to
        // the raw key in any locale that doesn't define it.
        return $this->translator->trans(
            $isShown ? 'category.visibility.shown' : 'category.visibility.hidden',
        );
    }
}
