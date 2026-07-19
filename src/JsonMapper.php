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
use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;
use MagicSunday\JsonMapper\Collection\CollectionDocBlockTypeResolver;
use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Collection\CollectionFactoryInterface;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
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
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
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
use function array_replace;
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
final readonly class JsonMapper
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
     * Creates a mapper that converts JSON data into PHP objects using the configured Symfony services.
     *
     * @param PropertyInfoExtractorInterface                                                                            $extractor     Extractor that provides type information for mapped properties.
     * @param PropertyAccessorInterface                                                                                 $accessor      Property accessor used to write values onto target objects.
     * @param PropertyNameConverterInterface|null                                                                       $nameConverter Optional converter to normalise incoming property names.
     * @param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap      Map of base classes to resolvers that determine the concrete class to instantiate.
     * @param CacheItemPoolInterface|null                                                                               $typeCache     Optional cache for resolved type information.
     * @param JsonMapperConfiguration                                                                                   $config        Default mapper configuration cloned for new mapping contexts.
     */
    public function __construct(
        private PropertyInfoExtractorInterface $extractor,
        private PropertyAccessorInterface $accessor,
        private ?PropertyNameConverterInterface $nameConverter = null,
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

                    return $this->map(
                        $value,
                        $resolvedClass,
                        null,
                        $context,
                        $configuration
                    );
                },
            ),
        );
        $this->valueConverter->addStrategy(new BuiltinValueConversionStrategy());
        $this->valueConverter->addStrategy(new PassthroughValueConversionStrategy());
    }

    /**
     * Creates a mapper with sensible default Symfony services.
     *
     * @param PropertyNameConverterInterface|null                                                                       $nameConverter Optional converter to normalise incoming property names.
     * @param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap      Optional class map forwarded to the mapper constructor.
     * @param CacheItemPoolInterface|null                                                                               $typeCache     Optional cache for resolved type information.
     * @param JsonMapperConfiguration|null                                                                              $config        Default mapper configuration cloned for new mapping contexts.
     */
    public static function createWithDefaults(
        ?PropertyNameConverterInterface $nameConverter = null,
        array $classMap = [],
        ?CacheItemPoolInterface $typeCache = null,
        ?JsonMapperConfiguration $config = null,
    ): self {
        $extractor = new PropertyInfoExtractor(
            [new ReflectionExtractor()],
            [new PhpDocExtractor()],
        );

        return new self(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            $nameConverter,
            $classMap,
            $typeCache,
            $config ?? new JsonMapperConfiguration(),
        );
    }

    /**
     * Registers a custom type handler.
     *
     * @param TypeHandlerInterface $handler Type handler implementation to register with the mapper.
     *
     * @return JsonMapper Returns the mapper instance for fluent configuration.
     */
    public function addTypeHandler(TypeHandlerInterface $handler): JsonMapper
    {
        $this->customTypeRegistry->registerHandler($handler);

        return $this;
    }

    /**
     * Registers a custom type using a closure-based handler.
     *
     * @param non-empty-string $type    Name of the custom type alias handled by the closure.
     * @param Closure          $closure Closure that converts the incoming value to the target type.
     *
     * @deprecated Use addTypeHandler() with a TypeHandlerInterface implementation instead.
     *
     * @return JsonMapper Returns the mapper instance for fluent configuration.
     */
    public function addType(string $type, Closure $closure): JsonMapper
    {
        $this->customTypeRegistry->registerHandler(new ClosureTypeHandler($type, $closure));

        return $this;
    }

    /**
     * Add a custom class map entry.
     *
     * @param class-string                                                            $className Fully qualified class name that should be resolved dynamically.
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure   Closure that returns the concrete class to instantiate for the provided value.
     *
     * @phpstan-param class-string $className
     * @phpstan-param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure
     *
     * @return JsonMapper Returns the mapper instance for fluent configuration.
     */
    public function addCustomClassMapEntry(string $className, Closure $closure): JsonMapper
    {
        $this->classResolver->add($className, $closure);

        return $this;
    }

    /**
     * Maps the JSON to the specified class entity.
     *
     * @param mixed                        $json                Source data to map into PHP objects.
     * @param class-string|null            $className           Fully qualified class name that should be instantiated for mapped objects.
     * @param class-string|null            $collectionClassName Collection class that should wrap the mapped objects when required.
     * @param MappingContext|null          $context             Optional mapping context reused across nested mappings.
     * @param JsonMapperConfiguration|null $configuration       Optional configuration that overrides the default mapper settings.
     *
     * @return mixed The mapped PHP value or collection produced from the given JSON.
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
        } elseif ($configuration instanceof JsonMapperConfiguration) {
            $context->replaceOptions($configuration->toOptions());
        } else {
            $configuration = JsonMapperConfiguration::fromContext($context);
        }

        $resolvedClassName = $className === null
            ? null
            : $this->classResolver->resolve($className, $json, $context);

        $resolvedCollectionClassName = $collectionClassName === null
            ? null
            : $this->classResolver->resolve($collectionClassName, $json, $context);

        $this->assertClassesExists($resolvedClassName, $resolvedCollectionClassName);

        $collectionValueType = $this->extractCollectionType(
            $resolvedClassName,
            $resolvedCollectionClassName
        );

        $collectionResult = $this->mapCollection(
            $json,
            $resolvedClassName,
            $resolvedCollectionClassName,
            $collectionValueType,
            $context,
        );

        if ($collectionResult !== null) {
            return $collectionResult;
        }

        if ($resolvedClassName === null) {
            return $json;
        }

        if (!is_array($json) && !is_object($json)) {
            // A scalar carries no property values, so it can only produce an instance of a class
            // whose constructor needs none. For anything else the instantiation below would fail
            // with a native ArgumentCountError, outside the error-collection contract - so the
            // impossible shape has to surface as a mapping error instead.
            if ($this->hasRequiredConstructorArguments($resolvedClassName)) {
                throw new TypeMismatchException(
                    $context->getPath(),
                    $resolvedClassName,
                    get_debug_type($json),
                );
            }

            return $this->makeInstance($resolvedClassName);
        }

        return $this->mapSingleObject($json, $resolvedClassName, $context, $configuration);
    }

    /**
     * Maps the JSON structure and returns a detailed mapping report.
     *
     * @param mixed                        $json                Source data to map into PHP objects.
     * @param class-string|null            $className           Fully qualified class name that should be instantiated for mapped objects.
     * @param class-string|null            $collectionClassName Collection class that should wrap the mapped objects when required.
     * @param JsonMapperConfiguration|null $configuration       Optional configuration that overrides the default mapper settings.
     *
     * @return MappingResult Mapping result containing the mapped value and a detailed report.
     */
    public function mapWithReport(
        mixed $json,
        ?string $className = null,
        ?string $collectionClassName = null,
        ?JsonMapperConfiguration $configuration = null,
    ): MappingResult {
        $configuration = ($configuration ?? $this->createDefaultConfiguration())->withErrorCollection(true);
        // array_replace rather than the + operator: the override has to WIN. Union keeps the left
        // operand, so the day toOptions() learns to emit abort_on_error, a + would silently stop
        // forcing it off and this method would start throwing again.
        $context = new MappingContext(
            $json,
            array_replace(
                $configuration->toOptions(),
                [MappingContext::OPTION_ABORT_ON_ERROR => false],
            ),
        );

        try {
            $value = $this->map(
                $json,
                $className,
                $collectionClassName,
                $context,
                $configuration
            );
        } catch (MappingException $exception) {
            // A failure on the root object has no enclosing property loop to record it, so it
            // used to escape this method while the identical failure one level down was collected
            // - the same error meaning different things depending on nesting depth. Routing it
            // through the shared handler makes both lanes agree; strict mode still rethrows.
            $this->handleMappingException($exception, $context);

            $value = null;
        }

        return new MappingResult($value, new MappingReport($context->getErrorRecords()));
    }

    /**
     * Extracts the collection element type based on the resolved class information.
     *
     * @param class-string|null $resolvedClassName           Fully qualified class name resolved for the mapped elements.
     * @param class-string|null $resolvedCollectionClassName Fully qualified collection class wrapping the mapped elements.
     *
     * @return Type|null Element type derived from the collection definition when available.
     */
    private function extractCollectionType(
        ?string $resolvedClassName,
        ?string $resolvedCollectionClassName,
    ): ?Type {
        if ($resolvedCollectionClassName === null) {
            return null;
        }

        if ($resolvedClassName !== null) {
            return new ObjectType($resolvedClassName);
        }

        $docBlockCollectionType = $this->collectionDocBlockTypeResolver->resolve($resolvedCollectionClassName);

        if (!$docBlockCollectionType instanceof CollectionType) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unable to resolve the element type for collection [%s]. Define an "@extends" annotation such as "@extends %s<YourClass>".',
                    $resolvedCollectionClassName,
                    $resolvedCollectionClassName,
                )
            );
        }

        $collectionValueType = $docBlockCollectionType->getCollectionValueType();

        if ($collectionValueType instanceof TemplateType) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unable to resolve the element type for collection [%s]. Please provide a concrete class in the "@extends" annotation.',
                    $resolvedCollectionClassName,
                )
            );
        }

        return $collectionValueType;
    }

    /**
     * Maps iterable payloads into the configured collection structure when applicable.
     *
     * @param mixed             $json                        Source payload that may represent a collection.
     * @param class-string|null $resolvedClassName           Fully qualified class name resolved for mapped elements.
     * @param class-string|null $resolvedCollectionClassName Fully qualified collection class wrapping the mapped elements.
     * @param Type|null         $collectionValueType         Element type derived from the collection definition.
     * @param MappingContext    $context                     Mapping context forwarded to nested mappings.
     *
     * @return mixed|null Returns the mapped collection when handled, null otherwise.
     */
    private function mapCollection(
        mixed $json,
        ?string $resolvedClassName,
        ?string $resolvedCollectionClassName,
        ?Type $collectionValueType,
        MappingContext $context,
    ): mixed {
        $isGenericCollectionMapping = $resolvedClassName === null && $collectionValueType instanceof Type;

        if ($isGenericCollectionMapping) {
            if ($resolvedCollectionClassName === null) {
                throw new InvalidArgumentException(
                    'A collection class name must be provided when mapping without an element class.'
                );
            }

            return $this->wrapCollection(
                $resolvedCollectionClassName,
                $this->collectionFactory->mapIterable($json, $collectionValueType, $context),
            );
        }

        if ($resolvedClassName === null) {
            return null;
        }

        if (!$this->isIterableWithArraysOrObjects($json)) {
            return null;
        }

        /** @var array<array-key, mixed>|object $json */
        $valueType = $collectionValueType ?? new ObjectType($resolvedClassName);

        if ($resolvedCollectionClassName !== null) {
            return $this->wrapCollection(
                $resolvedCollectionClassName,
                $this->collectionFactory->mapIterable($json, $valueType, $context),
            );
        }

        if ($this->isNumericIndexArray($json)) {
            return $this->collectionFactory->mapIterable($json, $valueType, $context);
        }

        return null;
    }

    /**
     * Maps a single object or associative array onto the resolved class instance.
     *
     * @param array<array-key, mixed>|object $json              Source payload representing the object to map.
     * @param class-string                   $resolvedClassName Fully qualified class name that receives the mapped values.
     * @param MappingContext                 $context           Mapping context forwarded to nested mappings.
     * @param JsonMapperConfiguration        $configuration     Effective configuration guiding the mapping process.
     *
     * @return object Instantiated and populated object that represents the mapped payload.
     */
    private function mapSingleObject(
        array|object $json,
        string $resolvedClassName,
        MappingContext $context,
        JsonMapperConfiguration $configuration,
    ): object {
        $source = $this->toIterableArray($json);

        $properties         = $this->getProperties($resolvedClassName);
        $replacePropertyMap = $this->buildReplacePropertyMap($resolvedClassName);
        $mappedProperties   = [];

        // A class may nominate one property (via the UnknownPropertyCollector attribute) as the sink
        // for every source key that matches no declared property. Such keys are gathered here, by
        // normalized name and raw value, and handed to that property after the main pass instead of
        // being ignored or reported.
        $collectorProperty = $this->unknownPropertyCollector($resolvedClassName);
        $collectedUnknown  = [];

        // Convert every payload value once, collecting the results by property name. Whether a
        // value ends up as a constructor argument or is assigned afterwards, it goes through the
        // exact same conversion, replace-property, replace-null and error-handling pipeline.
        $convertedValues = [];

        foreach ($source as $propertyName => $propertyValue) {
            $normalizedProperty = $this->normalizePropertyName($propertyName, $replacePropertyMap);
            $pathSegment        = is_string($normalizedProperty) ? $normalizedProperty : (string) $propertyName;

            $context->withPathSegment($pathSegment, function (MappingContext $propertyContext) use (
                $resolvedClassName,
                $normalizedProperty,
                $propertyValue,
                &$mappedProperties,
                &$convertedValues,
                &$collectedUnknown,
                $collectorProperty,
                $properties,
                $configuration,
            ): void {
                if (!is_string($normalizedProperty)) {
                    return;
                }

                // Divert a key that matches no declared property to the nominated collector rather
                // than dropping or reporting it. The collector's own key is excluded explicitly (as
                // well as through the membership check) so it is never collected into itself even
                // when an extractor is configured not to expose the collector property.
                if (
                    ($collectorProperty !== null)
                    && ($normalizedProperty !== $collectorProperty)
                    && !in_array($normalizedProperty, $properties, true)
                ) {
                    $collectedUnknown[$normalizedProperty] = $propertyValue;

                    return;
                }

                $validatedProperty = $this->validateAndNormalize(
                    $normalizedProperty,
                    $properties,
                    $configuration,
                    $propertyContext,
                    $resolvedClassName,
                );

                if ($validatedProperty === null) {
                    return;
                }

                $mappedProperties[] = $validatedProperty;

                $preparedValue = $this->normalizeEmptyStringToNull($propertyValue, $propertyContext);

                // A null input on a property marked ReplaceNullWithDefaultValue keeps the declared
                // default without running through the conversion pipeline, which would reject null
                // for a non-nullable target. A default that is itself null cannot satisfy such a
                // target either, and an untyped property reports an implicit null default through
                // reflection, so both fall through to the regular pipeline and its null guard.
                if (
                    ($preparedValue === null)
                    && $this->isReplaceNullWithDefaultValueAnnotation($resolvedClassName, $validatedProperty)
                ) {
                    $defaultValue = $this->getDefaultValue($resolvedClassName, $validatedProperty);

                    if ($defaultValue !== null) {
                        $convertedValues[$validatedProperty] = $defaultValue;

                        return;
                    }
                }

                $type = $this->typeResolver->resolve($resolvedClassName, $validatedProperty);

                try {
                    $value = $this->convertValue($preparedValue, $type, $propertyContext);
                } catch (MappingException $exception) {
                    $this->handleMappingException($exception, $propertyContext);

                    return;
                }

                if (
                    ($value === null)
                    && $this->isReplaceNullWithDefaultValueAnnotation($resolvedClassName, $validatedProperty)
                ) {
                    $value = $this->getDefaultValue($resolvedClassName, $validatedProperty);
                }

                $convertedValues[$validatedProperty] = $value;
            });
        }

        // Hand the gathered unknown keys to the nominated collector as the raw associative array of
        // normalized name to unconverted value, bypassing the per-value conversion pipeline (its
        // element type is deliberately open). Left untouched when nothing was gathered, so the
        // property keeps its constructor default. The consumer interprets the raw map itself. Any
        // explicitly mapped value for the same property is merged in rather than overwritten, so a
        // payload that carries both the collector key and unknown keys loses neither. array_replace
        // (not array_merge) preserves numeric keys instead of re-indexing them.
        if (($collectorProperty !== null) && ($collectedUnknown !== [])) {
            $mappedProperties[] = $collectorProperty;
            $existingValue      = $convertedValues[$collectorProperty] ?? [];

            $convertedValues[$collectorProperty] = array_replace(
                is_array($existingValue) ? $existingValue : [],
                $collectedUnknown,
            );
        }

        if ($configuration->isStrictMode()) {
            foreach ($this->determineMissingProperties($resolvedClassName, $properties, $mappedProperties) as $missingProperty) {
                $context->withPathSegment($missingProperty, function (MappingContext $propertyContext) use (
                    $resolvedClassName,
                    $missingProperty,
                ): void {
                    $this->handleMappingException(
                        new MissingPropertyException($propertyContext->getPath(), $missingProperty, $resolvedClassName),
                        $propertyContext,
                    );
                });
            }
        }

        // Build the object through its constructor when it declares promoted or required
        // parameters (an immutable value object cannot be populated afterwards); otherwise fall
        // back to an argument-less instantiation. Either way, any collected value that is not a
        // constructor argument is assigned afterwards, so mixed classes lose nothing.
        $constructor = $this->constructorForHydration($resolvedClassName);
        $consumed    = [];

        if ($constructor instanceof ReflectionMethod) {
            [$entity, $consumed] = $this->instantiateViaConstructor(
                $resolvedClassName,
                $constructor,
                $convertedValues,
                $context,
            );
        } else {
            $entity = $this->makeInstance($resolvedClassName);
        }

        foreach ($convertedValues as $property => $value) {
            if (isset($consumed[$property])) {
                continue;
            }

            $context->withPathSegment($property, function (MappingContext $propertyContext) use (
                $entity,
                $property,
                $value,
            ): void {
                try {
                    $this->setProperty($entity, $property, $value, $propertyContext);
                } catch (ReadonlyPropertyException $exception) {
                    $this->handleMappingException($exception, $propertyContext);
                }
            });
        }

        return $entity;
    }

    /**
     * Validates the normalized property name and reports unknown properties when required.
     *
     * @param string                    $normalizedProperty Normalized property name derived from the payload.
     * @param array<int|string, string> $properties         Declared properties available on the target class.
     * @param JsonMapperConfiguration   $configuration      Effective configuration guiding the mapping process.
     * @param MappingContext            $context            Mapping context scoped to the current property.
     * @param class-string              $resolvedClassName  Fully qualified class name receiving the mapped values.
     *
     * @return string|null Returns the validated property name or null when the property should be skipped.
     */
    private function validateAndNormalize(
        string $normalizedProperty,
        array $properties,
        JsonMapperConfiguration $configuration,
        MappingContext $context,
        string $resolvedClassName,
    ): ?string {
        if (!in_array($normalizedProperty, $properties, true)) {
            if ($configuration->shouldIgnoreUnknownProperties()) {
                return null;
            }

            $this->handleMappingException(
                new UnknownPropertyException($context->getPath(), $normalizedProperty, $resolvedClassName),
                $context,
            );

            return null;
        }

        return $normalizedProperty;
    }

    /**
     * Creates a clone of the default mapper configuration for a fresh mapping context.
     *
     * @return JsonMapperConfiguration Copy of the base configuration that can be mutated safely.
     */
    private function createDefaultConfiguration(): JsonMapperConfiguration
    {
        return clone $this->config;
    }

    /**
     * Identifies required properties that were not provided in the source data.
     *
     * @param class-string              $className          Fully qualified class name inspected for required metadata.
     * @param array<int|string, string> $declaredProperties List of property names resolved from the target class definition.
     * @param list<string>              $mappedProperties   List of properties that successfully received mapped values.
     *
     * @return list<string> List of property names that are still required after mapping.
     */
    private function determineMissingProperties(
        string $className,
        array $declaredProperties,
        array $mappedProperties,
    ): array {
        $used = array_values(array_unique($mappedProperties));

        return array_values(array_filter(
            array_diff($declaredProperties, $used),
            fn (string $property): bool => $this->isRequiredProperty($className, $property),
        ));
    }

    /**
     * Determines whether the given property must be present on the input data.
     *
     * @param class-string $className    Fully qualified class name whose property metadata is evaluated.
     * @param string       $propertyName Property name checked for default values and nullability.
     *
     * @return bool True when the property is mandatory and missing values must be reported.
     */
    private function isRequiredProperty(string $className, string $propertyName): bool
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!$reflectionProperty instanceof ReflectionProperty) {
            return false;
        }

        if ($reflectionProperty->hasDefaultValue()) {
            return false;
        }

        // A promoted property's default lives on the constructor parameter, so a promoted
        // parameter with a default is not required even though the property has none.
        $parameter = $this->constructorParameter($className, $propertyName);

        if (($parameter instanceof ReflectionParameter) && $parameter->isDefaultValueAvailable()) {
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
     * Instantiates the collection wrapper around the mapped elements.
     *
     * The elements are null when no collection was produced at all - a null payload that the
     * configuration does not map to an empty collection. A wrapper's constructor takes an array
     * and answers null with a native TypeError, which would escape error collection entirely, so
     * the absence is passed on as an absence instead of being handed over as one.
     *
     * @param class-string                    $collectionClassName Fully qualified collection class to instantiate.
     * @param array<array-key, mixed>|null    $elements            Mapped elements, or null when there was no collection.
     *
     * @return object|null Collection instance, or null when there was nothing to wrap.
     */
    private function wrapCollection(string $collectionClassName, ?array $elements): ?object
    {
        if ($elements === null) {
            return null;
        }

        return $this->makeInstance($collectionClassName, $elements);
    }

    /**
     * Records a mapping exception and decides whether it should stop the mapping process.
     *
     * @param MappingException $exception Exception that occurred while mapping a property.
     * @param MappingContext   $context   Context collecting the error information and deciding
     *                                    whether a failure aborts the run.
     */
    private function handleMappingException(
        MappingException $exception,
        MappingContext $context,
    ): void {
        $context->recordException($exception);

        // Asked of the context rather than the configuration: strict mode decides what counts as
        // a failure, the entry point decides what happens to one. map() raises on the first in
        // strict mode; mapWithReport() exists to return a report and so collects them all, which
        // is what its own recipe demonstrates.
        if ($context->shouldAbortOnError()) {
            throw $exception;
        }
    }

    /**
     * Converts the provided JSON value using the registered strategies.
     *
     * @throws TypeMismatchException When a null value targets a non-nullable type.
     */
    private function convertValue(
        mixed $json,
        Type $type,
        MappingContext $context,
    ): mixed {
        if ($type instanceof CollectionType) {
            // A null payload is only acceptable for a collection when the configuration maps it
            // to an empty collection. Otherwise it must surface as a type mismatch instead of
            // being assigned to the (non-nullable) collection property later on.
            if (
                ($json === null)
                && !$type->isNullable()
                && !$context->shouldTreatNullAsEmptyCollection()
            ) {
                throw $this->createNullMismatchException($type, $context);
            }

            return $this->collectionFactory->fromCollectionType($type, $json, $context);
        }

        if ($type instanceof UnionType) {
            return $this->convertUnionValue($json, $type, $context);
        }

        if ($this->isNullType($type)) {
            return null;
        }

        // Reject null for non-nullable targets before it reaches the strategy chain, where the
        // null strategy would swallow it and the later property assignment would fail with a
        // native (non-mapping) exception outside the error-collection contract.
        if (
            ($json === null)
            && !$type->isNullable()
        ) {
            throw $this->createNullMismatchException($type, $context);
        }

        return $this->valueConverter->convert($type, $json, $context);
    }

    /**
     * Creates the type-mismatch exception raised when a null value targets a non-nullable type.
     *
     * @param Type           $type    Non-nullable target type the null value was rejected for.
     * @param MappingContext $context Mapping context providing the current path.
     *
     * @return TypeMismatchException Exception describing the rejected null assignment.
     */
    private function createNullMismatchException(Type $type, MappingContext $context): TypeMismatchException
    {
        return new TypeMismatchException(
            $context->getPath(),
            $this->describeType($type),
            'null',
        );
    }

    /**
     * Normalizes an empty or whitespace-only string to null when the corresponding option is set.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context carrying the normalization option.
     *
     * @return mixed The unchanged value, or null when the normalization applies.
     */
    private function normalizeEmptyStringToNull(mixed $value, MappingContext $context): mixed
    {
        if (
            is_string($value)
            && (trim($value) === '')
            && (bool) $context->getOption(MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL, false)
        ) {
            return null;
        }

        return $value;
    }

    /**
     * Converts the value according to the provided union type.
     *
     * @param mixed           $json    Value being converted so it matches one of the union candidates.
     * @param UnionType<Type> $type    Union definition listing acceptable target types.
     * @param MappingContext  $context Context used to track conversion errors while testing candidates.
     *
     * @return mixed Value converted to a type accepted by the union.
     *
     * @throws TypeMismatchException When a null value targets a union without a null member.
     */
    private function convertUnionValue(
        mixed $json,
        UnionType $type,
        MappingContext $context,
    ): mixed {
        if ($json === null && $this->unionAllowsNull($type)) {
            return null;
        }

        if ($json === null) {
            // A collection member of the union honours the treat-null-as-empty-collection
            // option, mirroring the CollectionType branch in convertValue().
            if ($context->shouldTreatNullAsEmptyCollection()) {
                foreach ($type->getTypes() as $candidate) {
                    if ($candidate instanceof CollectionType) {
                        return $this->convertValue($json, $candidate, $context);
                    }
                }
            }

            // A null value on a union without a null member can never match a candidate. Reject
            // it up front with the full union description instead of surfacing the misleading
            // mismatch of whichever candidate happens to be tried last.
            throw $this->createNullMismatchException($type, $context);
        }

        $lastException = null;

        // A candidate is accepted when converting the value against it produces no error. That
        // observation is only possible while errors are actually being recorded, so collection is
        // forced on for the duration - otherwise a caller who switched reporting off would get the
        // first candidate every time, and the declared type of the value would depend on an
        // unrelated setting. Everything recorded here is trimmed away again: these are internal
        // attempts, not failures the caller asked about.
        // A regular closure rather than an arrow function: the latter captures by value, which
        // would bind the by-reference argument to a copy and leave the failure of the last
        // rejected candidate behind.
        [$matched, $converted] = $context->withForcedErrorCollection(
            function (MappingContext $childContext) use ($json, $type, &$lastException): array {
                return $this->resolveUnionCandidate($json, $type, $childContext, $lastException);
            }
        );

        if ($matched) {
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

        // A guard, not a path reached in practice: resolveUnionCandidate() assigns $lastException
        // for every rejected non-null candidate, so reaching here needs a union whose members are
        // all null types - a shape Symfony's TypeInfo does not produce. It stays because the
        // invariant lives in another method and a future member kind could break it, but it is
        // deliberately not claimed as covered.
        //
        // Asked of the context rather than the configuration for the reason given in
        // handleMappingException(), so that it cannot become the one site that still aborts.
        if ($context->shouldAbortOnError()) {
            throw $exception;
        }

        return $json;
    }

    /**
     * Tries each member of a union in declaration order and returns the first that converts the
     * value without producing an error.
     *
     * A candidate is judged by whether converting against it recorded anything, so the caller has
     * to run this with error collection forced on - see {@see MappingContext::withForcedErrorCollection()}.
     * Records produced by rejected candidates are trimmed away again: they are internal attempts,
     * not failures the caller asked about.
     *
     * @param mixed                 $json          Raw value to convert.
     * @param UnionType<Type>       $type          Union whose members are tried in order.
     * @param MappingContext        $context       Mapping context, with error collection forced on.
     * @param MappingException|null $lastException Receives the failure of the last rejected candidate.
     *
     * @return array{0: bool, 1: mixed} Whether a candidate matched, and the value it produced.
     */
    private function resolveUnionCandidate(
        mixed $json,
        UnionType $type,
        MappingContext $context,
        ?MappingException &$lastException,
    ): array {
        foreach ($type->getTypes() as $candidate) {
            if ($this->isNullType($candidate)) {
                continue;
            }

            $errorCount = $context->getErrorCount();

            try {
                $candidateValue = $this->convertValue($json, $candidate, $context);
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

            return [true, $candidateValue];
        }

        return [false, null];
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
     * @param UnionType<Type> $type Union type converted into a human-readable string.
     *
     * @return string Pipe-separated description of all candidate types.
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
     * Checks whether the provided union type accepts null values.
     *
     * @param UnionType<Type> $type Union type inspected for a nullable member.
     *
     * @return bool True when null is part of the union definition.
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

    /**
     * Checks whether the provided type explicitly represents the null value.
     *
     * @param Type $type Type information extracted for a property or union candidate.
     *
     * @return bool True when the type identifies the null built-in.
     */
    private function isNullType(Type $type): bool
    {
        return ($type instanceof BuiltinType) && ($type->getTypeIdentifier() === TypeIdentifier::NULL);
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
     * Determines whether a class can only be instantiated by passing constructor arguments.
     *
     * This is narrower than {@see constructorForHydration()}: that one also reports a constructor
     * whose parameters are all optional but promoted, which an argument-less call satisfies fine.
     * Here the question is only whether an argument-less instantiation would fail outright.
     *
     * @param class-string $className Fully qualified class name to inspect.
     *
     * @return bool TRUE when the constructor declares at least one required parameter.
     */
    private function hasRequiredConstructorArguments(string $className): bool
    {
        $constructor = (new ReflectionClass($className))->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            return false;
        }

        return $constructor->getNumberOfRequiredParameters() > 0;
    }

    /**
     * Instantiates a class through its constructor, drawing each argument from the already
     * converted values by parameter name and returning both the object and the set of property
     * names consumed as constructor arguments (so the caller assigns only the remaining ones).
     *
     * @param class-string         $className       Fully qualified class name to construct.
     * @param ReflectionMethod     $constructor     The constructor to build through.
     * @param array<string, mixed> $convertedValues The already converted values, keyed by property name.
     * @param MappingContext       $context         Mapping context, used for the error path.
     *
     * @return array{0: object, 1: array<string, true>} The constructed object and the consumed argument names.
     *
     * @throws MissingConstructorArgumentException When a required, non-nullable argument has no value and no default.
     */
    private function instantiateViaConstructor(
        string $className,
        ReflectionMethod $constructor,
        array $convertedValues,
        MappingContext $context,
    ): array {
        $arguments = [];
        $consumed  = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $convertedValues)) {
                $consumed[$name] = true;
                $value           = $convertedValues[$name];

                // A variadic parameter spreads a collected list into the tail arguments.
                if ($parameter->isVariadic() && is_array($value)) {
                    foreach ($value as $variadicValue) {
                        $arguments[] = $variadicValue;
                    }

                    continue;
                }

                $arguments[] = $value;

                continue;
            }

            if ($parameter->isVariadic()) {
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;

                continue;
            }

            throw new MissingConstructorArgumentException($context->getPath(), $name, $className);
        }

        return [$this->makeInstance($className, ...$arguments), $consumed];
    }

    /**
     * Creates an instance of the given class name.
     *
     * @param string $className               Fully qualified class name to instantiate.
     * @param mixed  ...$constructorArguments Arguments forwarded to the constructor of the class.
     *
     * @return object Newly created instance of the requested class.
     */
    private function makeInstance(string $className, mixed ...$constructorArguments): object
    {
        return new $className(...$constructorArguments);
    }

    /**
     * Checks whether the property declares the ReplaceNullWithDefaultValue attribute.
     *
     * @param class-string $className    Fully qualified class containing the property to inspect.
     * @param string       $propertyName Property name that may carry the attribute.
     *
     * @return bool True when null inputs should be replaced with the property's default value.
     */
    private function isReplaceNullWithDefaultValueAnnotation(string $className, string $propertyName): bool
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!$reflectionProperty instanceof ReflectionProperty) {
            return false;
        }

        return $this->hasAttribute($reflectionProperty, ReplaceNullWithDefaultValue::class);
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
        /** @var array<class-string, string|null> $cache */
        static $cache = [];

        if (array_key_exists($className, $cache)) {
            return $cache[$className];
        }

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

        return $cache[$className] = $collector;
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
     * Normalizes the property name using annotations and converters.
     *
     * @param string|int            $propertyName       Property name taken from the source payload.
     * @param array<string, string> $replacePropertyMap Map of alias names to their replacement counterparts.
     *
     * @return string|int Normalized property name to use for mapping.
     */
    private function normalizePropertyName(string|int $propertyName, array $replacePropertyMap): string|int
    {
        $normalized = $propertyName;

        if (
            is_string($normalized)
            && array_key_exists($normalized, $replacePropertyMap)
        ) {
            $normalized = $replacePropertyMap[$normalized];
        }

        if (
            is_string($normalized)
            && ($this->nameConverter instanceof PropertyNameConverterInterface)
        ) {
            return $this->nameConverter->convert($normalized);
        }

        return $normalized;
    }

    /**
     * Converts arrays and objects into a plain array structure.
     *
     * @param array<array-key, mixed>|object $json Source payload that may be an array, object, or traversable.
     *
     * @return array<array-key, mixed> Normalised array representation of the provided payload.
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
     * @param class-string $className    Fully qualified class containing the property definition.
     * @param string       $propertyName Property name resolved on the reflected class.
     *
     * @return ReflectionProperty|null Reflection property instance when the property exists, null otherwise.
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
     * @param class-string $className Fully qualified class name that should be reflected.
     *
     * @return ReflectionClass<object>|null Reflection of the class when it exists, otherwise null.
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
     * @param class-string $className    Fully qualified class that defines the property.
     * @param string       $propertyName Property name whose default value should be retrieved.
     *
     * @return mixed Default value configured on the property, or null when none exists.
     */
    private function getDefaultValue(string $className, string $propertyName): mixed
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!$reflectionProperty instanceof ReflectionProperty) {
            return null;
        }

        if ($reflectionProperty->hasDefaultValue()) {
            return $reflectionProperty->getDefaultValue();
        }

        // A promoted property carries no property-level default; its default lives on the
        // constructor parameter of the same name.
        $parameter = $this->constructorParameter($className, $propertyName);

        if (($parameter instanceof ReflectionParameter) && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Neither the property nor a promoted parameter declares a default. Calling
        // ReflectionProperty::getDefaultValue() here is deprecated as of PHP 8.5 precisely
        // because there is nothing to return.
        return null;
    }

    /**
     * Returns the PROMOTED constructor parameter of the given class that shares the property's
     * name, or NULL. Used to read a promoted property's default and required-ness, which live on
     * the parameter rather than the property. A plain parameter that merely shares the name is
     * ignored, since it has no type relationship to the property.
     *
     * @param class-string $className    Fully qualified class name to inspect.
     * @param string       $propertyName Property (and promoted parameter) name to look up.
     *
     * @return ReflectionParameter|null The matching constructor parameter, or NULL.
     */
    private function constructorParameter(string $className, string $propertyName): ?ReflectionParameter
    {
        $constructor = (new ReflectionClass($className))->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if (
                ($parameter->getName() === $propertyName)
                && $parameter->isPromoted()
            ) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Returns TRUE if the given JSON contains integer property keys.
     *
     * @param array<array-key, mixed>|object $json Source payload inspected for numeric keys.
     *
     * @return bool True when at least one numeric index is present.
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
        if (
            !is_array($json)
            && !is_object($json)
        ) {
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
        if (
            ($className !== null)
            && !class_exists($className)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Class [%s] does not exist',
                    $className
                )
            );
        }

        if ($collectionClassName === null) {
            return;
        }

        if (class_exists($collectionClassName)) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Class [%s] does not exist',
                $collectionClassName
            )
        );
    }

    /**
     * Sets a property value.
     */
    private function setProperty(
        object $entity,
        string $name,
        mixed $value,
        MappingContext $context,
    ): void {
        $reflectionProperty = $this->getReflectionProperty($entity::class, $name);

        if ($reflectionProperty instanceof ReflectionProperty && $reflectionProperty->isReadOnly()) {
            throw new ReadonlyPropertyException(
                $context->getPath(),
                $name,
                $entity::class
            );
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
     * @param class-string $className Fully qualified class whose property names should be extracted.
     *
     * @return string[] List of property names exposed by the configured extractor.
     */
    private function getProperties(string $className): array
    {
        return $this->extractor->getProperties($className) ?? [];
    }
}
