# JsonMapper API reference

This document summarises the public surface of the JsonMapper package. All classes are namespaced under `MagicSunday\\JsonMapper` unless stated otherwise.

## JsonMapper (final)
The `JsonMapper` class is the main entry point for mapping arbitrary JSON structures to PHP objects. The class is `final`; prefer composition over inheritance.

### Constructor
```
__construct(
    PropertyInfoExtractorInterface $extractor,
    PropertyAccessorInterface $accessor,
    ?PropertyNameConverterInterface $nameConverter = null,
    array $classMap = [],
    ?CacheItemPoolInterface $typeCache = null,
    JsonMapperConfiguration $config = new JsonMapperConfiguration(),
)
```

* `$classMap` allows overriding resolved target classes. Use `addCustomClassMapEntry()` for runtime registration.
* `$typeCache` enables caching of resolved Symfony `Type` instances. Any PSR-6 cache pool is supported.
* `$config` provides the default configuration that will be cloned for every mapping operation.

### Methods

| Method | Description |
| --- | --- |
| `addTypeHandler(TypeHandlerInterface $handler): self` | Registers a reusable conversion strategy for a specific type. |
| `addType(string $type, Closure $closure): self` | Deprecated shortcut for registering closure-based handlers. Prefer `addTypeHandler()`. |
| `addCustomClassMapEntry(string $className, Closure $resolver): self` | Adds or replaces a class map entry. The resolver receives JSON data (and optionally the current `MappingContext`). |
| `map(mixed $json, ?string $className = null, ?string $collectionClassName = null, ?MappingContext $context = null, ?JsonMapperConfiguration $configuration = null): mixed` | Maps the provided JSON payload to the requested class or collection. |
| `mapWithReport(mixed $json, ?string $className = null, ?string $collectionClassName = null, ?JsonMapperConfiguration $configuration = null): MappingResult` | Maps data and returns a `MappingResult` containing both the mapped value and an error report. |

> `map()` and `mapWithReport()` accept JSON decoded into arrays or objects (`json_decode(..., associative: false)` is recommended). Collections require either an explicit collection class name or collection PHPDoc (`@extends`) metadata.

## JsonMapperConfiguration (final)
The `JsonMapperConfiguration` class encapsulates mapping options. All configuration methods return a **new** instance; treat instances as immutable value objects.

### Factory helpers
* `JsonMapperConfiguration::lenient()` – default, tolerant configuration.
* `JsonMapperConfiguration::strict()` – enables strict mode (missing and unknown properties raise `MappingException`).
* `JsonMapperConfiguration::fromArray(array $data)` – rebuilds a configuration from persisted values.
* `JsonMapperConfiguration::fromContext(MappingContext $context)` – reconstructs a configuration for an existing mapping run.

### Withers
Each `with*` method toggles a single option and returns a clone:

| Method | Purpose |
| --- | --- |
| `withStrictMode(bool $enabled)` | Enable strict validation. |
| `withCollectErrors(bool $enabled)` | Collect errors instead of failing fast. Required for `mapWithReport()`. |
| `withTreatEmptyStringAsNull(bool $enabled)` | Map empty strings to `null`. |
| `withIgnoreUnknownProperties(bool $enabled)` | Skip unmapped JSON keys. |
| `withTreatNullAsEmptyCollection(bool $enabled)` | Replace `null` collections with their default value. |
| `withDefaultDateFormat(string $format)` | Configure the default `DateTimeInterface` parsing format. |
| `withScalarToObjectCasting(bool $enabled)` | Allow casting scalar values to object types when possible. |

Use `toOptions()` to feed configuration data into a `MappingContext`, or `toArray()` to persist settings.

## Property name converters
`CamelCasePropertyNameConverter` implements `PropertyNameConverterInterface` and is declared `final`. Instantiate it when JSON keys use snake case:

```
$nameConverter = new CamelCasePropertyNameConverter();
$mapper = new JsonMapper($extractor, $accessor, $nameConverter);
```

## Custom type handlers
Implement `Value\TypeHandlerInterface` to plug in custom conversion logic:

```
final class UuidTypeHandler implements TypeHandlerInterface
{
    public function supports(Type $type, mixed $value): bool
    {
        return $type instanceof ObjectType && $type->getClassName() === Uuid::class;
    }

    public function convert(Type $type, mixed $value, MappingContext $context): Uuid
    {
        return Uuid::fromString((string) $value);
    }
}
```

Register handlers via `JsonMapper::addTypeHandler()` to make them available for all mappings.
