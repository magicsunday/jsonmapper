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
use DomainException;
use InvalidArgumentException;
use MagicSunday\JsonMapper\Collection\CollectionDocBlockTypeResolver;
use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Collection\CollectionFactoryInterface;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\JsonMapper\Metadata\ClassMetadata;
use MagicSunday\JsonMapper\Metadata\ClassMetadataFactory;
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
use MagicSunday\JsonMapper\Value\Strategy\UnionValueConversionStrategy;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use MagicSunday\JsonMapper\Value\ValueConverter;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
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
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Traversable;

use function array_diff;
use function array_filter;
use function array_is_list;
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

    private ClassMetadataFactory $classMetadataFactory;

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
        PropertyInfoExtractorInterface $extractor,
        private PropertyAccessorInterface $accessor,
        private ?PropertyNameConverterInterface $nameConverter = null,
        array $classMap = [],
        ?CacheItemPoolInterface $typeCache = null,
        private JsonMapperConfiguration $config = new JsonMapperConfiguration(),
    ) {
        $this->typeResolver                   = new TypeResolver($extractor, $typeCache);
        $this->classMetadataFactory           = new ClassMetadataFactory($extractor);
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

        // Custom handlers are registered explicitly by the caller, so they outrank every
        // heuristic below them. The collection strategy in particular recognises a container by
        // its shape, which would otherwise shadow a handler registered for that very class -
        // addType() is documented as the escape hatch and has to behave like one. A handler can
        // never claim a genuine collection type: it matches an ObjectType by exact class name.
        $this->valueConverter->addStrategy(new CustomTypeValueConversionStrategy($this->customTypeRegistry));
        $this->valueConverter->addStrategy(
            new CollectionValueConversionStrategy(
                $this->collectionFactory,
                $this->collectionDocBlockTypeResolver
            )
        );
        $this->valueConverter->addStrategy(new DateTimeValueConversionStrategy());
        $this->valueConverter->addStrategy(new EnumValueConversionStrategy());
        $this->valueConverter->addStrategy(
            new ObjectValueConversionStrategy(
                $this->classResolver,
                // No configuration argument, which is what reduces this to one expression. Passing
                // one meant rebuilding it from this very context and having map() write it straight
                // back - a per-nested-object round trip that could only ever restore what was
                // already there, and the mechanism by which a custom option went missing before #64
                // made the write a merge. The context already carries the settings.
                fn (mixed $value, string $resolvedClass, MappingContext $context): mixed => $this->map($value, $resolvedClass, null, $context),
            ),
        );
        $this->valueConverter->addStrategy(new BuiltinValueConversionStrategy());

        // Registered last of the deciding strategies: a union is resolved by trying its members,
        // so every strategy that can answer for a concrete type has to have declined first.
        // Placing it here rather than leaving the resolution on the property path is what lets a
        // collection resolve a union ELEMENT type - it goes through the same converter.
        $this->valueConverter->addStrategy(
            new UnionValueConversionStrategy(
                fn (mixed $value, UnionType $type, MappingContext $context): mixed => $this->convertUnionValue($value, $type, $context),
            ),
        );
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
     * SECURITY: the closure's input is the payload, so returning a class name taken from it - the
     * naive discriminator, fn ($json) => $json['__type'] - lets whoever supplies the payload choose
     * which class gets instantiated, with constructor arguments that also come from the payload.
     * That is the classic object-injection surface. Decide the class from a FIXED set instead:
     *
     *     $mapper->addCustomClassMapEntry(
     *         Shape::class,
     *         static fn (array $json): string => match ($json['kind'] ?? null) {
     *             'circle' => Circle::class,
     *             'square' => Square::class,
     *             default  => Shape::class,
     *         },
     *     );
     *
     * Pass $allowedTargets to have that enforced rather than merely intended. It is opt-in, since
     * the class map is documented for class replacement as well as polymorphism and a default
     * restriction would break that.
     *
     * An entry is REPLACED wholesale: registering the same base class again without
     * $allowedTargets drops the list the earlier registration carried, since a list written for one
     * closure must not outlive it. Registration order therefore decides what is enforced.
     *
     * @param class-string                                                            $className      Fully qualified class name that should be resolved dynamically.
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure        Closure that returns the concrete class to instantiate for the provided value.
     * @param list<string>|null                                                       $allowedTargets Classes the closure may return; null leaves it unrestricted.
     *
     * @phpstan-param class-string $className
     * @phpstan-param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $closure
     *
     * @return JsonMapper Returns the mapper instance for fluent configuration.
     *
     * @throws DomainException When $allowedTargets is empty or names something that is not a class.
     */
    public function addCustomClassMapEntry(
        string $className,
        Closure $closure,
        ?array $allowedTargets = null,
    ): JsonMapper {
        $this->classResolver->add($className, $closure, $allowedTargets);

        return $this;
    }

    /**
     * Maps the JSON to the specified class entity.
     *
     * SECURITY: $className selects the class to instantiate. Never derive it from request data -
     * that is object injection by the shortest route, and no class-map allowlist covers it, since
     * allowlists are keyed by class-map entry.
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
        // Two branches, not three. The third rebuilt a configuration from the context for callers
        // that supplied only a context - which is every nested object - and nothing read it once
        // the two questions the mapper asks moved to the context. That was the READ half of the
        // round trip, and leaving it would have kept eight accessor calls and an allocation per
        // nested object to produce a value thrown away on the next line.
        if (!$context instanceof MappingContext) {
            $configuration ??= $this->createDefaultConfiguration();
            $context = new MappingContext($json, $configuration->toOptions());
        } elseif ($configuration instanceof JsonMapperConfiguration) {
            $context->replaceOptions($configuration->toOptions());
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

        return $this->mapSingleObject($json, $resolvedClassName, $context);
    }

    /**
     * Maps the JSON structure and returns a detailed mapping report.
     *
     * SECURITY: $className selects the class to instantiate. Never derive it from request data -
     * that is object injection by the shortest route, and no class-map allowlist covers it, since
     * allowlists are keyed by class-map entry.
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

        // Both checks and their guidance live on the resolver, so the property path and this one
        // cannot drift apart when the wording is improved.
        return $this->collectionDocBlockTypeResolver
            ->resolveOrFail($resolvedCollectionClassName)
            ->getCollectionValueType();
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
            // Not reachable at runtime: extractCollectionType() returns a Type only when the
            // collection class is set, so $collectionValueType being one already implies a non-null
            // collection class here. Kept because it also narrows ?string to string for the calls
            // below - PHPStan max fails without it - so it is a load-bearing assertion, not merely
            // a runtime guard.
            if ($resolvedCollectionClassName === null) {
                throw new InvalidArgumentException(
                    'A collection class name must be provided when mapping without an element class.'
                );
            }

            // A null payload yields no collection, and that is recorded rather than passed back in
            // silence: the identical null against a non-nullable collection PROPERTY is reported by
            // convertValue(), and a report whose meaning depends on nesting depth is exactly the
            // defect mapWithReport() exists to remove. Routed through the shared handler, so strict
            // map() keeps raising while mapWithReport() collects. The returned value stays null so
            // that treatNullAsEmptyCollection remains observable in the result.
            if (($json === null) && !$context->shouldTreatNullAsEmptyCollection()) {
                $this->handleMappingException(
                    new TypeMismatchException($context->getPath(), $resolvedCollectionClassName, 'null'),
                    $context,
                );

                return null;
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
            // A null is not a scalar and is not handled here. It means "no collection", which the
            // generic lane above answers by honouring treatNullAsEmptyCollection - and letting the
            // guard below swallow it made that option silently inert on this lane, so the same
            // payload and the same configuration produced an empty collection or a hard failure
            // depending only on whether an element class was also passed.
            if (($json === null) && ($resolvedCollectionClassName !== null)) {
                return $context->shouldTreatNullAsEmptyCollection()
                    ? $this->wrapCollection($resolvedCollectionClassName, [])
                    : null;
            }

            // A SCALAR against a requested collection is refused. It can be neither the collection
            // nor an element of it, yet the collection class was dropped silently and a bare
            // element built from nothing came back - so a caller who type-hinted the collection got
            // a TypeError from its own code, far from the cause.
            //
            // An object payload is deliberately NOT refused here. Handing over both class names and
            // letting the shape decide is how a caller consumes an API that returns one object for
            // a single hit and a list for several, and mapSingleObjectWithGivenCollection() has
            // pinned that since long before this change. Only the shape that can satisfy neither
            // reading is rejected.
            //
            // Thrown rather than routed through throwOrRecord(). That helper is for a site with a
            // partial answer to hand back - the collection factory's guard returns an empty
            // collection - so it can record and carry on. This one has none: returning after
            // recording would let map() fall through to the single-object lane and build the very
            // element being rejected. The catch that receives the throw records it exactly once.
            // A LIST whose entries are not mappable is refused for the same reason, and the scalar
            // test alone does not catch it because such a payload IS an array. A list of scalars
            // cannot be a collection of objects, so it fell through to the single-object lane and
            // came back as one element built from the list itself - silently, and where the list
            // mixed shapes, discarding the entry that would have mapped.
            //
            // Being a list is the discriminator, not what the entries hold: an OBJECT whose values
            // are scalars is simply an object, and stays exempt. An empty array is not caught here
            // either, since isIterableWithArraysOrObjects() already answers true for it.
            $isUnmappableList = is_array($json) && array_is_list($json);

            if (($resolvedCollectionClassName !== null) && ($isUnmappableList || (!is_array($json) && !is_object($json)))) {
                throw new CollectionMappingException($context->getPath(), get_debug_type($json));
            }

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

        // An empty array deliberately falls through to the single-object lane rather than being
        // treated as an empty list. It is genuinely ambiguous: json_decode() with associative:true
        // renders both [] and {} as an empty array, and a caller passing associative arrays
        // directly - the common case outside json_decode - means an empty OBJECT by it far more
        // often than an empty list.
        //
        // A caller who does mean an empty list says so by naming the collection class, which the
        // branch above handles. Resolving the ambiguity the other way was tried and rejected: it
        // turns every map([], Dto::class) into a list, and that call is how a caller asks for a
        // DTO built from defaults.
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
     *
     * @return object Instantiated and populated object that represents the mapped payload.
     */
    private function mapSingleObject(
        array|object $json,
        string $resolvedClassName,
        MappingContext $context,
    ): object {
        // Orchestrates the phases below; the state they hand along travels by return rather than
        // by shared locals.
        $metadata = $this->classMetadataFactory->forClass($resolvedClassName);

        [$convertedValues, $mappedProperties] = $this->collectConvertedValues(
            $this->toIterableArray($json),
            $resolvedClassName,
            $metadata,
            $context,
        );

        if ($context->isStrictMode()) {
            $this->reportMissingProperties($resolvedClassName, $metadata, $mappedProperties, $context);
        }

        return $this->hydrate($resolvedClassName, $metadata, $convertedValues, $context);
    }

    /**
     * Converts every payload value once, keyed by the property it maps to.
     *
     * Whether a value ends up a constructor argument or is assigned afterwards, it goes through
     * the exact same conversion, replace-property, replace-null and error-handling pipeline.
     *
     * A key matching no declared property is not converted but diverted to the nominated collector,
     * and the gathered keys are merged into it once the loop completes - the two halves of the one
     * unknown-key concern, kept together.
     *
     * @param array<array-key, mixed> $source            Payload as an associative array.
     * @param class-string            $resolvedClassName Class the values are mapped onto.
     * @param ClassMetadata           $metadata          The class's derived shape.
     * @param MappingContext          $context           Active mapping context.
     *
     * @return array{0: array<string, mixed>, 1: list<string>} Converted values by property, and the
     *                                                         names actually mapped.
     */
    private function collectConvertedValues(
        array $source,
        string $resolvedClassName,
        ClassMetadata $metadata,
        MappingContext $context,
    ): array {
        $properties         = $metadata->properties;
        $replacePropertyMap = $metadata->replaceMap;
        $collectorProperty  = $metadata->collectorProperty;
        $mappedProperties   = [];
        $collectedUnknown   = [];
        $convertedValues    = [];

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

        // Merge the diverted unknown keys into the collector, the tail of the same concern that
        // diverted them above. They go in as the raw associative array of normalized name to
        // unconverted value, bypassing the per-value pipeline (the collector's element type is
        // deliberately open). Left untouched when nothing was gathered, so the property keeps its
        // constructor default. Any explicitly mapped value for the same property is merged in
        // rather than overwritten, so a payload carrying both the collector key and unknown keys
        // loses neither. array_replace (not array_merge) preserves numeric keys.
        if (($collectorProperty !== null) && ($collectedUnknown !== [])) {
            $mappedProperties[] = $collectorProperty;
            $existingValue      = $convertedValues[$collectorProperty] ?? [];

            $convertedValues[$collectorProperty] = array_replace(
                is_array($existingValue) ? $existingValue : [],
                $collectedUnknown,
            );
        }

        return [$convertedValues, $mappedProperties];
    }

    /**
     * Records a failure for every required property the payload did not supply.
     *
     * @param class-string   $resolvedClassName Class being mapped.
     * @param ClassMetadata  $metadata          The class's derived shape.
     * @param list<string>   $mappedProperties  Names the payload actually supplied.
     * @param MappingContext $context           Active mapping context.
     *
     * @return void
     */
    private function reportMissingProperties(
        string $resolvedClassName,
        ClassMetadata $metadata,
        array $mappedProperties,
        MappingContext $context,
    ): void {
        foreach ($this->determineMissingProperties($resolvedClassName, $metadata->properties, $mappedProperties) as $missingProperty) {
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

    /**
     * Builds the object and assigns the converted values it did not consume as constructor arguments.
     *
     * The object is built through its constructor when it declares promoted or required parameters
     * (an immutable value object cannot be populated afterwards); otherwise through an argument-less
     * instantiation. Either way, any collected value that is not a constructor argument is assigned
     * afterwards, so mixed classes lose nothing.
     *
     * @param class-string         $resolvedClassName Class to build.
     * @param ClassMetadata        $metadata          The class's derived shape.
     * @param array<string, mixed> $convertedValues   Values to hydrate with.
     * @param MappingContext       $context           Active mapping context.
     *
     * @return object The built and populated object.
     */
    private function hydrate(
        string $resolvedClassName,
        ClassMetadata $metadata,
        array $convertedValues,
        MappingContext $context,
    ): object {
        $constructor = $metadata->constructor;
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
     * @param MappingContext            $context            Mapping context scoped to the current property.
     * @param class-string              $resolvedClassName  Fully qualified class name receiving the mapped values.
     *
     * @return string|null Returns the validated property name or null when the property should be skipped.
     */
    private function validateAndNormalize(
        string $normalizedProperty,
        array $properties,
        MappingContext $context,
        string $resolvedClassName,
    ): ?string {
        if (!in_array($normalizedProperty, $properties, true)) {
            if ($context->shouldIgnoreUnknownProperties()) {
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
            fn (string $property): bool => $this->classMetadataFactory->forClass($className)->isRequired($property),
        ));
    }

    /**
     * Instantiates the collection wrapper around the mapped elements.
     *
     * The null case is no longer reachable: both call sites guard against it, one with its own null
     * check and one via isIterableWithArraysOrObjects(), and mapIterable() returns an empty array
     * for a recorded failure rather than its absence sentinel. It is handled rather than asserted
     * away because the declared return type still permits null, and passing null to a wrapper's
     * constructor raises a native TypeError - an error that escapes error collection entirely,
     * which is the one thing this entry point promises not to do. The unreachability is an
     * emergent property of two callers, not an invariant the signature enforces, so the branch is
     * the cheap enforcement at the boundary.
     *
     * @param class-string                 $collectionClassName Fully qualified collection class to instantiate.
     * @param array<array-key, mixed>|null $elements            Mapped elements, or null when there was no collection.
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
        // NOT throwOrRecord(): routing it through the helper would leave an aborting run with no
        // record at all, because this IS the catch site the helper's throw is caught by.
        //
        // Asked of the context rather than the configuration: strict mode decides what counts as
        // a failure, the entry point decides what happens to one. map() raises on the first in
        // strict mode; mapWithReport() exists to return a report and so collects them all.
        $context->recordException($exception);

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

        // A guard, not a path reached in practice: resolveUnionCandidate() assigns $lastException
        // for every rejected non-null candidate, so reaching here needs a union whose members are
        // all null types - a shape Symfony's TypeInfo does not produce. It stays because the
        // invariant lives in another method and a future member kind could break it, but it is
        // deliberately not claimed as covered.
        $context->throwOrRecord($exception);

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
     * Determines whether a class can only be instantiated by passing constructor arguments.
     *
     * This is narrower than the constructor selection in ClassMetadataFactory: that one also
     * reports a constructor whose parameters are all optional but promoted, which an argument-less
     * call satisfies fine. Here the question is only whether an argument-less instantiation would
     * fail outright.
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
        return $this->classMetadataFactory->forClass($className)->replacesNullWithDefault($propertyName);
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
     * Returns the default value of a property.
     *
     * @param class-string $className    Fully qualified class that defines the property.
     * @param string       $propertyName Property name whose default value should be retrieved.
     *
     * @return mixed Default value configured on the property, or null when none exists.
     */
    private function getDefaultValue(string $className, string $propertyName): mixed
    {
        // Neither the property nor a promoted parameter declaring a default yields null. Calling
        // ReflectionProperty::getDefaultValue() for that case is deprecated as of PHP 8.5 precisely
        // because there is nothing to return, which is why the metadata resolves it once instead.
        return $this->classMetadataFactory->forClass($className)->defaultValue($propertyName);
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
}
