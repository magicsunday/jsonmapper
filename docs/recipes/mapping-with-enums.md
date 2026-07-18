# Mapping JSON to PHP enums

JsonMapper can map enums transparently when the target property is typed with the enum class. The built-in `EnumValueConversionStrategy` handles the conversion from scalars to enum cases.

A **backed** enum is addressed by case value, a **pure** enum by case name - its cases carry no scalar value, so the name is the only thing a payload can refer to. The name comparison is exact, since a case name is an identifier rather than a value.

```php
<?php
declare(strict_types=1);

namespace App\Dto;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class Article
{
    public string $title;
    public Status $status;
}
```

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Dto\Article;
use App\Dto\Status;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Decode the incoming JSON payload into an object.
$json = json_decode('{
    "title": "Enum mapping",
    "status": "published"
}', associative: false, flags: JSON_THROW_ON_ERROR);

// Wire up the mapper with reflection and PhpDoc metadata.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);
$article = $mapper->map($json, Article::class);

assert($article instanceof Article);
assert($article->status === Status::Published);
```

## Pure enums

```php
enum Color
{
    case Red;
    case Blue;
}

final class Shape
{
    public Color $color;
}

// {"color": "Red"} maps to Color::Red - "red" does not, the name must match exactly.
```

## Validation

The mapper validates enum values. In strict mode (`JsonMapperConfiguration::strict()`), a value that names no case results in a `TypeMismatchException` instead of populating the property; in lenient mode it is recorded in the mapping report and the property is left alone - keeping its default, or staying uninitialised if it never had one.

Inside a collection only the offending element is dropped; the valid ones are still mapped.

That covers every rejection reason alike: a value outside the declared cases, a scalar that does not match a backed enum's backing type (a string for an int-backed enum, for instance), and a name that matches no case of a pure enum.

Test coverage: `tests/JsonMapperTest.php::mapBackedEnumFromString`, `tests/JsonMapper/JsonMapperErrorHandlingTest.php::itReportsInvalidEnumValuesInLenientMode`, `tests/JsonMapper/Value/EnumValueConversionTest.php` and `tests/JsonMapper/Value/UnitEnumValueConversionTest.php`.
