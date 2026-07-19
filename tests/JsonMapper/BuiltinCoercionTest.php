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
     * @return array<string, array{string, int|float|bool|string, int|float|bool|string}>
     */
    public static function coercedValueProvider(): array
    {
        return [
            'int to string'           => ['text', 42, '42'],
            'float to string'         => ['text', 1.5, '1.5'],
            'bool true to string'     => ['text', true, '1'],
            'bool false to string'    => ['text', false, ''],
            'numeric to int'          => ['number', '42', 42],
            'bool true to int'        => ['number', true, 1],
            'bool false to int'       => ['number', false, 0],
            'float truncates to int'  => ['number', 3.9, 3],
            'non numeric to int'      => ['number', 'abc', 0],
            'int to float'            => ['decimal', 3, 3.0],
            'numeric to float'        => ['decimal', '2.5', 2.5],
            'bool to float'           => ['decimal', true, 1.0],
            'non numeric to float'    => ['decimal', 'abc', 0.0],
            'literal true'            => ['flag', 'true', true],
            'padded mixed case true'  => ['flag', '  TRUE  ', true],
            'non empty to bool'       => ['flag', 'yes', true],
            'int one to bool'         => ['flag', 1, true],
            'int to bool'             => ['flag', 5, true],
            'literal false'           => ['flagSeededTrue', 'false', false],
            'padded mixed case false' => ['flagSeededTrue', '  FALSE  ', false],
            'numeric string zero'     => ['flagSeededTrue', '0', false],
            'int zero to bool'        => ['flagSeededTrue', 0, false],
            'float zero to bool'      => ['flagSeededTrue', 0.0, false],
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
     */
    #[Test]
    #[DataProvider('coercedValueProvider')]
    public function itCoercesAMismatchingScalarInLenientMode(
        string $property,
        int|float|bool|string $payload,
        int|float|bool|string $expected,
    ): void {
        $result = $this->getJsonMapper()->mapWithReport(
            [$property => $payload],
            BuiltinCoercionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(BuiltinCoercionHolder::class, $holder);
        self::assertSame($expected, (new ReflectionProperty($holder, $property))->getValue($holder));
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

        // Lenient mode reports the coercion it performed, as it does for every other mismatching
        // pair here. What this pins is that the value ARRIVES rather than being rejected.
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

        // Lenient mode reports the coercion it performed, exactly as it does for the scalar pairs
        // above - what matters here is that the value arrives rather than being discarded.
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
        self::assertSame(1, $result->getReport()->getErrorCount());
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
        self::assertSame(1, $result->getReport()->getErrorCount());
    }
}
