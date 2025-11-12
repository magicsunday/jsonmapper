<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday;

use Closure;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use InvalidArgumentException;
use MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue;
use MagicSunday\JsonMapper\Annotation\ReplaceProperty;
use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Type\TypeResolver;
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use MagicSunday\JsonMapper\Value\Strategy\BuiltinValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\CollectionValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\CustomTypeValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\NullValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\ObjectValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\PassthroughValueConversionStrategy;
use MagicSunday\JsonMapper\Value\ValueConverter;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Traversable;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function iterator_to_array;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * JsonMapper.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapper
{
    private TypeResolver $typeResolver;

    private ClassResolver $classResolver;

    private ValueConverter $valueConverter;

    private CollectionFactory $collectionFactory;

    private CustomTypeRegistry $customTypeRegistry;

    /**
     * @param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap
     * @param CacheItemPoolInterface|null                                                                               $typeCache
     *
     * @phpstan-param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap
     */
    public function __construct(
        private readonly PropertyInfoExtractorInterface $extractor,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ?PropertyNameConverterInterface $nameConverter = null,
        array $classMap = [],
        ?CacheItemPoolInterface $typeCache = null,
    ) {
        $this->typeResolver       = new TypeResolver($extractor, $typeCache);
        $this->classResolver      = new ClassResolver($classMap);
        $this->customTypeRegistry = new CustomTypeRegistry();
        $this->valueConverter     = new ValueConverter();
        $this->collectionFactory  = new CollectionFactory(
            $this->valueConverter,
            $this->classResolver,
            function (string $className, ?array $arguments): object {
                if ($arguments === null) {
                    return $this->makeInstance($className);
                }

                return $this->makeInstance($className, $arguments);
            },
        );

        $this->valueConverter->addStrategy(new NullValueConversionStrategy());
        $this->valueConverter->addStrategy(new CollectionValueConversionStrategy($this->collectionFactory));
        $this->valueConverter->addStrategy(new CustomTypeValueConversionStrategy($this->customTypeRegistry));
        $this->valueConverter->addStrategy(
            new ObjectValueConversionStrategy(
                $this->classResolver,
                fn (mixed $value, string $resolvedClass, MappingContext $context): mixed => $this->map($value, $resolvedClass, null, $context),
            ),
        );
        $this->valueConverter->addStrategy(new BuiltinValueConversionStrategy());
        $this->valueConverter->addStrategy(new PassthroughValueConversionStrategy());
    }

    /**
     * Add a custom type.
     */
    public function addType(string $type, Closure $closure): JsonMapper
    {
        $this->customTypeRegistry->register($type, $closure);

        return $this;
    }

    /**
     * Add a custom class map entry.
     *
     * @param class-string                                                            $className
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure
     *
     * @phpstan-param class-string $className
     * @phpstan-param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure
     */
    public function addCustomClassMapEntry(string $className, Closure $closure): JsonMapper
    {
        $this->classResolver->add($className, $closure);

        return $this;
    }

    /**
     * Maps the JSON to the specified class entity.
     *
     * @param mixed             $json
     * @param class-string|null $className
     * @param class-string|null $collectionClassName
     *
     * @throws InvalidArgumentException
     */
    public function map(
        mixed $json,
        ?string $className = null,
        ?string $collectionClassName = null,
        ?MappingContext $context = null,
    ): mixed {
        $context ??= new MappingContext($json);

        if ($className === null) {
            return $json;
        }

        /** @var class-string $resolvedClassName */
        $resolvedClassName = $this->classResolver->resolve($className, $json, $context);

        /** @var class-string|null $resolvedCollectionClassName */
        $resolvedCollectionClassName = $collectionClassName === null
            ? null
            : $this->classResolver->resolve($collectionClassName, $json, $context);

        $this->assertClassesExists($resolvedClassName, $resolvedCollectionClassName);

        if (!is_array($json) && !is_object($json)) {
            return $this->makeInstance($resolvedClassName);
        }

        if (
            ($resolvedCollectionClassName !== null)
            && $this->isIterableWithArraysOrObjects($json)
        ) {
            $collection = $this->collectionFactory->mapIterable($json, new ObjectType($resolvedClassName), $context);

            return $this->makeInstance($resolvedCollectionClassName, $collection);
        }

        if (
            $this->isIterableWithArraysOrObjects($json)
            && $this->isNumericIndexArray($json)
        ) {
            return $this->collectionFactory->mapIterable($json, new ObjectType($resolvedClassName), $context);
        }

        $entity = $this->makeInstance($resolvedClassName);
        $source = $this->toIterableArray($json);

        $properties         = $this->getProperties($resolvedClassName);
        $replacePropertyMap = $this->buildReplacePropertyMap($resolvedClassName);

        foreach ($source as $propertyName => $propertyValue) {
            $normalizedProperty = $this->normalizePropertyName($propertyName, $replacePropertyMap);

            if (!is_string($normalizedProperty)) {
                continue;
            }

            if (!in_array($normalizedProperty, $properties, true)) {
                continue;
            }

            $context->withPathSegment($normalizedProperty, function (MappingContext $propertyContext) use (
                $resolvedClassName,
                $normalizedProperty,
                $propertyValue,
                $entity,
            ): void {
                $type  = $this->typeResolver->resolve($resolvedClassName, $normalizedProperty);
                $value = $this->convertValue($propertyValue, $type, $propertyContext);

                if (
                    ($value === null)
                    && $this->isReplaceNullWithDefaultValueAnnotation($resolvedClassName, $normalizedProperty)
                ) {
                    $value = $this->getDefaultValue($resolvedClassName, $normalizedProperty);
                }

                $this->setProperty($entity, $normalizedProperty, $value);
            });
        }

        return $entity;
    }

    /**
     * Converts the provided JSON value using the registered strategies.
     */
    private function convertValue(mixed $json, Type $type, MappingContext $context): mixed
    {
        if ($type instanceof CollectionType) {
            return $this->collectionFactory->fromCollectionType($type, $json, $context);
        }

        return $this->valueConverter->convert($json, $type, $context);
    }

    /**
     * Creates an instance of the given class name.
     *
     * @param string $className
     */
    private function makeInstance(string $className, mixed ...$constructorArguments): object
    {
        return new $className(...$constructorArguments);
    }

    /**
     * Returns TRUE if the property contains an "ReplaceNullWithDefaultValue" annotation.
     */
    /**
     * Returns TRUE if the property contains an "ReplaceNullWithDefaultValue" annotation.
     *
     * @param class-string $className
     */
    private function isReplaceNullWithDefaultValueAnnotation(string $className, string $propertyName): bool
    {
        return $this->hasPropertyAnnotation(
            $className,
            $propertyName,
            ReplaceNullWithDefaultValue::class,
        );
    }

    /**
     * Builds the map of properties replaced by the annotation.
     *
     * @param class-string $className
     *
     * @return array<string, string>
     */
    private function buildReplacePropertyMap(string $className): array
    {
        $map = [];

        foreach ($this->extractClassAnnotations($className) as $annotation) {
            if (!($annotation instanceof ReplaceProperty)) {
                continue;
            }

            if (!is_string($annotation->value)) {
                continue;
            }

            $map[$annotation->replaces] = $annotation->value;
        }

        return $map;
    }

    /**
     * Normalizes the property name using annotations and converters.
     *
     * @param array<string, string> $replacePropertyMap
     */
    private function normalizePropertyName(string|int $propertyName, array $replacePropertyMap): string|int
    {
        $normalized = $propertyName;

        if (is_string($normalized) && array_key_exists($normalized, $replacePropertyMap)) {
            $normalized = $replacePropertyMap[$normalized];
        }

        if (is_string($normalized) && ($this->nameConverter instanceof PropertyNameConverterInterface)) {
            return $this->nameConverter->convert($normalized);
        }

        return $normalized;
    }

    /**
     * Converts arrays and objects into a plain array structure.
     *
     * @param array<array-key, mixed>|object $json
     *
     * @return array<array-key, mixed>
     */
    private function toIterableArray(array|object $json): array
    {
        if ($json instanceof Traversable) {
            return iterator_to_array($json);
        }

        if (is_object($json)) {
            return get_object_vars($json);
        }

        return $json;
    }

    /**
     * Returns the specified reflection property.
     *
     * @param class-string $className
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
     * Returns the specified reflection class.
     *
     * @param class-string $className
     */
    private function getReflectionClass(string $className): ?ReflectionClass
    {
        if (!class_exists($className)) {
            return null;
        }

        return new ReflectionClass($className);
    }

    /**
     * Extracts possible property annotations.
     *
     * @param class-string $className
     *
     * @return Annotation[]|object[]
     */
    private function extractPropertyAnnotations(string $className, string $propertyName): array
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if ($reflectionProperty instanceof ReflectionProperty) {
            return (new AnnotationReader())
                ->getPropertyAnnotations($reflectionProperty);
        }

        return [];
    }

    /**
     * Extracts possible class annotations.
     *
     * @param class-string $className
     *
     * @return Annotation[]|object[]
     */
    private function extractClassAnnotations(string $className): array
    {
        $reflectionClass = $this->getReflectionClass($className);

        if ($reflectionClass instanceof ReflectionClass) {
            return (new AnnotationReader())
                ->getClassAnnotations($reflectionClass);
        }

        return [];
    }

    /**
     * Returns TRUE if the property has the given annotation.
     *
     * @param class-string $className
     * @param class-string $annotationName
     */
    private function hasPropertyAnnotation(string $className, string $propertyName, string $annotationName): bool
    {
        $annotations = $this->extractPropertyAnnotations($className, $propertyName);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the default value of a property.
     *
     * @param class-string $className
     */
    private function getDefaultValue(string $className, string $propertyName): mixed
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!($reflectionProperty instanceof ReflectionProperty)) {
            return null;
        }

        return $reflectionProperty->getDefaultValue();
    }

    /**
     * Returns TRUE if the given JSON contains integer property keys.
     *
     * @param array<array-key, mixed>|object $json
     */
    private function isNumericIndexArray(array|object $json): bool
    {
        foreach (array_keys($this->toIterableArray($json)) as $propertyName) {
            if (is_int($propertyName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns TRUE if the given JSON is a plain array or object.
     */
    private function isIterableWithArraysOrObjects(mixed $json): bool
    {
        if (!is_array($json) && !is_object($json)) {
            return false;
        }

        $values = is_array($json) ? $json : $this->toIterableArray($json);

        foreach ($values as $propertyValue) {
            if (is_array($propertyValue)) {
                continue;
            }

            if (is_object($propertyValue)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Assert that the given classes exist.
     */
    private function assertClassesExists(string $className, ?string $collectionClassName = null): void
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(sprintf('Class [%s] does not exist', $className));
        }

        if ($collectionClassName === null) {
            return;
        }

        if (class_exists($collectionClassName)) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Class [%s] does not exist', $collectionClassName));
    }

    /**
     * Sets a property value.
     */
    private function setProperty(object $entity, string $name, mixed $value): void
    {
        if (is_array($value)) {
            $methodName = 'set' . ucfirst($name);

            if (method_exists($entity, $methodName)) {
                $method     = new ReflectionMethod($entity, $methodName);
                $parameters = $method->getParameters();

                if ((count($parameters) === 1) && $parameters[0]->isVariadic()) {
                    $callable = [$entity, $methodName];

                    if (is_callable($callable)) {
                        call_user_func_array($callable, $value);
                    }

                    return;
                }
            }
        }

        $this->accessor->setValue($entity, $name, $value);
    }

    /**
     * Get all public properties for the specified class.
     *
     * @param class-string $className
     *
     * @return string[]
     */
    private function getProperties(string $className): array
    {
        return $this->extractor->getProperties($className) ?? [];
    }
}
