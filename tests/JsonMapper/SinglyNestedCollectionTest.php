<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\Docs\NestedCollections\SinglyNestedArticle;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Tag;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

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

        $first = $article->tags[0];

        self::assertInstanceOf(Tag::class, $first);
        self::assertSame('php', $first->name);
    }
}
