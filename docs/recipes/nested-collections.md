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

A collection is recognised by the two interfaces that let the mapper fill it and read it back:
`ArrayAccess`, so each element can be written through `offsetSet`, and `Traversable`, so the result
can be iterated. `ArrayObject` and its subclasses satisfy both, and so does a hand-rolled container.
A class that implements `Traversable` alone, without `ArrayAccess`, is not recognised as a container,
so a plain property typed as it maps its own fields as an ordinary object.

### Hand-rolled collections

A container does not have to extend `ArrayObject`. A class that keeps its elements in a declared
property is recognised just the same, as long as it implements both `ArrayAccess` and `Traversable`
and annotates what it yields. How it is filled then depends on its constructor. A container whose
constructor takes no arguments — like the `TagBag` below, which declares none — is built empty and
filled element by element through `offsetSet`, so a private storage array is no obstacle and no
list-accepting constructor is needed. A container whose constructor declares a parameter is handed
the whole list through that constructor instead, so a class that builds or validates from its
elements keeps doing so:

```php
/**
 * @implements IteratorAggregate<int, Tag>
 * @implements ArrayAccess<int, Tag>
 */
final class TagBag implements IteratorAggregate, ArrayAccess
{
    /** @var array<int, Tag> */
    private array $items = [];

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
```

A class that implements only `IteratorAggregate`, without `ArrayAccess`, is not recognised as a
container, so a plain property typed as it maps its own fields instead of being filled from the list.
Give it `ArrayAccess` to have the mapper treat it as a collection.

## Union and nullable element types

An element type may be a union or nullable. Each element is resolved against it on its own, so a
list may legitimately mix the members:

```php
final class Feed
{
    /**
     * @var array<int, Article|null>
     */
    public array $entries = [];
}
```

`[{"title": "Hello"}, null]` yields one `Article` and one `null`. A null element is only accepted
when the element type admits it; against `Article|string` it is rejected and reported like any
other mismatch.

Each element is resolved independently. A member whose scalar type the value already has wins before
any coercion, so a numeric string such as `"42"` stays a string against `int|string` rather than being
coerced to an int. This preference applies to scalar members only. Otherwise the members are tried in
a fixed order the declaration cannot influence (`int|string` and `string|int` are indistinguishable
once normalised), and the first member that converts the value without recording an error wins. A
fractional float such as `7.5` is an exact match for no member, and in that fallback the `int` member
refuses it as a lossy cast while the `string` member refuses a non-string scalar, so it matches no
member and is not truncated into one.

A value that matches no member is never coerced into one. In a collection the element is dropped and
reported at its own index, so the surviving elements keep their relative order and no element is
replaced by an invented value. A scalar property keeps its default, or stays uninitialised if it has
none, and the failure is reported. A lenient result never holds a value its declared type forbids.

A union or nullable element type is the only case where a `null` element survives. An element type
that does not admit null rejects one: the element is dropped and the failure reported, rather than
being turned into an object built from defaults.

Test coverage: `tests/JsonMapper/DocsNestedCollectionsTest.php`,
`tests/JsonMapper/SinglyNestedCollectionTest.php`,
`tests/JsonMapper/UnionCollectionElementTest.php` and
`tests/JsonMapper/UnionMemberSelectionTest.php`.

