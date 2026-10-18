<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionElementCollectionHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Union resolution existed on the property path only. A collection element declared as a union or
 * as nullable matched no strategy, so the passthrough returned the raw payload and the element was
 * never mapped - silently, since nothing was recorded.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
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
        // below is the control. Without it this assertion would also pass for a union that forbids
        // null, whenever a null is let through regardless of the element type.
        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->nullableItems[0]);
        self::assertNull($holder->nullableItems[1], 'A null element stays null rather than being rejected.');
    }

    #[Test]
    public function itAppliesTheNullGuardToElementsAsItDoesToProperties(): void
    {
        // The two entry points into value conversion gave different guarantees: a null was
        // rejected on a top-level property of the same declared type and silently accepted as a
        // collection element. Reporting it is half the contract - the element also has to be
        // dropped rather than stored, or the list holds a value its own docblock forbids while
        // the report claims it was handled.
        $result = $this->getJsonMapper()->mapWithReport(
            ['names' => ['a', null, 'c']],
            UnionElementCollectionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertSame(['a', 'c'], $holder->names, 'The rejected element is dropped, its siblings survive.');
        self::assertCount(1, $result->getReport()->getErrors());
    }

    #[Test]
    public function itMatchesALaterUnionMemberEvenInStrictMode(): void
    {
        // A candidate trial is an internal question, not a mapping the caller asked for. With
        // strict mode left on during the trial, the first candidate that failed aborted the whole
        // run - so this perfectly valid element raised "expected string, got stdClass", naming
        // whichever member happened to be tried first. The element is complete on purpose, so the
        // only thing under test is which member it settles on.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"unionItems": [{"id": 1, "name": "first"}]}'),
            UnionElementCollectionHolder::class,
            null,
            null,
            JsonMapperConfiguration::strict(),
        );

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->unionItems[0]);
    }

    #[Test]
    public function itReportsAPropertyTheMatchedUnionMemberLacksInStrictMode(): void
    {
        // The counterpart of the test above. The member is chosen on type alone, and strict mode
        // then still holds the element to its required properties, as it does for an element whose
        // type involves no union.
        $result = $this->getJsonMapper()->mapWithReport(
            $this->getJsonAsObject('{"unionItems": [{"name": "first"}]}'),
            UnionElementCollectionHolder::class,
            null,
            JsonMapperConfiguration::strict(),
        );

        $holder = $result->getValue();

        self::assertSame(
            ['$.unionItems.0.id'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
        );
        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertInstanceOf(Simple::class, $holder->unionItems[0]);
    }

    #[Test]
    public function itRaisesForAnIncompleteUnionElementEvenWhenNothingIsCollected(): void
    {
        // Strict mode aborts on the first failure whether or not failures are kept.
        $this->expectException(MissingPropertyException::class);

        $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"unionItems": [{"name": "first"}]}'),
            UnionElementCollectionHolder::class,
            null,
            null,
            JsonMapperConfiguration::strict()->withErrorCollection(false),
        );
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
        // The control for the test above. A null element must be checked against the element type,
        // or a null lands in a list whose declared type forbids it - no error, no dropped element.
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

    #[Test]
    public function itRejectsANullElementForANonNullableObjectType(): void
    {
        // The gap the null guard left open. A builtin element type reports the null, because the
        // builtin strategy refuses it - but an OBJECT element type fell through to the object
        // strategy, which happily instantiated a class needing no constructor arguments. So a
        // null in the payload became a fully-formed object with default values, and nothing was
        // recorded: a fabricated entry the caller cannot tell from a real one.
        $result = $this->getJsonMapper()->mapWithReport(
            $this->getJsonAsObject('{"objectItems": [{"name": "first"}, null]}'),
            UnionElementCollectionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionElementCollectionHolder::class, $holder);
        self::assertCount(1, $holder->objectItems, 'The null element is dropped, not invented.');
        self::assertSame(1, $result->getReport()->getErrorCount(), 'And it is reported.');
    }
}
