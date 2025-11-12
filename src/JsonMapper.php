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
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * JsonMapper.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @template TEntity
 * @template TEntityCollection
 */
class JsonMapper
{
    private TypeResolver $typeResolver;

    private ClassResolver $classResolver;

    private ValueConverter $valueConverter;

    private CollectionFactory $collectionFactory;

    private CustomTypeRegistry $customTypeRegistry;

    public function __construct(
        private readonly PropertyInfoExtractorInterface $extractor,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ?PropertyNameConverterInterface $nameConverter = null,
        array $classMap = [],
    ) {
        $this->typeResolver        = new TypeResolver($extractor);
        $this->classResolver       = new ClassResolver($classMap);
        $this->customTypeRegistry  = new CustomTypeRegistry();
        $this->valueConverter      = new ValueConverter();
        $this->collectionFactory   = new CollectionFactory(
            $this->valueConverter,
            $this->classResolver,
            function (string $className, ?array $arguments): object {
                if ($arguments === null) {
                    return $this->makeInstance($className, null);
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
                function (mixed $value, string $resolvedClass, MappingContext $context): mixed {
                    return $this->map($value, $resolvedClass, null, $context);
                },
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
     * @template T
     *
     * @param class-string<T> $className
     */
    public function addCustomClassMapEntry(string $className, Closure $closure): JsonMapper
    {
        $this->classResolver->add($className, $closure);

        return $this;
    }

    /**
     * Maps the JSON to the specified class entity.
     *
     * @param mixed                                $json
     * @param class-string<TEntity>|null           $className
     * @param class-string<TEntityCollection>|null $collectionClassName
     *
     * @return mixed|TEntityCollection|TEntity|null
     *
     * @phpstan-return ($collectionClassName is class-string
     *                      ? TEntityCollection
     *                      : ($className is class-string ? TEntity : null|mixed))
     *
     * @throws InvalidArgumentException
     */
    public function map(
        mixed $json,
        ?string $className = null,
        ?string $collectionClassName = null,
        ?MappingContext $context = null,
    ) {
        $context ??= new MappingContext($json);

        if ($className === null) {
            return $json;
        }

        $className = $this->classResolver->resolve($className, $json, $context);

        if ($collectionClassName !== null) {
            $collectionClassName = $this->classResolver->resolve($collectionClassName, $json, $context);
        }

        $this->assertClassesExists($className, $collectionClassName);

        if ($this->isIterableWithArraysOrObjects($json)) {
            if ($collectionClassName !== null) {
                $collection = $this->collectionFactory->mapIterable($json, new ObjectType($className), $context);

                return $this->makeInstance($collectionClassName, $collection);
            }

            if ($this->isNumericIndexArray($json)) {
                return $this->collectionFactory->mapIterable($json, new ObjectType($className), $context);
            }
        }

        $entity = $this->makeInstance($className);

        if (!is_array($json) && !is_object($json)) {
            return $entity;
        }

        $properties          = $this->getProperties($className);
        $replacePropertyMap  = $this->buildReplacePropertyMap($className);

        foreach ($json as $propertyName => $propertyValue) {
            $normalizedProperty = $this->normalizePropertyName($propertyName, $replacePropertyMap);

            if (!is_string($normalizedProperty)) {
                continue;
            }

            if (!in_array($normalizedProperty, $properties, true)) {
                continue;
            }

            $context->withPathSegment($normalizedProperty, function (MappingContext $propertyContext) use (
                $className,
                $normalizedProperty,
                $propertyValue,
                $entity,
            ): void {
                $type  = $this->typeResolver->resolve($className, $normalizedProperty, $propertyContext);
                $value = $this->convertValue($propertyValue, $type, $propertyContext);

                if (
                    ($value === null)
                    && $this->isReplaceNullWithDefaultValueAnnotation($className, $normalizedProperty)
                ) {
                    $value = $this->getDefaultValue($className, $normalizedProperty);
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
     * @template T of object
     *
     * @param class-string<T> $className
     * @param mixed           ...$constructorArguments
     *
     * @return T
     */
    private function makeInstance(string $className, mixed ...$constructorArguments)
    {
        /** @var T $instance */
        $instance = new $className(...$constructorArguments);

        return $instance;
    }

    /**
     * Returns TRUE if the property contains an "ReplaceNullWithDefaultValue" annotation.
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
     * @return array<string, string>
     */
    private function buildReplacePropertyMap(string $className): array
    {
        $map = [];

        foreach ($this->extractClassAnnotations($className) as $annotation) {
            if (!($annotation instanceof ReplaceProperty)) {
                continue;
            }

            $map[$annotation->replaces] = $annotation->value;
        }

        return $map;
    }

    /**
     * Normalizes the property name using annotations and converters.
     */
    private function normalizePropertyName(string|int $propertyName, array $replacePropertyMap): string|int
    {
        $normalized = $propertyName;

        if (is_string($normalized) && array_key_exists($normalized, $replacePropertyMap)) {
            $normalized = $replacePropertyMap[$normalized];
        }

        if (is_string($normalized) && ($this->nameConverter instanceof PropertyNameConverterInterface)) {
            $normalized = $this->nameConverter->convert($normalized);
        }

        return $normalized;
    }

    /**
     * Returns the specified reflection property.
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
     */
    private function isNumericIndexArray(array|object $json): bool
    {
        foreach ($json as $propertyName => $propertyValue) {
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

        foreach ($json as $propertyValue) {
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
     * @return string[]
     */
    private function getProperties(string $className): array
    {
        return $this->extractor->getProperties($className) ?? [];
    }
}
