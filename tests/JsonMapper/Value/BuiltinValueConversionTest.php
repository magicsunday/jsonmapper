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
use MagicSunday\Test\Classes\FalseTypedPropertyHolder;
use MagicSunday\Test\Classes\IterablePropertyHolder;
use MagicSunday\Test\Classes\MixedPropertyHolder;
use MagicSunday\Test\Classes\TrueTypedPropertyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

use function preg_quote;

/**
 * Covers the builtin type identifiers settype() cannot handle. The holders whose type admits more
 * than one value seed a sentinel default, so an assertion on the mapped value also proves the
 * property was written rather than silently skipped. The two literal-typed holders cannot do that
 * - their type has a single inhabitant - so they are left uninitialized and their tests assert the
 * initialization state instead.
 *
 * @internal
 */
final class BuiltinValueConversionTest extends TestCase
{
    /**
     * Explains why the error count is pinned exactly rather than loosely: the throw inside the
     * strategy is the only recording path, and an earlier implementation that also consulted the
     * recording guard produced two entries for a single mismatch.
     */
    private const string SINGLE_RECORDING_PATH
        = 'The throw is the single recording path; an earlier implementation recorded 2.';

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

        // The property type admits exactly one value, so asserting the value proves nothing - a
        // mapper that never wrote it would look identical. The initialization state is what tells
        // an assignment apart from a skipped property.
        self::assertInstanceOf(TrueTypedPropertyHolder::class, $holder);
        self::assertTrue(
            (new ReflectionProperty($holder, 'flag'))->isInitialized($holder),
            'The true-typed property must have been written, not skipped.',
        );
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsAFalseTypedPropertyWithoutCastingIt(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['flag' => false],
            FalseTypedPropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(FalseTypedPropertyHolder::class, $holder);
        self::assertTrue(
            (new ReflectionProperty($holder, 'flag'))->isInitialized($holder),
            'The false-typed property must have been written, not skipped.',
        );
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
        self::assertSame(1, $result->getReport()->getErrorCount(), self::SINGLE_RECORDING_PATH);
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
        self::assertSame(1, $result->getReport()->getErrorCount(), self::SINGLE_RECORDING_PATH);
    }

    #[Test]
    public function itRecordsAMismatchForAValueThatDoesNotMatchAFalseTypedProperty(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['flag' => 'abc'],
            FalseTypedPropertyHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(FalseTypedPropertyHolder::class, $holder);
        self::assertSame(1, $result->getReport()->getErrorCount(), self::SINGLE_RECORDING_PATH);
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
        self::assertSame(1, $result->getReport()->getErrorCount(), self::SINGLE_RECORDING_PATH);
    }

    #[Test]
    public function itThrowsInStrictModeWhenAnIncompatibleValueCannotBeCast(): void
    {
        // The class alone would not discriminate: the recording guard raises the same type. The
        // property path pins that the rejection came from the non-castable branch.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('items', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['items' => 'nope'],
            IterablePropertyHolder::class,
        );
    }
}
