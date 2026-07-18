<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use ArrayIterator;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\CallableDocBlockPropertyHolder;
use MagicSunday\Test\Classes\IterablePropertyHolder;
use MagicSunday\Test\Classes\MixedPropertyHolder;
use MagicSunday\Test\Classes\TrueTypedPropertyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the builtin type identifiers settype() cannot handle. Every holder used here seeds a
 * sentinel default, so an assertion on the mapped value also proves the property was written
 * rather than silently skipped.
 *
 * @internal
 */
final class BuiltinValueConversionTest extends TestCase
{
    /**
     * Values that must survive a mixed-typed property untouched. settype() has no "mixed" mode,
     * so every one of these used to abort the whole mapping with a ValueError.
     *
     * @return array<string, array{string|array<int, int>|int|float|bool}>
     */
    public static function mixedValueProvider(): array
    {
        return [
            'string'  => ['hello'],
            'array'   => [[1, 2]],
            'integer' => [5],
            'float'   => [1.5],
            'boolean' => [true],
        ];
    }

    /**
     * @param string|array<int, int>|int|float|bool $value Value handed to the mixed-typed property.
     */
    #[Test]
    #[DataProvider('mixedValueProvider')]
    public function itPassesValuesThroughAMixedTypedPropertyUnchanged(
        string|array|int|float|bool $value,
    ): void {
        $result = $this->getJsonMapper()->mapWithReport(
            ['value' => $value],
            MixedPropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(MixedPropertyHolder::class, $holder);
        self::assertSame($value, $holder->value);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsAnIterablePropertyWithoutCastingIt(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['items' => [1, 2, 3]],
            IterablePropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IterablePropertyHolder::class, $holder);
        self::assertSame([1, 2, 3], $holder->items);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsATraversableToAnIterablePropertyByIdentity(): void
    {
        // An array survives several conversion paths incidentally. A Traversable is what proves
        // that no cast happened at all, since settype() could never have produced this object.
        $iterator = new ArrayIterator([1, 2, 3]);

        $result = $this->getJsonMapper()->mapWithReport(
            ['items' => $iterator],
            IterablePropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IterablePropertyHolder::class, $holder);
        self::assertSame($iterator, $holder->items);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsACallableTypedPropertyWithoutCastingIt(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['handler' => 'strlen'],
            CallableDocBlockPropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(CallableDocBlockPropertyHolder::class, $holder);
        self::assertSame('strlen', $holder->handler);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsATrueTypedPropertyWithoutCastingIt(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['flag' => true],
            TrueTypedPropertyHolder::class,
        );

        $holder = $result->getValue();

        // The property type admits exactly one value, so there is nothing to assert about the
        // value itself - what this pins is that the identifier no longer aborts the mapping.
        self::assertInstanceOf(TrueTypedPropertyHolder::class, $holder);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itRecordsAMismatchInsteadOfAssigningAnIncompatibleIterableValue(): void
    {
        // Without a settype() equivalent there is no coercion available, so the value must be
        // rejected as a mapping error rather than reaching the property assignment - which would
        // abort the whole mapping with an exception from outside the error-collection contract.
        $result = $this->getJsonMapper()->mapWithReport(
            ['items' => 'nope'],
            IterablePropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IterablePropertyHolder::class, $holder);
        self::assertSame(['preset'], $holder->items);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itRecordsAMismatchForAValueThatDoesNotMatchATrueTypedProperty(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['flag' => 'abc'],
            TrueTypedPropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(TrueTypedPropertyHolder::class, $holder);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itRejectsNullOnANonNullableIterableProperty(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['items' => null],
            IterablePropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IterablePropertyHolder::class, $holder);
        self::assertSame(['preset'], $holder->items);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itThrowsInStrictModeWhenAnIncompatibleValueCannotBeCast(): void
    {
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['items' => 'nope'],
            IterablePropertyHolder::class,
        );
    }
}
