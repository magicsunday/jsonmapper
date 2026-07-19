<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionElementCollectionHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Union resolution existed on the property path only. A collection element declared as a union or
 * as nullable matched no strategy, so the passthrough returned the raw payload and the element was
 * never mapped - silently, since nothing was recorded.
 *
 * @internal
 */
final class UnionCollectionElementTest extends TestCase
{
    #[Test]
    public function itMapsObjectElementsOfANullableElementType(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"nullableItems": [{"name": "first"}, {"name": "second"}]}'),
            UnionElementCollectionHolder::class,
        );

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertContainsOnlyInstancesOf(Simple::class, $holder->nullableItems);
        self::assertSame('first', $holder->nullableItems[0]->name);
    }

    #[Test]
    public function itKeepsNullElementsOfANullableElementType(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"nullableItems": [{"name": "first"}, null]}'),
            UnionElementCollectionHolder::class,
        );

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->nullableItems[0]);
        self::assertNull($holder->nullableItems[1], 'A null element stays null rather than being rejected.');
    }

    #[Test]
    public function itResolvesEachElementOfAUnionElementType(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"unionItems": [{"name": "first"}, "a string"]}'),
            UnionElementCollectionHolder::class,
        );

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->unionItems[0]);
        self::assertSame('a string', $holder->unionItems[1], 'Each element resolves against the union on its own.');
    }
}
