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
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\Test\Classes\UnionScalarCollectionHolder;
use MagicSunday\Test\Classes\UnionScalarHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Member selection for a scalar union, both as a property and as a collection element.
 *
 * Two rules are pinned here: a value whose runtime type exactly matches one member keeps that
 * member rather than being coerced into whichever member the normalised order tries first, and a
 * value matching no member is rejected - a property keeps its default, a collection element is
 * dropped - with a recorded error. A float is never silently truncated into an int member.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class UnionMemberSelectionTest extends TestCase
{
    #[Test]
    public function itKeepsANumericStringAsAStringAgainstAnIntStringUnion(): void
    {
        // The normalised order of `int|string` tries int first, so a numeric string used to be
        // coerced to an int. A value whose runtime type is already one of the members must keep
        // that member: "42" arrived as a string and stays a string.
        $result = $this->getJsonMapper()->map(
            ['value' => '42'],
            UnionScalarHolder::class,
        );

        self::assertInstanceOf(UnionScalarHolder::class, $result);
        self::assertSame('42', $result->value, 'A numeric string keeps the exact string member rather than being coerced to int.');
    }

    #[Test]
    public function itKeepsANumericStringElementAsAStringInAUnionCollection(): void
    {
        // The same exact-match rule applies to a collection element, which is decided through the
        // same value-conversion entry point as a property.
        $holder = $this->getJsonMapper()->map(
            ['items' => ['42']],
            UnionScalarCollectionHolder::class,
        );

        self::assertInstanceOf(UnionScalarCollectionHolder::class, $holder);
        self::assertSame(['42'], $holder->items, 'A numeric string element keeps the exact string member.');
    }

    #[Test]
    public function itKeepsAnIntegerAsAnIntegerAgainstAnIntStringUnion(): void
    {
        // The positive control for the int member: an exact int match is not pushed to the string
        // member by the exact-match rule.
        $result = $this->getJsonMapper()->map(
            ['value' => 42],
            UnionScalarHolder::class,
        );

        self::assertInstanceOf(UnionScalarHolder::class, $result);
        self::assertSame(42, $result->value, 'An integer keeps the exact int member.');
    }

    #[Test]
    public function itRejectsALossyFloatAgainstAnIntStringUnion(): void
    {
        // A fractional float matches neither member. The int member refuses it as lossy and the
        // string member refuses a non-string scalar. It is reported, and the property keeps its
        // default - it is never silently truncated to an int.
        $result = $this->getJsonMapper()->mapWithReport(
            ['value' => 7.5],
            UnionScalarHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionScalarHolder::class, $holder);
        self::assertSame('untouched', $holder->value, 'A lossy float is rejected, not truncated into the int member.');
        self::assertSame(1, $result->getReport()->getErrorCount(), 'The lossy float is reported exactly once.');
    }

    #[Test]
    public function itDropsUnionCollectionElementsMatchingNoMemberAndReportsEachByIndex(): void
    {
        // A mixed list against `int|string`: the int and the string survive, the lossy float and the
        // bool match no member and are dropped, each reported at its own index. The surviving
        // elements keep their position relative to one another, and a dropped element is not replaced
        // by an invented value.
        $result = $this->getJsonMapper()->mapWithReport(
            ['items' => [42, 'abc', 7.5, true]],
            UnionScalarCollectionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionScalarCollectionHolder::class, $holder);
        self::assertSame([42, 'abc'], $holder->items, 'The int and string survive; the float and bool are dropped.');
        self::assertSame(
            ['$.items.2', '$.items.3'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
            'Each rejected element is reported at its own index.',
        );
    }
}
