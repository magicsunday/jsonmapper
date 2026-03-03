# Manual instantiation

`JsonMapper::createWithDefaults()` covers most use cases. When you need custom extractors, a name converter, or class-map overrides, construct the mapper manually.

## Basic manual setup

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class SdkFoo
{
}

final class Foo
{
}

// Gather Symfony extractors that describe available DTO properties.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);

// Build a property accessor so JsonMapper can read and write DTO values.
$propertyAccessor = PropertyAccess::createPropertyAccessor();

// Convert snake_case JSON keys into camelCase DTO properties.
$nameConverter = new CamelCasePropertyNameConverter();

// Provide explicit class-map overrides when API classes differ from DTOs.
$classMap = [
    SdkFoo::class => Foo::class,
];

// Finally create the mapper with the configured dependencies.
$mapper = new JsonMapper(
    $propertyInfo,
    $propertyAccessor,
    $nameConverter,
    $classMap,
);
```

To use the `PhpDocExtractor` extractor you need to install the `phpdocumentor/reflection-docblock` library too.

## Complete factory helper

A reusable factory function to bootstrap `JsonMapper` instances:

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * Bootstrap a JsonMapper instance with Symfony extractors and optional class-map overrides.
 *
 * @param array<class-string, class-string> $classMap Override source classes with DTO replacements.
 */
function createJsonMapper(array $classMap = []): JsonMapper
{
    // Cache property metadata to avoid repeated reflection work.
    $propertyInfo = new PropertyInfoExtractor(
        listExtractors: [new ReflectionExtractor()],
        typeExtractors: [new PhpDocExtractor()],
    );

    // Return a mapper configured with a camelCase converter and optional overrides.
    return new JsonMapper(
        $propertyInfo,
        PropertyAccess::createPropertyAccessor(),
        new CamelCasePropertyNameConverter(),
        $classMap,
    );
}

$mapper = createJsonMapper();
```

## Mapping collections

Call method `map` to do the actual mapping of the JSON object/array into PHP classes. Pass the initial class name and optional the name of a collection class to the method.

```php
require __DIR__ . '/vendor/autoload.php';

use ArrayObject;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class FooCollection extends ArrayObject
{
}

final class Foo
{
    public string $name;
}

// Decode a JSON array into objects and throw on malformed payloads.
$json = json_decode('[{"name":"alpha"},{"name":"beta"}]', associative: false, flags: JSON_THROW_ON_ERROR);

// Configure JsonMapper with reflection and PHPDoc metadata.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);

// Map the collection into Foo instances stored inside FooCollection.
$mappedResult = $mapper->map($json, Foo::class, FooCollection::class);

var_dump($mappedResult);
```
