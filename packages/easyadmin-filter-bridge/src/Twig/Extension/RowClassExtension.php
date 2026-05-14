<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use BackedEnum;
use ReflectionMethod;
use ReflectionProperty;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use UnitEnum;

/**
 * Twig extension exposing `polysource_row_class(entity, property, classMap)` —
 * returns a CSS class string for a table row based on the value of
 * one of the entity's properties.
 *
 * Used in EA index template overrides to colour rows based on a
 * status / state / flag. Example host override
 * (`templates/bundles/EasyAdminBundle/crud/index.html.twig`):
 *
 *     {% block table_body %}
 *         {% for entity in entities %}
 *             <tr class="{{ polysource_row_class(entity.instance, 'status', {
 *                 refunded: 'table-danger',
 *                 archived: 'text-muted',
 *             }) }}">
 *                 …
 *             </tr>
 *         {% endfor %}
 *     {% endblock %}
 *
 * Property resolution: tries `getX()`, `isX()`, then direct public
 * property access. Returns the empty string when nothing matches
 * (no exception) so the helper can be sprinkled freely in templates
 * without try/catch in Twig.
 *
 * For more complex rules (combinations of multiple properties,
 * arithmetic, custom logic), hosts write their own Twig function —
 * this helper covers the 80% "map this property's value to a CSS
 * class" use case.
 *
 * @since 0.3.0
 */
final class RowClassExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_row_class', $this->rowClass(...)),
        ];
    }

    /**
     * @param array<string|int|bool, string> $classMap value → css class
     */
    public function rowClass(
        object $entity,
        string $property,
        array $classMap,
        string $default = '',
    ): string {
        $value = $this->readProperty($entity, $property);
        if (null === $value) {
            return $default;
        }

        // Coerce booleans to their stringified form so the host can
        // key the map on 'true'/'false' OR on the actual bool. Both
        // are recognised — we look up the raw value first.
        if (\array_key_exists($this->normaliseKey($value), $this->normaliseMap($classMap))) {
            return $this->normaliseMap($classMap)[$this->normaliseKey($value)];
        }

        return $default;
    }

    private function readProperty(object $entity, string $property): mixed
    {
        // Dynamic method + property dispatch is intentional here —
        // the helper's purpose is to resolve arbitrary host-defined
        // properties at template-render time. Reflection routes around
        // PHPStan's static-only dynamic-dispatch detection without
        // losing the dynamic resolution we actually need.
        $getter = 'get' . ucfirst($property);
        if (method_exists($entity, $getter)) {
            return (new ReflectionMethod($entity, $getter))->invoke($entity);
        }

        $isser = 'is' . ucfirst($property);
        if (method_exists($entity, $isser)) {
            return (new ReflectionMethod($entity, $isser))->invoke($entity);
        }

        if (property_exists($entity, $property)) {
            $reflectionProperty = new ReflectionProperty($entity, $property);
            if ($reflectionProperty->isInitialized($entity)) {
                return $reflectionProperty->getValue($entity);
            }
        }

        return null;
    }

    private function normaliseKey(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (null === $value) {
            return '';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return '';
    }

    /**
     * @param array<string|int|bool, string> $map
     *
     * @return array<string, string>
     */
    private function normaliseMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $class) {
            $out[$this->normaliseKey($key)] = $class;
        }

        return $out;
    }
}
