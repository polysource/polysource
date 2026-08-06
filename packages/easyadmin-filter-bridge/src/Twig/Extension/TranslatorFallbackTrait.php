<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

/**
 * Shared translation helper for the bridge's HTML-emitting Twig
 * extensions.
 *
 * The extensions accept a NULLABLE `TranslatorInterface` (last
 * constructor argument, `null` default) so they keep working on
 * hosts where `framework.translator` is disabled and in bare unit
 * tests — same defensive posture as the bridge's `?Security` /
 * `?RequestStack` constructor args. When the translator is absent,
 * the hard-coded English fallback string is used with plain
 * `strtr()` parameter substitution, which is byte-identical to the
 * pre-i18n output.
 *
 * The using class must declare a
 * `private readonly ?TranslatorInterface $translator` property.
 *
 * IMPORTANT — escaping contract: the returned string is NOT
 * HTML-escaped. Callers interpolating it into `is_safe: html`
 * markup must escape the final string (and pass UNescaped
 * parameters here, so values aren't double-escaped).
 */
trait TranslatorFallbackTrait
{
    /**
     * @param array<string, string> $params `%placeholder%` → value
     */
    private function transWithFallback(string $id, string $fallback, array $params = []): string
    {
        if (null === $this->translator) {
            return strtr($fallback, $params);
        }

        return $this->translator->trans($id, $params, 'PolysourceEasyAdminFilterBridge');
    }
}
