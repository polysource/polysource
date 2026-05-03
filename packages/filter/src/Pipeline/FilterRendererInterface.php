<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline;

/**
 * Third phase of the filter pipeline — selects the Symfony FormType
 * FQCN that renders the input widget for a given filter `name`.
 *
 * One renderer per filter `name`. Tagged services are registered as
 * `polysource.filter.renderer` and indexed by name by
 * `RendererRegistry` (compile-time).
 *
 * Used by `FilterCollectionType` to dynamically build sub-forms — for
 * each criterion, the renderer answers "which FormType should the
 * form factory instantiate to let the user edit this filter".
 *
 * The renderer's choice can be overridden by a host via the
 * `formSpec` of `FilterDefinition` (e.g. `{form_type:
 * MyCustomFilterType::class}`); the default impls fall back to
 * sensible builtins (Symfony's TextType, NumberType, etc.).
 */
interface FilterRendererInterface
{
    public function supports(string $name): bool;

    /**
     * @return class-string The FQCN of a Symfony FormType
     */
    public function getFormType(): string;
}
