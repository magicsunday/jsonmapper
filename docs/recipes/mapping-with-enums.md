# Mapping JSON to PHP enums

JsonMapper can map backed enums transparently when the target property is typed with the enum class. The built-in `EnumValueConversionStrategy` handles the conversion from scalars to enum cases.

```php
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
use App\Dto\Article;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\JsonMapper;

$json = json_decode('{
    "title": "Enum mapping", 
    "status": "published"
}', associative: false, flags: JSON_THROW_ON_ERROR);

// Create PropertyInfoExtractor and PropertyAccessor instances as shown in the quick start guide.
$mapper = new JsonMapper($propertyInfo, $propertyAccessor);
$article = $mapper->map($json, Article::class);

assert($article instanceof Article);
assert($article->status === Status::Published);
```

The mapper validates enum values. When strict mode is enabled (`JsonMapperConfiguration::strict()`), an invalid enum value results in a `TypeMismatchException`.
