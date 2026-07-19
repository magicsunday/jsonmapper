<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Metadata;

use InvalidArgumentException;
use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;
use MagicSunday\JsonMapper\Attribute\ReplaceProperty;
use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

use function array_key_exists;
use function class_exists;
use function sprintf;

/**
 * Derives what a class's declaration fixes about how it is mapped, once per class.
 *
 * Everything here was previously re-derived through fresh reflection on every mapSingleObject()
 * call - which for a collection is once per ELEMENT. Fifty rows of one class asked fifty times for
 * an answer the declaration had already settled.
 *
 * The memo is per INSTANCE. It replaced a process-wide `static $cache` inside a `final readonly`
 * class, which was the library's only global state and outlived any test that touched it.
 *
 * Not routed through the PSR-6 pool that caches property types: this holds a ReflectionMethod,
 * which does not survive serialisation. Deriving it is cheap once it happens once per class, and
 * an in-memory memo is what the cost actually called for.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ClassMetadataFactory
{
    /**
     * @var array<class-string, ClassMetadata>
     */
    private array $metadata = [];

    public function __construct(private readonly PropertyInfoExtractorInterface $extractor)
    {
    }

    /**
     * Returns the metadata for the class, deriving it on first use.
     *
     * @param class-string $className Class whose declaration is inspected.
     *
     * @return ClassMetadata Everything the declaration fixes about mapping it
     */
    public function forClass(string $className): ClassMetadata
    {
        if (array_key_exists($className, $this->metadata)) {
            return $this->metadata[$className];
        }

        /** @var list<string> $properties */
        $properties    = $this->getProperties($className);
        $required      = [];
        $replaceNull   = [];
        $defaultSource = [];

        // The constructor's promoted parameters are collected once here rather than re-reflected
        // per property: the old per-property lookup built a fresh ReflectionClass for each one.
        $promoted = [];

        foreach (($this->constructorForHydration($className)?->getParameters() ?? []) as $parameter) {
            if ($parameter->isPromoted()) {
                $promoted[$parameter->getName()] = $parameter;
            }
        }

        foreach ($properties as $property) {
            $reflectionProperty = $this->getReflectionProperty($className, $property);
            $promotedParameter  = $promoted[$property] ?? null;

            $required[$property]    = $this->isRequiredProperty($reflectionProperty, $promotedParameter);
            $replaceNull[$property] = ($reflectionProperty instanceof ReflectionProperty)
                && $this->hasAttribute($reflectionProperty, ReplaceNullWithDefaultValue::class);
            $defaultSource[$property] = $this->defaultSourceOf($reflectionProperty, $promotedParameter);
        }

        // Assigned only after every derivation succeeded: a misdeclared class throws from one of
        // them, and memoising a half-built entry would hand the next call a shape nobody derived.
        return $this->metadata[$className] = new ClassMetadata(
            $properties,
            $this->buildReplacePropertyMap($className),
            $this->unknownPropertyCollector($className),
            $this->constructorForHydration($className),
            $required,
            $replaceNull,
            $defaultSource,
        );
    }

    /**
     * Get all public properties for the specified class.
     *
     * @param class-string $className Fully qualified class whose property names should be extracted.
     *
     * @return string[] List of property names exposed by the configured extractor.
     */
    private function getProperties(string $className): array
    {
        return $this->extractor->getProperties($className) ?? [];
    }

    /**
     * Builds the mapping of legacy property names to their replacements declared via attributes.
     *
     * @param class-string $className Fully qualified class inspected for ReplaceProperty attributes.
     *
     * @return array<string, string> Map of original property names to their replacement names.
     */
    private function buildReplacePropertyMap(string $className): array
    {
        $reflectionClass = $this->getReflectionClass($className);

        if (!$reflectionClass instanceof ReflectionClass) {
            return [];
        }

        $map        = [];
        $attributes = $reflectionClass->getAttributes(ReplaceProperty::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            /** @var ReplaceProperty $instance */
            $instance                 = $attribute->newInstance();
            $map[$instance->replaces] = $instance->value;
        }

        return $map;
    }

    /**
     * Returns the name of the property nominated as the unknown-key collector via the
     * {@see UnknownPropertyCollector} attribute, or NULL when the class declares none.
     *
     * @param class-string $className Fully qualified class inspected for a collector property.
     *
     * @return string|null The collector property name, or NULL.
     *
     * @throws InvalidArgumentException When the class marks more than one collector, or the marked
     *                                  property is static or not array-typed (the raw collected map
     *                                  is assigned to a per-instance array property without
     *                                  conversion).
     */
    private function unknownPropertyCollector(string $className): ?string
    {
        // The collector for a class is fixed by its declaration, so memoize it per class: the lookup
        // runs on every mapSingleObject() call and would otherwise re-reflect for every element of a
        // large collection. A misdeclared class throws before it is ever cached.
        $reflectionClass = $this->getReflectionClass($className);

        if (!$reflectionClass instanceof ReflectionClass) {
            return null;
        }

        $collector = null;

        foreach ($reflectionClass->getProperties() as $property) {
            if (!$this->hasAttribute($property, UnknownPropertyCollector::class)) {
                continue;
            }

            // A static property is shared, not a per-instance sink, and cannot be hydrated as one;
            // reject the declaration rather than silently ignoring the marker.
            if ($property->isStatic()) {
                throw new InvalidArgumentException(sprintf(
                    'The property "%s::$%s" marked with #[UnknownPropertyCollector] must not be static.',
                    $className,
                    $property->getName(),
                ));
            }

            // A class nominates at most one collector; a second marked property is a declaration
            // error, so fail fast rather than silently ignoring it.
            if ($collector !== null) {
                throw new InvalidArgumentException(sprintf(
                    'The class "%s" must not mark more than one property with #[UnknownPropertyCollector].',
                    $className,
                ));
            }

            // The raw collected map is assigned without conversion, so a non-array collector would
            // fail late as a native TypeError; reject the declaration up front with a clear message.
            if (!$this->isArrayType($property->getType())) {
                throw new InvalidArgumentException(sprintf(
                    'The property "%s::$%s" marked with #[UnknownPropertyCollector] must be array-typed.',
                    $className,
                    $property->getName(),
                ));
            }

            $collector = $property->getName();
        }

        return $collector;
    }

    /**
     * Returns the constructor a class must be hydrated through, or NULL when the class can be
     * populated by property assignment after an argument-less instantiation.
     *
     * A class must be built through its constructor when the constructor has a promoted parameter
     * (the immutable `final readonly` shape, whose properties cannot be written after
     * construction) or any required parameter (which an argument-less instantiation could not
     * satisfy). Plain mutable classes keep the property-assignment path unchanged.
     *
     * @param class-string $className Fully qualified class name to inspect.
     *
     * @return ReflectionMethod|null The constructor to hydrate through, or NULL.
     */
    private function constructorForHydration(string $className): ?ReflectionMethod
    {
        $constructor = (new ReflectionClass($className))->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            // Promoted (immutable shape), required (an argument-less call could not satisfy it),
            // or variadic (its values must be spread through the constructor) parameters all
            // force construction through the constructor.
            if ($parameter->isPromoted() || !$parameter->isOptional() || $parameter->isVariadic()) {
                return $constructor;
            }
        }

        return null;
    }

    /**
     * Determines whether the given property must be present on the input data.
     *
     * @param ReflectionProperty|null  $reflectionProperty Already-resolved property handle, when there is one.
     * @param ReflectionParameter|null $promotedParameter  The matching promoted constructor parameter, when there is one.
     *
     * @return bool True when the property is mandatory and missing values must be reported.
     */
    private function isRequiredProperty(
        ?ReflectionProperty $reflectionProperty,
        ?ReflectionParameter $promotedParameter,
    ): bool {
        if (!$reflectionProperty instanceof ReflectionProperty) {
            return false;
        }

        if ($reflectionProperty->hasDefaultValue()) {
            return false;
        }

        // A promoted property's default lives on the constructor parameter, so a promoted
        // parameter with a default is not required even though the property has none.
        if (($promotedParameter instanceof ReflectionParameter) && $promotedParameter->isDefaultValueAvailable()) {
            return false;
        }

        $type = $reflectionProperty->getType();

        if ($type instanceof ReflectionNamedType) {
            return !$type->allowsNull();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($innerType instanceof ReflectionNamedType && $innerType->allowsNull()) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Checks whether the given property is marked with the specified attribute class.
     *
     * @param ReflectionProperty $property       Property reflection inspected for attributes.
     * @param class-string       $attributeClass Attribute class name to look for on the property.
     *
     * @return bool True when at least one matching attribute is present.
     */
    private function hasAttribute(ReflectionProperty $property, string $attributeClass): bool
    {
        return $property->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /**
     * Determines whether the given declared type is a valid collector type. A plain `array` and a
     * nullable collector — written either `?array` or `array|null` — both reflect as a nullable
     * {@see ReflectionNamedType} named `array`, so the named check is exhaustive: any genuine
     * multi-type union (e.g. `array|int`), an intersection type or an untyped property is a
     * {@see ReflectionUnionType}/{@see ReflectionIntersectionType}/`null` and is rejected, since the
     * collector holds an array map and must not permit a non-array value.
     *
     * @param ReflectionType|null $type The property's declared type, or NULL when untyped.
     *
     * @return bool True when the type only ever holds an array (or null).
     */
    private function isArrayType(?ReflectionType $type): bool
    {
        return ($type instanceof ReflectionNamedType) && ($type->getName() === 'array');
    }

    /**
     * Returns the reflection handle carrying the property's declared default, without evaluating it.
     *
     * @param ReflectionProperty|null  $reflectionProperty Already-resolved property handle, when there is one.
     * @param ReflectionParameter|null $promotedParameter  The matching promoted constructor parameter, when there is one.
     *
     * @return ReflectionProperty|ReflectionParameter|null Handle carrying the default, or null when there is none
     */
    private function defaultSourceOf(
        ?ReflectionProperty $reflectionProperty,
        ?ReflectionParameter $promotedParameter,
    ): ReflectionProperty|ReflectionParameter|null {
        if (!$reflectionProperty instanceof ReflectionProperty) {
            return null;
        }

        if ($reflectionProperty->hasDefaultValue()) {
            return $reflectionProperty;
        }

        // A promoted property carries no property-level default; its default lives on the
        // constructor parameter of the same name. The HANDLE is returned, not its value: the
        // value may be an expression that must be evaluated per use, not once per class.
        if (($promotedParameter instanceof ReflectionParameter) && $promotedParameter->isDefaultValueAvailable()) {
            return $promotedParameter;
        }

        return null;
    }

    /**
     * Returns the specified reflection property, or null when it does not exist.
     *
     * @param class-string $className    Class declaring the property.
     * @param string       $propertyName Property to reflect.
     *
     * @return ReflectionProperty|null Reflection handle, or null when absent
     */
    private function getReflectionProperty(string $className, string $propertyName): ?ReflectionProperty
    {
        try {
            return new ReflectionProperty($className, $propertyName);
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Returns the specified reflection class, or null when it does not exist.
     *
     * @param class-string $className Class to reflect.
     *
     * @return ReflectionClass<object>|null Reflection handle, or null when the class is unknown
     */
    private function getReflectionClass(string $className): ?ReflectionClass
    {
        if (!class_exists($className)) {
            return null;
        }

        return new ReflectionClass($className);
    }
}
