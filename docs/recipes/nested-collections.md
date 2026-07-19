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
use MagicSunday\JsonMapper;
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

## Where the element type may live

A property typed with a collection class needs no generic docblock of its own — the class already
says what it holds:

```php
final class Article
{
    public TagCollection $tags;   // element type comes from TagCollection's own @extends
}
```

Naming it on the property works too, and takes precedence:

```php
/** @var TagCollection<int, Tag> */
public TagCollection $tags;
```

The annotation is what makes the class a collection. A container that declares none cannot be
filled, and the mapper says so rather than failing obscurely:

```
Unable to resolve the element type for collection [App\TagBag]. Define an "@extends"
annotation such as "@extends App\TagBag<YourClass>".
```

This is a defect in the class definition rather than in the payload, so it is raised in both
strict and lenient mode instead of being collected into the report.

Note that a collection is recognised by being a container — traversable or array-accessible, and
holding its contents in the type it inherits from. A data object that merely implements
`IteratorAggregate` and annotates what it yields keeps its own properties and is mapped as the
object it is.

### Current limitation

Recognition requires the class to declare no properties of its own, which holds for `ArrayObject`
and `ArrayIterator` subclasses because their storage is not visible to reflection. A hand-rolled
collection that keeps its elements in a declared property is therefore **not** recognised yet:

```php
final class TagBag implements IteratorAggregate
{
    /** @var array<int, Tag> */
    private array $items = [];   // visible to reflection, so not recognised as a container
}
```

Extend `ArrayObject` for now. Tracked in issue 97.

Test coverage: `tests/JsonMapper/DocsNestedCollectionsTest.php` and
`tests/JsonMapper/SinglyNestedCollectionTest.php`.

