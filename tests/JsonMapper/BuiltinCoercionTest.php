<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Classes\BuiltinCoercionHolder;
use MagicSunday\Test\Classes\StringableValue;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * Pins which scalar payloads lenient mode coerces and which it rejects.
 *
 * This matrix was entirely uncovered, which let a change that widened rejection from "cannot be
 * cast at all" to "any type mismatch" pass 178 green tests while silently dropping every value
 * lenient mode used to absorb.
 *
 * @internal
 */
final class BuiltinCoercionTest extends TestCase
{
    /**
     * Payloads that lenient mode converts rather than rejects. A scalar reaching a differently
     * typed scalar property is exactly the schema drift lenient mode exists to absorb.
     *
     * The last column says whether the conversion shows up in the report, and the split is not
     * cosmetic. normalizeValue() recognises a payload as a *representation* of the target type -
     * the string '42' IS the number 42 - and rewrites it before the compatibility guard ever runs,
     * so nothing is recorded. Everything else reaches the guard as a genuine mismatch, gets
     * recorded, and is then cast. Documenting the whole table as "recorded" would be wrong for
     * more than half of it.
     *
     * @return array<string, array{string, int|float|bool|string, int|float|bool|string, bool}>
     */
    public static function coercedValueProvider(): array
    {
        return [
            // Normalised silently - the payload is a representation of the target type.
            'numeric to int'          => ['number', '42', 42, false],
            'float truncates to int'  => ['number', 3.9, 3, false],
            'int to float'            => ['decimal', 3, 3.0, false],
            'numeric to float'        => ['decimal', '2.5', 2.5, false],
            'literal true'            => ['flag', 'true', true, false],
            'numeric string one'      => ['flag', '1', true, false],
            'padded mixed case true'  => ['flag', '  TRUE  ', true, false],
            'int one to bool'         => ['flag', 1, true, false],
            'literal false'           => ['flagSeededTrue', 'false', false, false],
            'padded mixed case false' => ['flagSeededTrue', '  FALSE  ', false, false],
            'numeric string zero'     => ['flagSeededTrue', '0', false, false],
            'int zero to bool'        => ['flagSeededTrue', 0, false, false],

            // Cast and recorded - a genuine mismatch the guard sees.
            'int to string'        => ['text', 42, '42', true],
            'float to string'      => ['text', 1.5, '1.5', true],
            'bool true to string'  => ['text', true, '1', true],
            'bool false to string' => ['text', false, '', true],
            'bool true to int'     => ['number', true, 1, true],
            'bool false to int'    => ['number', false, 0, true],
            'non numeric to int'   => ['number', 'abc', 0, true],
            'bool to float'        => ['decimal', true, 1.0, true],
            'non numeric to float' => ['decimal', 'abc', 0.0, true],
            'non empty to bool'    => ['flag', 'yes', true, true],
            'int to bool'          => ['flag', 5, true, true],
            'float zero to bool'   => ['flagSeededTrue', 0.0, false, true],
        ];
    }

    /**
     * Payloads with no meaningful cast. settype() would not refuse them: the string target writes
     * the literal 'Array' and warns, the bool/int/float targets silently yield true/1/1.0. Both
     * kinds are rejected instead of handed to the caller.
     *
     * @return array<string, array{string, array<int, string>|object}>
     */
    public static function rejectedValueProvider(): array
    {
        return [
            'array to string'  => ['text', ['a', 'b']],
            'array to int'     => ['number', ['a']],
            'array to float'   => ['decimal', ['a']],
            'array to bool'    => ['flag', ['a']],
            'object to string' => ['text', (object) ['a' => 1]],
        ];
    }

    /**
     * @param string                $property Property receiving the payload value.
     * @param int|float|bool|string $payload  Value handed to the mapper.
     * @param int|float|bool|string $expected Value the property must hold afterwards.
     * @param bool                  $recorded Whether the conversion appears in the report.
     */
    #[Test]
    #[DataProvider('coercedValueProvider')]
    public function itCoercesAMismatchingScalarInLenientMode(
        string $property,
        int|float|bool|string $payload,
        int|float|bool|string $expected,
        bool $recorded,
    ): void {
        $result = $this->getJsonMapper()->mapWithReport(
            [$property => $payload],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertSame($expected, (new ReflectionProperty($holder, $property))->getValue($holder));

        // The value arriving is only half the contract. A cast that the guard saw has to stay
        // visible in the report; a payload that normalizeValue() recognised outright must not
        // produce noise. Without this both directions could regress unnoticed.
        self::assertSame(
            $recorded ? 1 : 0,
            $result->getReport()->getErrorCount(),
            $recorded
                ? 'A cast the guard saw stays visible in the report.'
                : 'A recognised representation is not a mismatch and must not be reported.',
        );
    }

    #[Test]
    public function itLeavesANoDefaultPropertyUninitialisedWhenRejected(): void
    {
        // Every property in rejectedValueProvider() carries a default, so none of those rows can
        // show what happens without one. The documentation promises the property is left
        // uninitialised rather than filled with something invented.
        $result = $this->getJsonMapper()->mapWithReport(
            ['required' => ['a']],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertFalse(
            (new ReflectionProperty($holder, 'required'))->isInitialized($holder),
            'A rejected value must not be substituted by a fabricated one.',
        );
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itStillCastsAnObjectOntoAnArrayProperty(): void
    {
        // array and object are castable targets, so a composite reaching them has a meaningful
        // cast and must not be swept up by the composite rejection. The rejection has to look at
        // the target type, not only at the value.
        $result = $this->getJsonMapper()->mapWithReport(
            ['bag' => (object) ['a' => 'one']],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertSame(['a' => 'one'], $holder->bag);

        // A composite is never a recognised representation, so it always reaches the guard and is
        // reported. What this pins is that the value ARRIVES rather than being rejected.
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itStillCastsAnArrayOntoAnObjectProperty(): void
    {
        // The more damaging direction: the property has no default, so a wrongly rejected value
        // leaves it uninitialised and every later read raises an Error.
        $result = $this->getJsonMapper()->mapWithReport(
            ['thing' => ['a' => 'one']],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertTrue(
            (new ReflectionProperty($holder, 'thing'))->isInitialized($holder),
            'The object property must have been written.',
        );

        // isInitialized() alone would pass for any written value, including a wrong one.
        self::assertEquals((object) ['a' => 'one'], $holder->thing);

        // Reported for the same reason as the direction above - what matters here is that the
        // value arrives rather than being discarded.
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    /**
     * @param string                    $property Property receiving the payload value.
     * @param array<int, string>|object $payload  Composite value with no meaningful cast.
     */
    #[Test]
    #[DataProvider('rejectedValueProvider')]
    public function itRejectsACompositeValueOnAScalarProperty(string $property, array|object $payload): void
    {
        $holderDefaults = new BuiltinCoercionHolder();

        $result = $this->getJsonMapper()->mapWithReport(
            [$property => $payload],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertSame(
            (new ReflectionProperty($holderDefaults, $property))->getValue($holderDefaults),
            (new ReflectionProperty($holder, $property))->getValue($holder),
            'A value with no meaningful cast must leave the property untouched.',
        );
        self::assertSame(
            1,
            $result->getReport()->getErrorCount(),
            'One rejection record, not one per attempted cast.',
        );
    }

    #[Test]
    public function itRejectsAStringableObjectOnAStringProperty(): void
    {
        // The one composite that settype() would convert meaningfully: it honours __toString() and
        // would yield 'hi'. The rejection is by target type, so this is swept up with the rest -
        // a deliberate trade, pinned here because nothing else records it. Only reachable when
        // mapping a PHP array; a json_decode() payload cannot carry a Stringable.
        $result = $this->getJsonMapper()->mapWithReport(
            ['text' => new StringableValue()],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertSame('sentinel', $holder->text, 'The Stringable is rejected, not stringified.');
        self::assertSame(
            1,
            $result->getReport()->getErrorCount(),
            'One rejection record, not one per attempted cast.',
        );
    }
}
