<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use DateTime;
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
use Symfony\Component\TypeInfo\Type\ObjectType;
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

        // The message pins THIS guard: TypeMismatchException is thrown from several sites in the
        // strategy, and a future refactor routing null down a different one would otherwise keep
        // this test green while covering the wrong branch. Matches() not Message() - the latter is
        // deprecated across the PHPUnit majors the constraint spans.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/expected int, got null/');

        $strategy->convert(new BuiltinType(TypeIdentifier::INT), null, new MappingContext([]));
    }

    #[Test]
    public function theObjectGuardRefusesANullForANonNullableType(): void
    {
        // The object-trait counterpart of the builtin guard above, and the one the first round of
        // tests missed. Through the chain a null is claimed by NullValueConversionStrategy first;
        // a direct call reaches guardNullableValue, which must keep a null off a non-nullable
        // object target rather than let convertObjectValue return it.
        $strategy = new DateTimeValueConversionStrategy();

        $this->expectException(TypeMismatchException::class);

        $strategy->convert(new ObjectType(DateTime::class), null, new MappingContext([]));
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
