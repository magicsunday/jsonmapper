# Mapping JSON to PHP enums

JsonMapper can map backed enums transparently when the target property is typed with the enum class. The built-in `EnumValueConversionStrategy` handles the conversion from scalars to enum cases.

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

The mapper validates enum values. In strict mode (`JsonMapperConfiguration::strict()`), an invalid enum value results in a `TypeMismatchException` instead of populating the property.

Test coverage: `tests/JsonMapperTest.php::mapBackedEnumFromString` and `tests/JsonMapper/JsonMapperErrorHandlingTest.php::itReportsInvalidEnumValuesInLenientMode`.
