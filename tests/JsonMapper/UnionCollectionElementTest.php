<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
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

        // The null member of the element type is what permits this. The non-nullable sibling
        // below is the control: without it this assertion would pass for a union forbidding null
        // too, since a value-keyed null strategy claims every null before the type is inspected.
        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->nullableItems[0]);
        self::assertNull($holder->nullableItems[1], 'A null element stays null rather than being rejected.');
    }

    #[Test]
    public function itMatchesALaterUnionMemberEvenInStrictMode(): void
    {
        // A candidate trial is an internal question, not a mapping the caller asked for. With
        // strict mode left on during the trial, the first candidate that failed aborted the whole
        // run - so this perfectly valid element raised "expected string, got stdClass", naming
        // whichever member happened to be tried first.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"unionItems": [{"name": "first"}]}'),
            UnionElementCollectionHolder::class,
            null,
            null,
            JsonMapperConfiguration::strict(),
        );

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->unionItems[0]);
    }

    #[Test]
    public function itStillRaisesInStrictModeWhenNoMemberMatches(): void
    {
        // The control for the test above: overriding strict mode for the trial must not swallow a
        // genuine failure. Once every candidate has actually been tried, the caller still raises.
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"nonNullableItems": [null]}'),
            UnionElementCollectionHolder::class,
            null,
            null,
            JsonMapperConfiguration::strict(),
        );
    }

    #[Test]
    public function itRejectsANullElementOfANonNullableElementType(): void
    {
        // The control for the test above. A null element used to be claimed by the null strategy
        // before the element type was ever consulted, so a null landed in a list whose declared
        // type forbids it - no error, no dropped element.
        $result = $this->getJsonMapper()->mapWithReport(
            ['nonNullableItems' => [null, 'ok']],
            UnionElementCollectionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertSame(['ok'], $holder->nonNullableItems, 'The valid sibling survives, the null is dropped.');
        self::assertCount(1, $result->getReport()->getErrors());
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
