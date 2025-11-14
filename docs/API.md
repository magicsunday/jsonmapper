# JsonMapper API reference

This document summarises the public surface of the JsonMapper package. All classes are namespaced under `MagicSunday\\JsonMapper` unless stated otherwise.

## JsonMapper (final)
The `JsonMapper` class is the main entry point for mapping arbitrary JSON structures to PHP objects. The class is `final`; prefer composition over inheritance.

### Constructor
```php
<?php
declare(strict_types=1);

use MagicSunday\JsonMapper\JsonMapper;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Describe DTO metadata and wiring through Symfony extractors.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

// Cache resolved Type metadata for subsequent mappings.
$typeCache = new ArrayAdapter();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor, classMap: [], typeCache: $typeCache);

var_dump($mapper::class);
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

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Collect metadata and build the property accessor.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

// Translate snake_case JSON keys into camelCase DTO properties.
$nameConverter = new CamelCasePropertyNameConverter();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor, $nameConverter);

var_dump($mapper::class);
```

## Custom type handlers
Implement `Value\TypeHandlerInterface` to plug in custom conversion logic:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

final class FakeUuid
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}

final class UuidTypeHandler implements TypeHandlerInterface
{
    public function supports(Type $type, mixed $value): bool
    {
        // Only handle FakeUuid targets to keep conversion focused.
        return $type instanceof ObjectType && $type->getClassName() === FakeUuid::class;
    }

    public function convert(Type $type, mixed $value, MappingContext $context): FakeUuid
    {
        // Build the value object from the incoming scalar payload.
        return FakeUuid::fromString((string) $value);
    }
}
```

Register handlers via `JsonMapper::addTypeHandler()` to make them available for all mappings.
