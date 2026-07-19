# JsonMapper API reference

This document summarises the public surface of the JsonMapper package. All classes are namespaced under `MagicSunday\\JsonMapper` unless stated otherwise.

## JsonMapper (final)
The `JsonMapper` class is the main entry point for mapping arbitrary JSON structures to PHP objects. The class is `final`; prefer composition over inheritance.

### Factory helper
```php
<?php
declare(strict_types=1);

use MagicSunday\JsonMapper;

$mapper = JsonMapper::createWithDefaults();
```

The helper wires the Symfony `PropertyInfoExtractor` (reflection + PhpDoc) and a default `PropertyAccessor`. Use the constructor described below when you need custom extractors, caches, or accessors.

### Constructor
```php
<?php
declare(strict_types=1);

use MagicSunday\JsonMapper;
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

> **What the payload's shape decides.** Given both an element class and a collection class, a list
> becomes the collection and a single object becomes one element — which is how you consume an API
> that returns one object for a single hit and a list for several. A **scalar** satisfies neither
> reading and is reported as a `CollectionMappingException` rather than silently yielding a bare
> element.
>
> An **empty array** without a collection class yields an *instance*, not an empty list: with
> `associative: true` both `[]` and `{}` decode to an empty array, and a caller passing associative
> arrays directly usually means the object. Name the collection class when you mean an empty list —
> `map([], Item::class, ItemCollection::class)` returns an empty `ItemCollection`.

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
| `withErrorCollection(bool $collect)` | Collect errors instead of failing fast. `mapWithReport()` enables it regardless. |
| `withEmptyStringAsNull(bool $enabled)` | Map empty strings to `null`. |
| `withIgnoreUnknownProperties(bool $enabled)` | Skip unmapped JSON keys. |
| `withTreatNullAsEmptyCollection(bool $enabled)` | Replace `null` collections with their default value. |
| `withDefaultDateFormat(string $format)` | Configure the default `DateTimeInterface` parsing format. |
| `withDefaultTimezone(string $timezone)` | Timezone assumed when the date format carries none. Defaults to `UTC`. |
| `withScalarToObjectCasting(bool $enabled)` | Allow casting scalar values to object types when possible. |

Use `toOptions()` to feed configuration data into a `MappingContext`, or `toArray()` to persist settings.

> Date properties may be typed with any `DateTimeInterface` implementation — `DateTimeImmutable`,
> the mutable `DateTime`, or your own subclass — and the mapper builds whatever class the property
> declares. `DateInterval` is supported the same way. A property typed by the **interface** is
> refused: it cannot be instantiated, and choosing an implementation would decide mutability on
> your behalf.

> A date **format that carries no timezone** — `Y-m-d H:i:s`, say — is read as UTC rather than in
> the host's timezone, so the same payload yields the same instant on every deployment. Use
> `withDefaultTimezone()` when your zoneless payloads are wall-clock times in a known region. A
> payload that states its own offset always wins; the default format, `ATOM`, carries one, so this
> setting does not affect it.

> A field the format does not mention — the time of day under `Y-m-d`, say — is **reset**, not taken
> from the clock, so the same payload maps to the same instant however often it is mapped.

> A **timestamp** may be an integer or a float. A fraction is kept as microseconds
> (`1700000000.5` → `.500000`), and a timestamp is an absolute instant, so the host's timezone
> cannot shift it either.

> To **preserve** unmapped keys instead of skipping (`withIgnoreUnknownProperties`) or reporting them (strict mode), mark one property with the `UnknownPropertyCollector` attribute — it receives the unknown keys as a raw `array<string, mixed>`. See [Using mapper attributes](recipes/using-attributes.md).

## Property name converters
`CamelCasePropertyNameConverter` implements `PropertyNameConverterInterface` and is declared `final`. Instantiate it when JSON keys use snake case:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;

// Translate snake_case JSON keys into camelCase DTO properties.
$nameConverter = new CamelCasePropertyNameConverter();

$mapper = JsonMapper::createWithDefaults($nameConverter);

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
