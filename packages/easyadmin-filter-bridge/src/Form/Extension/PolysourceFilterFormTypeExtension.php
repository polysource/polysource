<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony FormTypeExtension that registers the `polysource_group`
 * option on EVERY form type so EA's filter form types tolerate it.
 *
 * Without this, a host calling
 * `setFormTypeOption('polysource_group', 'Status')` on an
 * `EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter` would crash:
 * the underlying `ChoiceFilterType`'s `OptionsResolver` rejects
 * unknown options. We can't strip the key from `formTypeOptions`
 * after the fact because `FilterDto::setFormTypeOptions()` is
 * additive (delegates to `KeyValueStore::setAll()`), so the only
 * way to stay non-invasive is to widen the resolver itself.
 *
 * Extending `FormType::class` (the abstract base type) makes the
 * option available transitively to every FormType in the app, EA's
 * built-ins included. The option is a no-op at the form-rendering
 * level — only the `GroupCarrierConfigurator` reads it back to
 * propagate the group label into `FilterDto::customOption()`,
 * which is then read by `crud/filters.html.twig` to render the
 * accordion sections.
 *
 * Allowed values: `null` (no group) or a non-empty string. Empty
 * strings are normalised to null by the resolver.
 */
final class PolysourceFilterFormTypeExtension extends AbstractTypeExtension
{
    public const OPTION_NAME = 'polysource_group';

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION_NAME, null);
        $resolver->setAllowedTypes(self::OPTION_NAME, ['null', 'string']);
        $resolver->setNormalizer(self::OPTION_NAME, static fn ($options, $value) => '' === $value ? null : $value);
    }
}
