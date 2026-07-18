<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use MagicSunday\Test\Classes\IterablePropertyHolder;
use MagicSunday\Test\Classes\MixedPropertyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class BuiltinValueConversionTest extends TestCase
{
    /**
     * Values that must survive a mixed-typed property untouched. settype() has no "mixed" mode,
     * so every one of these used to abort the whole mapping with a ValueError.
     *
     * @return array<string, array{mixed}>
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

    #[Test]
    #[DataProvider('mixedValueProvider')]
    public function itPassesValuesThroughAMixedTypedPropertyUnchanged(mixed $value): void
    {
        $result = $this->getJsonMapper()->map(
            ['value' => $value],
            MixedPropertyHolder::class,
        );

        self::assertInstanceOf(MixedPropertyHolder::class, $result);
        self::assertSame($value, $result->value);
    }

    #[Test]
    public function itAssignsAnIterablePropertyWithoutCastingIt(): void
    {
        $result = $this->getJsonMapper()->map(
            ['items' => [1, 2, 3]],
            IterablePropertyHolder::class,
        );

        self::assertInstanceOf(IterablePropertyHolder::class, $result);
        self::assertSame([1, 2, 3], $result->items);
    }
}
