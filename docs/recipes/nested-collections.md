# Mapping nested collections

Collections of collections require explicit metadata so JsonMapper can determine the element types at every level.

```php
<?php
declare(strict_types=1);

namespace App\Dto;

use ArrayObject;

final class Tag
{
    public string $name;
}

/**
 * @extends ArrayObject<int, Tag>
 */
final class TagCollection extends ArrayObject
{
}

/**
 * @extends ArrayObject<int, TagCollection>
 */
final class NestedTagCollection extends ArrayObject
{
}

final class Article
{
    /** @var NestedTagCollection */
    public NestedTagCollection $tags;
}

/**
 * @extends ArrayObject<int, Article>
 */
final class ArticleCollection extends ArrayObject
{
}
```

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Dto\Article;
use App\Dto\ArticleCollection;
use App\Dto\NestedTagCollection;
use App\Dto\TagCollection;
use MagicSunday\JsonMapper\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Decode an array of nested tag collections.
$json = json_decode('[
    {
        "tags": [
            [{"name": "php"}],
            [{"name": "json"}]
        ]
    }
]', associative: false, flags: JSON_THROW_ON_ERROR);

// Configure JsonMapper with the extractors that understand collection metadata.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();


// Map the nested structure into DTOs and typed collections.
$mapper = new JsonMapper($propertyInfo, $propertyAccessor);
$articles = $mapper->map($json, Article::class, ArticleCollection::class);

assert($articles instanceof ArticleCollection);
assert($articles[0] instanceof Article);
assert($articles[0]->tags instanceof NestedTagCollection);
```

Each custom collection advertises its value type through the `@extends` PHPDoc annotation, allowing the mapper to recurse through nested structures.
