# Type converters and custom class maps

## Custom type handlers

To handle custom or special types of objects, add them to the mapper. You may implement `\MagicSunday\JsonMapper\Value\TypeHandlerInterface` to package reusable handlers, or use `ClosureTypeHandler` for lightweight overrides.

```php
require __DIR__ . '/vendor/autoload.php';

use DateTimeImmutable;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Value\ClosureTypeHandler;
use stdClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class Bar
{
    public function __construct(public string $name)
    {
    }
}

final class Wrapper
{
    public Bar $bar;
    public DateTimeImmutable $createdAt;
}

// Describe DTO properties through Symfony extractors.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);

// Register a handler that hydrates Bar value objects from nested stdClass payloads.
$mapper->addTypeHandler(
    new ClosureTypeHandler(
        Bar::class,
        static function (stdClass $value): Bar {
            // Convert the decoded JSON object into a strongly typed Bar instance.
            return new Bar($value->name);
        },
    ),
);

// Register a handler for DateTimeImmutable conversion using ISO-8601 timestamps.
$mapper->addTypeHandler(
    new ClosureTypeHandler(
        DateTimeImmutable::class,
        static function (string $value): DateTimeImmutable {
            return new DateTimeImmutable($value);
        },
    ),
);

// Decode the JSON payload while throwing on malformed input.
$payload = json_decode('{"bar":{"name":"custom"},"createdAt":"2024-01-01T10:00:00+00:00"}', associative: false, flags: JSON_THROW_ON_ERROR);

// Map the payload into the Wrapper DTO.
$result = $mapper->map($payload, Wrapper::class);

var_dump($result);
```

## Custom class maps

Use `JsonMapper::addCustomClassMapEntry()` when the target class depends on runtime data. The resolver receives the decoded JSON payload and may inspect a `MappingContext` when you need additional state.

> ### Security: never return a class name the payload supplied
>
> The resolver's input is the payload. Returning a class name taken from it lets whoever sends that
> payload choose which class gets instantiated — with constructor arguments that also come from the
> payload. That is the classic PHP object-injection surface, and it reaches any autoloadable class,
> including ones whose constructor or destructor does something on your behalf.
>
> ```php
> // DO NOT DO THIS.
> $mapper->addCustomClassMapEntry(Shape::class, static fn (array $json): string => $json['__type']);
> ```
>
> Decide from a fixed set instead, so the payload selects among classes you named rather than
> naming one itself:
>
> ```php
> $mapper->addCustomClassMapEntry(
>     Shape::class,
>     static fn (array $json): string => match ($json['kind'] ?? null) {
>         'circle' => Circle::class,
>         'square' => Square::class,
>         default  => Shape::class,
>     },
> );
> ```
>
> The mapper cannot tell the two apart — both return a `class-string` — so pass an **allowlist** to
> have the constraint enforced rather than merely intended:
>
> ```php
> $mapper->addCustomClassMapEntry(
>     Shape::class,
>     $resolver,
>     [Circle::class, Square::class, Shape::class],
> );
> ```
>
> A resolution outside that list raises a `DomainException` naming the class it refused. The list is
> validated when you register it, so a typo fails immediately rather than silently narrowing what is
> permitted. It is opt-in because the class map is also used for plain class replacement between
> unrelated types — see [Manual instantiation](manual-instantiation.md) — which an enforced list
> would break.

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class SdkFoo
{
}

final class FooBar
{
}

final class FooBaz
{
}

// Build the dependencies shared by all mapping runs.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);

// Route SDK payloads to specific DTOs based on runtime discriminator data.
$mapper->addCustomClassMapEntry(SdkFoo::class, static function (array $payload): string {
    // Decide which DTO to instantiate by inspecting the payload type.
    return $payload['type'] === 'bar' ? FooBar::class : FooBaz::class;
});
```
