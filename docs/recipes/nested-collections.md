# Mapping nested collections

Collections of collections require explicit metadata so JsonMapper can determine the element types at every level.

```php
namespace App\Dto;

/**
 * @extends \ArrayObject<int, Tag>
 */
final class TagCollection extends \ArrayObject
{
}

/**
 * @extends \ArrayObject<int, TagCollection>
 */
final class NestedTagCollection extends \ArrayObject
{
}

final class Article
{
    /** @var NestedTagCollection */
    public NestedTagCollection $tags;
}
```

```php
use App\Dto\Article;
use App\Dto\NestedTagCollection;
use App\Dto\Tag;
use App\Dto\TagCollection;
use MagicSunday\JsonMapper\JsonMapper;

$json = json_decode('[
    {
        "tags": [
            [{"name": "php"}],
            [{"name": "json"}]
        ]
    }
]', associative: false, flags: JSON_THROW_ON_ERROR);

// Create PropertyInfoExtractor and PropertyAccessor instances as shown in the quick start guide.
$mapper = new JsonMapper($propertyInfo, $propertyAccessor);
$articles = $mapper->map($json, Article::class, \ArrayObject::class);

assert($articles instanceof \ArrayObject);
assert($articles[0] instanceof Article);
assert($articles[0]->tags instanceof NestedTagCollection);
```

Each custom collection advertises its value type through the `@extends` PHPDoc annotation, allowing the mapper to recurse through nested structures.
