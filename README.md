<h1 align="center">JsonMapper: JSON to PHP Object Mapping</h1>

<p align="center">
  Map JSON data to strongly-typed PHP classes using Symfony's PropertyInfo and PropertyAccess components.
</p>

<!-- Row 1: CI / Quality badges -->
<p align="center">
  <a href="https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml"><img src="https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
</p>

<!-- Row 2: Standards / Tooling badges -->
<p align="center">
  <a href="https://phpstan.org/"><img src="https://img.shields.io/badge/PHPStan-max%20level-brightgreen.svg" alt="PHPStan Max Level"></a>
  <a href="https://phpunit.de/"><img src="https://img.shields.io/badge/PHPUnit-12-blue.svg" alt="PHPUnit 12"></a>
  <a href="https://getrector.com/"><img src="https://img.shields.io/badge/Rector-2.0-orange.svg" alt="Rector 2.0"></a>
  <a href="https://www.php-fig.org/psr/psr-12/"><img src="https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg" alt="PSR-12"></a>
</p>

<!-- Row 3: Compatibility badges -->
<p align="center">
  <a href="composer.json"><img src="https://img.shields.io/badge/php-8.3|8.4|8.5-blue" alt="PHP Version"></a>
</p>

<!-- Row 4: Project badges -->
<p align="center">
  <a href="https://github.com/magicsunday/jsonmapper/releases/latest"><img src="https://img.shields.io/github/v/release/magicsunday/jsonmapper?sort=semver" alt="Latest version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/magicsunday/jsonmapper" alt="License"></a>
</p>

---

## 📌 Overview
JsonMapper is a PHP library that maps JSON data to strongly-typed PHP classes (DTOs, value objects, entities) using reflection and PHPDoc annotations. It leverages Symfony's PropertyInfo and PropertyAccess components to provide flexible, extensible JSON-to-PHP object mapping.

| Key      | Value                                              |
|----------|----------------------------------------------------|
| Package  | `magicsunday/jsonmapper`                           |
| PHP      | `^8.3`                                             |
| Main API | `MagicSunday\JsonMapper`                           |
| Output   | Mapped PHP objects + optional `MappingReport`      |

## ❓ What is this?
JsonMapper takes decoded JSON (via `json_decode`) and hydrates typed PHP objects, including nested objects, collections, enums, DateTime values, and custom types. It supports both lenient and strict mapping modes with detailed error reporting.

## 🎯 Why does this exist?
Mapping API responses or configuration payloads to typed PHP classes is a common task that involves repetitive boilerplate. JsonMapper automates this with a clean, extensible architecture based on Symfony components, supporting advanced scenarios like polymorphic APIs, custom name conversion, and recursive collection handling.

## 🚀 Usage

```bash
composer require magicsunday/jsonmapper
```

### Quick start
A minimal mapping run consists of two parts: a set of DTOs annotated with collection metadata and the mapper bootstrap code.

```php
namespace App\Dto;

use ArrayObject;

final class Comment
{
    public string $message;
}

/**
 * @extends ArrayObject<int, Comment>
 */
final class CommentCollection extends ArrayObject
{
}

/**
 * @extends ArrayObject<int, Article>
 */
final class ArticleCollection extends ArrayObject
{
}

final class Article
{
    public string $title;

    /**
     * @var CommentCollection<int, Comment>
     */
    public CommentCollection $comments;
}
```

```php
require __DIR__ . '/vendor/autoload.php';

use App\Dto\Article;
use App\Dto\ArticleCollection;
use MagicSunday\JsonMapper;

// Decode a single article and a list of articles, raising on malformed JSON.
$single = json_decode('{"title":"Hello world","comments":[{"message":"First!"}]}', associative: false, flags: JSON_THROW_ON_ERROR);
$list = json_decode('[{"title":"Hello world","comments":[{"message":"First!"}]},{"title":"Second","comments":[]}]', associative: false, flags: JSON_THROW_ON_ERROR);

// Bootstrap JsonMapper with reflection and PhpDoc extractors.
$mapper = JsonMapper::createWithDefaults();

// Map a single DTO and an entire collection in one go.
$article = $mapper->map($single, Article::class);
$articles = $mapper->map($list, Article::class, ArticleCollection::class);

// Dump the results to verify the hydrated structures.
var_dump($article, $articles);
```

The first call produces an `Article` instance with a populated `CommentCollection`; the second call returns an `ArticleCollection` containing `Article` objects.

`JsonMapper::createWithDefaults()` wires the default Symfony `PropertyInfoExtractor` (reflection + PhpDoc) and a `PropertyAccessor`. When you need custom extractors, caching, or a specialised accessor you can still instantiate `JsonMapper` manually with your preferred services.

Test coverage: `tests/JsonMapper/DocsQuickStartTest.php`.

### PHP classes
In order to guarantee a seamless mapping of a JSON response into PHP classes you should prepare your classes well.
Annotate all properties with the requested type.

In order to ensure correct mapping of a collection, the property has to be annotated using
the phpDocumentor collection annotation type. A collection is a non-scalar value capable of containing other
values.

For example:

```php
/** @var SomeCollection<DateTime> $dates */
/** @var SomeCollection<string> $labels */
/** @var Collection\\SomeCollection<App\\Entity\\SomeEntity> $entities */
```


### Mapping collections
Convert a JSON string into a JSON array/object using PHPs built in method `json_decode`
```php
// Decode the JSON document while propagating parser errors.
$json = json_decode('{"title":"Sample"}', associative: false, flags: JSON_THROW_ON_ERROR);

// Inspect the decoded representation.
var_dump($json);
```

Call method `map` to do the actual mapping of the JSON object/array into PHP classes. Pass the initial class name
and optional the name of a collection class to the method.
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

### Complete set-up
A complete set-up may look like this:

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

## 🧩 Custom attributes
Sometimes its may be required to circumvent the limitations of a poorly designed API. Together with custom
attributes it becomes possible to fix some API design issues (e.g. mismatch between documentation and webservice
response), to create a clean SDK.

### #[ReplaceNullWithDefaultValue]
This attribute is used to inform the JsonMapper that an existing default value should be used when
setting a property, if the value derived from the JSON is a NULL value instead of the expected property type.

This can be necessary, for example, in the case of a bad API design, if the API documentation defines a
certain type (e.g. array), but the API call itself then returns NULL if no data is available for a property
instead of an empty array that can be expected.

```php
namespace App\Dto;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

final class AttributeExample
{
    /**
     * @var array<string>
     */
    #[ReplaceNullWithDefaultValue]
    public array $roles = [];
}
```

If the mapping tries to assign NULL to the property, the default value will be used, as annotated.

### #[ReplaceProperty]
This attribute is used to inform the JsonMapper to replace one or more properties with another one. It's
used in class context.

For instance if you want to replace a cryptic named property to a more human-readable name.
```php
namespace App\Dto;

use MagicSunday\JsonMapper\Attribute\ReplaceProperty;

#[ReplaceProperty('type', replaces: 'crypticTypeNameProperty')]
final class FooClass
{
    public string $type;
}
```


## ⚙️ Instantiation

In order to create an instance of the JsonMapper you are required to pass some arguments to the constructor. The
constructor requires an instance of `\Symfony\Component\PropertyInfo\PropertyInfoExtractor` and an instance of
`\Symfony\Component\PropertyAccess\PropertyAccessor`. The other arguments are optional.

So first create instances of Symfony's property info extractors. Each list of extractors could contain any number of
available extractors. You could also create your own extractors to adjust the process of extracting property info to
your needs.

To use the `PhpDocExtractor` extractor you need to install the `phpdocumentor/reflection-docblock` library too.

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

## 🔧 Type converters and custom class maps

To handle custom or special types of objects, add them to the mapper. For instance to perform
special treatment if an object of type Bar should be mapped:

You may alternatively implement `\MagicSunday\JsonMapper\Value\TypeHandlerInterface` to package reusable handlers.

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

Use `JsonMapper::addCustomClassMapEntry()` when the target class depends on runtime data. The resolver receives the decoded JSON payload and may inspect a `MappingContext` when you need additional state.

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

## 🛡️ Error handling strategies
The mapper operates in a lenient mode by default. Switch to strict mapping when every property must be validated:

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class Article
{
    public string $title;
}

// Decode the JSON payload that should comply with the DTO schema.
$payload = json_decode('{"title":"Strict example"}', associative: false, flags: JSON_THROW_ON_ERROR);

// Prepare the mapper with Symfony metadata extractors.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);

// Enable strict validation and collect every encountered error.
$config = JsonMapperConfiguration::strict()->withCollectErrors(true);

// Map while receiving a result object that contains the mapped DTO and issues.
$result = $mapper->mapWithReport($payload, Article::class, configuration: $config);

var_dump($result->getMappedValue());
```

For tolerant APIs combine `JsonMapperConfiguration::lenient()` with `->withIgnoreUnknownProperties(true)` or `->withTreatNullAsEmptyCollection(true)` to absorb schema drifts.

## ⚡ Performance hints
Type resolution is the most expensive part of a mapping run. Provide a PSR-6 cache pool to the constructor to reuse computed `Type` metadata:

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Assemble the reflection and PHPDoc extractors once.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

// Cache resolved Type metadata between mapping runs.
$cache = new ArrayAdapter();
$mapper = new JsonMapper($propertyInfo, $propertyAccessor, nameConverter: null, classMap: [], typeCache: $cache);
```

Reuse a single `JsonMapper` instance across requests to share the cached metadata and registered handlers.

## 📚 Documentation
* [API reference](docs/API.md)
* Recipes
  * [Mapping JSON to PHP enums](docs/recipes/mapping-with-enums.md)
  * [Using mapper attributes](docs/recipes/using-attributes.md)
  * [Mapping nested collections](docs/recipes/nested-collections.md)
  * [Using a custom name converter](docs/recipes/custom-name-converter.md)

## 🛠️ Development

Prerequisites:

- PHP `^8.3`
- Extensions: `json`

Install dependencies:

```bash
composer install
```

Run the mandatory quality gate:

```bash
composer ci:test
```

`ci:test` includes:

- Linting (`phplint`)
- Unit tests (`phpunit`)
- Static analysis (`phpstan`)
- Refactoring dry-run (`rector --dry-run`)
- Coding standards dry-run (`php-cs-fixer --dry-run`)
- Copy/paste detection (`jscpd`)

## 🤝 Contributing

See `CONTRIBUTING.md` for contributor workflow and minimal setup.

If contributions are prepared or modified by an LLM/agent, follow `AGENTS.md` (and `tests/AGENTS.md` for test-only scope).
