<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Metadata;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Everything a class's declaration fixes about how it is mapped.
 *
 * All of it was re-derived through fresh reflection on every mapSingleObject() call, which for a
 * collection is once per ELEMENT: fifty rows of one class asked fifty times for an answer the
 * declaration had already settled.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ClassMetadata
{
    /**
     * @param list<string>                                               $properties         Declared property names the mapper may write.
     * @param array<string, string>                                      $replaceMap         Payload name to property name, from ReplaceProperty.
     * @param string|null                                                $collectorProperty  Property marked with UnknownPropertyCollector.
     * @param ReflectionMethod|null                                      $constructor        Constructor to hydrate through, when there is one.
     * @param array<string, bool>                                        $requiredProperties Whether each property must be present in the payload.
     * @param array<string, bool>                                        $replaceNullFlags   Whether each property carries ReplaceNullWithDefaultValue.
     * @param array<string, ReflectionProperty|ReflectionParameter|null> $defaultSources     Reflection handle carrying each property's default, or null when it has none.
     */
    public function __construct(
        public array $properties,
        public array $replaceMap,
        public ?string $collectorProperty,
        public ?ReflectionMethod $constructor,
        private array $requiredProperties,
        private array $replaceNullFlags,
        private array $defaultSources,
    ) {
    }

    /**
     * Indicates whether the property must be present in the payload.
     *
     * @param string $propertyName Property to check.
     *
     * @return bool True when a missing value is a failure in strict mode
     */
    public function isRequired(string $propertyName): bool
    {
        return $this->requiredProperties[$propertyName] ?? false;
    }

    /**
     * Indicates whether the property is annotated to take its default in place of a null.
     *
     * @param string $propertyName Property to check.
     *
     * @return bool True when a null payload value yields the declared default
     */
    public function replacesNullWithDefault(string $propertyName): bool
    {
        return $this->replaceNullFlags[$propertyName] ?? false;
    }

    /**
     * Returns the property's declared default, including one carried by a promoted constructor
     * parameter rather than by the property itself.
     *
     * Evaluated on each call rather than cached as a value: a default may be an expression, and a
     * promoted `new X()` initializer must construct a fresh object PER USE - one per collection
     * element, not one shared across all of them - and must not run at all for a property the
     * payload supplies. Only the reflection LOOKUP is memoised, which is the per-element cost.
     *
     * @param string $propertyName Property to read.
     *
     * @return mixed Declared default, or null when there is none
     */
    public function defaultValue(string $propertyName): mixed
    {
        return ($this->defaultSources[$propertyName] ?? null)?->getDefaultValue();
    }
}
