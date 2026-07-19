<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\Docs\NestedCollections\IterableDataObjectHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\SinglyNestedArticle;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Tag;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * A property typed with a collection class whose element type is advertised by the class's own
 * "extends" annotation - the most common collection shape there is, and the one the suite missed.
 *
 * Two green paths sidestepped it. The recipe test exercises the DOUBLY nested shape, and the
 * repository's own collection fixtures name the element type on the PROPERTY docblock rather than
 * relying on the collection class. Neither reaches the resolution this pins, so the shape both the
 * README and the recipe document went untested.
 *
 * @internal
 */
final class SinglyNestedCollectionTest extends TestCase
{
    #[Test]
    public function itMapsACollectionPropertyWhoseElementTypeComesFromTheClassAnnotation(): void
    {
        $article = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            SinglyNestedArticle::class,
        );

        self::assertInstanceOf(SinglyNestedArticle::class, $article);

        // No assertion that $article->tags is a TagCollection: the native property type already
        // guarantees that, and the defect was never a wrong type landing there. It was the raw
        // array reaching the property accessor, which threw a foreign InvalidTypeException -
        // outside the MappingException hierarchy, so mapWithReport() could not report it either.
        // Reaching these assertions at all is the proof; their content pins the element mapping.
        self::assertCount(2, $article->tags);
        self::assertContainsOnlyInstancesOf(Tag::class, $article->tags);

        // Asserting the whole projection rather than element 0: a factory that mapped the first
        // element twice would satisfy the count, the element type and a single-element check.
        self::assertSame(
            ['php', 'json'],
            array_map(
                static fn (Tag $tag): string => $tag->name,
                $article->tags->getArrayCopy(),
            ),
            'Both payload elements map, in payload order.',
        );
    }

    #[Test]
    public function itLeavesAnIterableDataObjectToTheObjectStrategy(): void
    {
        // The negative control. Recognising a collection by its element annotation alone would
        // claim this too - it implements IteratorAggregate and says what it yields - and the
        // factory would build it from the payload's elements, dropping both properties while
        // still producing an object of the right class. The failure is silent by construction,
        // which is why the positive test alone is not enough.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"payload": {"title": "hello", "count": 7}}'),
            IterableDataObjectHolder::class,
        );

        self::assertInstanceOf(IterableDataObjectHolder::class, $holder);
        self::assertSame('hello', $holder->payload->title);
        self::assertSame(7, $holder->payload->count);
    }
}
