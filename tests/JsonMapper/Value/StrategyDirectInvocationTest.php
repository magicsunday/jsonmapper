<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Value\Strategy\BuiltinValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\DateTimeValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\ValueConversionStrategyInterface;
use MagicSunday\JsonMapper\Value\ValueConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * The strategy classes are public SPI: a caller may register its own and, in doing so, may invoke a
 * strategy directly rather than through the value converter's chain. Several guards inside them are
 * unreachable through that chain - NullValueConversionStrategy claims every null before they run -
 * but not dead: they defend exactly the direct-invocation case. These pin that they still hold, so
 * a future "this branch is never hit" cleanup meets a red test rather than a silent contract loss.
 *
 * @internal
 */
final class StrategyDirectInvocationTest extends TestCase
{
    #[Test]
    public function theBuiltinStrategyRefusesANullForANonNullableType(): void
    {
        $strategy = new BuiltinValueConversionStrategy();

        $this->expectException(TypeMismatchException::class);

        $strategy->convert(new BuiltinType(TypeIdentifier::INT), null, new MappingContext([]));
    }

    #[Test]
    public function theObjectGuardHandsBackAValueForANonObjectType(): void
    {
        // The extractObjectType-is-null branch of the shared trait: reached only by a direct call
        // that skips supports(), which would have returned false for a builtin type. It returns the
        // value untouched rather than dereferencing a null object type.
        $strategy = new DateTimeValueConversionStrategy();

        $result = $strategy->convert(
            new BuiltinType(TypeIdentifier::INT),
            'left-untouched',
            new MappingContext([]),
        );

        self::assertSame('left-untouched', $result);
    }

    #[Test]
    public function theConverterRaisesWhenNoStrategyMatches(): void
    {
        // The invariant guard: with no passthrough registered and a type nothing supports, the loop
        // finds no strategy. In production PassthroughValueConversionStrategy (supports() always
        // true, registered last) makes this unreachable - this drives it directly.
        $converter = new ValueConverter();
        $converter->addStrategy(new class implements ValueConversionStrategyInterface {
            public function supports(Type $type, mixed $value, MappingContext $context): bool
            {
                return false;
            }

            public function convert(Type $type, mixed $value, MappingContext $context): mixed
            {
                return $value;
            }
        });

        $this->expectException(LogicException::class);

        $converter->convert(new BuiltinType(TypeIdentifier::INT), 'x', new MappingContext([]));
    }
}
