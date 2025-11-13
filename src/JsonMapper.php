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
use InvalidArgumentException;
use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;
use MagicSunday\JsonMapper\Attribute\ReplaceProperty;
use MagicSunday\JsonMapper\Collection\CollectionDocBlockTypeResolver;
use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Collection\CollectionFactoryInterface;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\JsonMapper\Report\MappingReport;
use MagicSunday\JsonMapper\Report\MappingResult;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Type\TypeResolver;
use MagicSunday\JsonMapper\Value\ClosureTypeHandler;
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use MagicSunday\JsonMapper\Value\Strategy\BuiltinValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\CollectionValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\CustomTypeValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\DateTimeValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\EnumValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\NullValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\ObjectValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\PassthroughValueConversionStrategy;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use MagicSunday\JsonMapper\Value\ValueConverter;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Traversable;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_unique;
use function array_values;
use function call_user_func_array;
use function count;
use function get_debug_type;
use function get_object_vars;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function iterator_to_array;
use function method_exists;
use function sprintf;
use function trim;
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

    /**
     * @var CollectionFactoryInterface<array-key, mixed>
     */
    private CollectionFactoryInterface $collectionFactory;

    private CollectionDocBlockTypeResolver $collectionDocBlockTypeResolver;

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
        private JsonMapperConfiguration $config = new JsonMapperConfiguration(),
    ) {
        $this->typeResolver                   = new TypeResolver($extractor, $typeCache);
        $this->classResolver                  = new ClassResolver($classMap);
        $this->customTypeRegistry             = new CustomTypeRegistry();
        $this->collectionDocBlockTypeResolver = new CollectionDocBlockTypeResolver();
        $this->valueConverter                 = new ValueConverter();
        $this->collectionFactory              = new CollectionFactory(
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
        $this->valueConverter->addStrategy(new DateTimeValueConversionStrategy());
        $this->valueConverter->addStrategy(new EnumValueConversionStrategy());
        $this->valueConverter->addStrategy(
            new ObjectValueConversionStrategy(
                $this->classResolver,
                function (mixed $value, string $resolvedClass, MappingContext $context): mixed {
                    $configuration = JsonMapperConfiguration::fromContext($context);

                    return $this->map($value, $resolvedClass, null, $context, $configuration);
                },
            ),
        );
        $this->valueConverter->addStrategy(new BuiltinValueConversionStrategy());
        $this->valueConverter->addStrategy(new PassthroughValueConversionStrategy());
    }

    /**
     * Registers a custom type handler.
     */
    public function addTypeHandler(TypeHandlerInterface $handler): JsonMapper
    {
        $this->customTypeRegistry->registerHandler($handler);

        return $this;
    }

    /**
     * Registers a custom type using a closure-based handler.
     *
     * @deprecated Use addTypeHandler() with a TypeHandlerInterface implementation instead.
     */
    public function addType(string $type, Closure $closure): JsonMapper
    {
        $this->customTypeRegistry->registerHandler(new ClosureTypeHandler($type, $closure));

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
     * @param mixed                        $json
     * @param class-string|null            $className
     * @param class-string|null            $collectionClassName
     * @param MappingContext|null          $context
     * @param JsonMapperConfiguration|null $configuration
     *
     * @throws InvalidArgumentException
     */
    public function map(
        mixed $json,
        ?string $className = null,
        ?string $collectionClassName = null,
        ?MappingContext $context = null,
        ?JsonMapperConfiguration $configuration = null,
    ): mixed {
        if (!$context instanceof MappingContext) {
            $configuration ??= $this->createDefaultConfiguration();
            $context = new MappingContext($json, $configuration->toOptions());
        } elseif (!$configuration instanceof JsonMapperConfiguration) {
            $configuration = JsonMapperConfiguration::fromContext($context);
        } else {
            $context->replaceOptions($configuration->toOptions());
        }

        $resolvedClassName = $className === null
            ? null
            : $this->classResolver->resolve($className, $json, $context);

        $resolvedCollectionClassName = $collectionClassName === null
            ? null
            : $this->classResolver->resolve($collectionClassName, $json, $context);

        $this->assertClassesExists($resolvedClassName, $resolvedCollectionClassName);

        /** @var Type|null $collectionValueType */
        $collectionValueType = null;

        if ($resolvedCollectionClassName !== null) {
            if ($resolvedClassName !== null) {
                $collectionValueType = new ObjectType($resolvedClassName);
            } else {
                $docBlockCollectionType = $this->collectionDocBlockTypeResolver->resolve($resolvedCollectionClassName);

                if (!$docBlockCollectionType instanceof CollectionType) {
                    throw new InvalidArgumentException(sprintf(
                        'Unable to resolve the element type for collection [%s]. Define an "@extends" annotation such as "@extends %s<YourClass>".',
                        $resolvedCollectionClassName,
                        $resolvedCollectionClassName,
                    ));
                }

                $collectionValueType = $docBlockCollectionType->getCollectionValueType();

                if ($collectionValueType instanceof TemplateType) {
                    throw new InvalidArgumentException(sprintf(
                        'Unable to resolve the element type for collection [%s]. Please provide a concrete class in the "@extends" annotation.',
                        $resolvedCollectionClassName,
                    ));
                }
            }
        }

        $isGenericCollectionMapping = $resolvedClassName === null && $collectionValueType !== null;

        if ($isGenericCollectionMapping) {
            if ($resolvedCollectionClassName === null) {
                throw new InvalidArgumentException('A collection class name must be provided when mapping without an element class.');
            }

            $collection = $this->collectionFactory->mapIterable($json, $collectionValueType, $context);

            return $this->makeInstance($resolvedCollectionClassName, $collection);
        }

        if ($resolvedClassName === null) {
            return $json;
        }

        if (!is_array($json) && !is_object($json)) {
            return $this->makeInstance($resolvedClassName);
        }

        if (
            ($resolvedCollectionClassName !== null)
            && $this->isIterableWithArraysOrObjects($json)
        ) {
            $collection = $this->collectionFactory->mapIterable($json, $collectionValueType ?? new ObjectType($resolvedClassName), $context);

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
        $mappedProperties   = [];

        foreach ($source as $propertyName => $propertyValue) {
            $normalizedProperty = $this->normalizePropertyName($propertyName, $replacePropertyMap);
            $pathSegment        = is_string($normalizedProperty) ? $normalizedProperty : (string) $propertyName;

            $context->withPathSegment($pathSegment, function (MappingContext $propertyContext) use (
                $resolvedClassName,
                $normalizedProperty,
                $propertyValue,
                $entity,
                &$mappedProperties,
                $properties,
                $configuration,
            ): void {
                if (!is_string($normalizedProperty)) {
                    return;
                }

                if (!in_array($normalizedProperty, $properties, true)) {
                    if ($configuration->shouldIgnoreUnknownProperties()) {
                        return;
                    }

                    $this->handleMappingException(
                        new UnknownPropertyException($propertyContext->getPath(), $normalizedProperty, $resolvedClassName),
                        $propertyContext,
                        $configuration,
                    );

                    return;
                }

                $mappedProperties[] = $normalizedProperty;

                $type = $this->typeResolver->resolve($resolvedClassName, $normalizedProperty);

                try {
                    $value = $this->convertValue($propertyValue, $type, $propertyContext);
                } catch (MappingException $exception) {
                    $this->handleMappingException($exception, $propertyContext, $configuration);

                    return;
                }

                if (
                    ($value === null)
                    && $this->isReplaceNullWithDefaultValueAnnotation($resolvedClassName, $normalizedProperty)
                ) {
                    $value = $this->getDefaultValue($resolvedClassName, $normalizedProperty);
                }

                try {
                    $this->setProperty($entity, $normalizedProperty, $value, $propertyContext);
                } catch (ReadonlyPropertyException $exception) {
                    $this->handleMappingException($exception, $propertyContext, $configuration);
                }
            });
        }

        if ($configuration->isStrictMode()) {
            foreach ($this->determineMissingProperties($resolvedClassName, $properties, $mappedProperties) as $missingProperty) {
                $context->withPathSegment($missingProperty, function (MappingContext $propertyContext) use (
                    $resolvedClassName,
                    $missingProperty,
                    $configuration,
                ): void {
                    $this->handleMappingException(
                        new MissingPropertyException($propertyContext->getPath(), $missingProperty, $resolvedClassName),
                        $propertyContext,
                        $configuration,
                    );
                });
            }
        }

        return $entity;
    }

    /**
     * Maps the JSON structure and returns a detailed mapping report.
     *
     * @param mixed                        $json
     * @param class-string|null            $className
     * @param class-string|null            $collectionClassName
     * @param JsonMapperConfiguration|null $configuration
     */
    public function mapWithReport(
        mixed $json,
        ?string $className = null,
        ?string $collectionClassName = null,
        ?JsonMapperConfiguration $configuration = null,
    ): MappingResult {
        $configuration = ($configuration ?? $this->createDefaultConfiguration())->withErrorCollection(true);
        $context       = new MappingContext($json, $configuration->toOptions());
        $value         = $this->map($json, $className, $collectionClassName, $context, $configuration);

        return new MappingResult($value, new MappingReport($context->getErrorRecords()));
    }

    private function createDefaultConfiguration(): JsonMapperConfiguration
    {
        return clone $this->config;
    }

    /**
     * @param class-string              $className
     * @param array<int|string, string> $declaredProperties
     * @param list<string>              $mappedProperties
     *
     * @return list<string>
     */
    private function determineMissingProperties(string $className, array $declaredProperties, array $mappedProperties): array
    {
        $used = array_values(array_unique($mappedProperties));

        return array_values(array_filter(
            array_diff($declaredProperties, $used),
            fn (string $property): bool => $this->isRequiredProperty($className, $property),
        ));
    }

    /**
     * @param class-string $className
     */
    private function isRequiredProperty(string $className, string $propertyName): bool
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!($reflectionProperty instanceof ReflectionProperty)) {
            return false;
        }

        if ($reflectionProperty->hasDefaultValue()) {
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

    private function handleMappingException(MappingException $exception, MappingContext $context, JsonMapperConfiguration $configuration): void
    {
        $context->recordException($exception);

        if ($configuration->isStrictMode()) {
            throw $exception;
        }
    }

    /**
     * Converts the provided JSON value using the registered strategies.
     */
    private function convertValue(mixed $json, Type $type, MappingContext $context): mixed
    {
        if (
            is_string($json)
            && ($json === '' || trim($json) === '')
            && (bool) $context->getOption(MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL, false)
        ) {
            $json = null;
        }

        if ($type instanceof CollectionType) {
            return $this->collectionFactory->fromCollectionType($type, $json, $context);
        }

        if ($type instanceof UnionType) {
            return $this->convertUnionValue($json, $type, $context);
        }

        if ($this->isNullType($type)) {
            return null;
        }

        return $this->valueConverter->convert($json, $type, $context);
    }

    /**
     * Converts the value according to the provided union type.
     *
     * @param UnionType<Type> $type
     */
    private function convertUnionValue(mixed $json, UnionType $type, MappingContext $context): mixed
    {
        if ($json === null && $this->unionAllowsNull($type)) {
            return null;
        }

        $lastException = null;

        foreach ($type->getTypes() as $candidate) {
            if ($this->isNullType($candidate) && $json !== null) {
                continue;
            }

            $errorCount = $context->getErrorCount();

            try {
                $converted = $this->convertValue($json, $candidate, $context);
            } catch (MappingException $exception) {
                $context->trimErrors($errorCount);
                $lastException = $exception;

                continue;
            }

            if ($context->getErrorCount() > $errorCount) {
                $context->trimErrors($errorCount);

                $lastException = new TypeMismatchException(
                    $context->getPath(),
                    $this->describeType($candidate),
                    get_debug_type($json),
                );

                continue;
            }

            return $converted;
        }

        if ($lastException instanceof MappingException) {
            throw $lastException;
        }

        $exception = new TypeMismatchException(
            $context->getPath(),
            $this->describeUnionType($type),
            get_debug_type($json),
        );

        $context->recordException($exception);

        if ($context->isStrictMode()) {
            throw $exception;
        }

        return $json;
    }

    /**
     * Returns a string representation of the provided type.
     */
    private function describeType(Type $type): string
    {
        if ($type instanceof BuiltinType) {
            return $type->getTypeIdentifier()->value . ($type->isNullable() ? '|null' : '');
        }

        if ($type instanceof ObjectType) {
            return $type->getClassName();
        }

        if ($type instanceof CollectionType) {
            return 'array';
        }

        if ($this->isNullType($type)) {
            return 'null';
        }

        if ($type instanceof UnionType) {
            return $this->describeUnionType($type);
        }

        return $type::class;
    }

    /**
     * Returns a textual representation of the union type.
     *
     * @param UnionType<Type> $type
     */
    private function describeUnionType(UnionType $type): string
    {
        $parts = [];

        foreach ($type->getTypes() as $candidate) {
            $parts[] = $this->describeType($candidate);
        }

        return implode('|', $parts);
    }

    /**
     * @param UnionType<Type> $type
     */
    private function unionAllowsNull(UnionType $type): bool
    {
        foreach ($type->getTypes() as $candidate) {
            if ($this->isNullType($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isNullType(Type $type): bool
    {
        return $type instanceof BuiltinType && $type->getTypeIdentifier() === TypeIdentifier::NULL;
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
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!($reflectionProperty instanceof ReflectionProperty)) {
            return false;
        }

        return $this->hasAttribute($reflectionProperty, ReplaceNullWithDefaultValue::class);
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
        $reflectionClass = $this->getReflectionClass($className);

        if (!($reflectionClass instanceof ReflectionClass)) {
            return [];
        }

        $map = [];

        foreach ($reflectionClass->getAttributes(ReplaceProperty::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var ReplaceProperty $instance */
            $instance                 = $attribute->newInstance();
            $map[$instance->replaces] = $instance->value;
        }

        return $map;
    }

    /**
     * @param class-string $attributeClass
     */
    private function hasAttribute(ReflectionProperty $property, string $attributeClass): bool
    {
        return $property->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF) !== [];
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
     *
     * @return ReflectionClass<object>|null
     */
    private function getReflectionClass(string $className): ?ReflectionClass
    {
        if (!class_exists($className)) {
            return null;
        }

        return new ReflectionClass($className);
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
    private function assertClassesExists(?string $className, ?string $collectionClassName = null): void
    {
        if ($className !== null && !class_exists($className)) {
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
    private function setProperty(object $entity, string $name, mixed $value, MappingContext $context): void
    {
        $reflectionProperty = $this->getReflectionProperty($entity::class, $name);

        if ($reflectionProperty instanceof ReflectionProperty && $reflectionProperty->isReadOnly()) {
            throw new ReadonlyPropertyException($context->getPath(), $name, $entity::class);
        }

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
