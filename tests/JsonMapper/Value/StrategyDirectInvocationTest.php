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
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Value\Strategy\BuiltinValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\DateTimeValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\ObjectValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\PassthroughValueConversionStrategy;
use MagicSunday\JsonMapper\Value\Strategy\ValueConversionStrategyInterface;
use MagicSunday\JsonMapper\Value\ValueConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Several guards inside the strategies are unreachable through the value converter's chain: the
 * chain answers the question first. NullValueConversionStrategy claims every null before a strategy
 * that guards against one runs, and supports() declines a type before convert() could be handed it.
 *
 * The strategies are internal rather than an extension point, so the case those guards defend is not
 * a consumer calling one directly - it is the chain changing. Reorder it, or let a type through
 * supports() that did not get through before, and a guard that was decoration becomes load-bearing.
 * These drive each guard on its own so that a future "this branch is never hit" cleanup meets a red
 * test rather than a silent contract loss.
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
    public function theBuiltinStrategyReturnsNullForAnIdentifierThatAcceptsOne(): void
    {
        // mixed makes no claim about its value, so a null satisfies it - and there is no settype()
        // equivalent to run. The chain never asks: NullValueConversionStrategy claims the null two
        // strategies earlier.
        $strategy = new BuiltinValueConversionStrategy();

        self::assertNull($strategy->convert(new BuiltinType(TypeIdentifier::MIXED), null, new MappingContext([])));
    }

    #[Test]
    public function theBuiltinStrategyRecordsANonNullValueForTheNullType(): void
    {
        // The literal null type accepts exactly one value, and the compatibility table has an arm
        // for it. Recorded rather than thrown: the value IS castable, so this is the ordinary
        // coercion lane, which reports the loss instead of aborting on it.
        $strategy = new BuiltinValueConversionStrategy();
        $context  = new MappingContext([]);

        self::assertNull($strategy->convert(new BuiltinType(TypeIdentifier::NULL), 'x', $context));

        $records = $context->getErrorRecords();

        self::assertCount(1, $records);

        $exception = $records[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('null', $exception->getExpectedType());
        self::assertSame('string', $exception->getActualType());
    }

    #[Test]
    public function theObjectStrategyRefusesATypeThatIsNotAnObjectType(): void
    {
        // supports() declines a non-object type, so the chain never calls convert() with one. The
        // guard turns a would-be dereference of a missing class name into a statement about the
        // caller's mistake.
        $strategy = new ObjectValueConversionStrategy(
            new ClassResolver(),
            static fn (mixed $value, string $className, MappingContext $context): mixed => $value,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/requires an object type/');

        $strategy->convert(new BuiltinType(TypeIdentifier::INT), 1, new MappingContext([]));
    }

    #[Test]
    public function theObjectStrategyRefusesAnObjectTypeWithoutAClassName(): void
    {
        // An object type carrying no class name names nothing to instantiate. Reported as the
        // caller's mistake rather than passed to the resolver, which would report it as a class
        // that does not exist - a different problem with a different fix.
        $strategy = new ObjectValueConversionStrategy(
            new ClassResolver(),
            static fn (mixed $value, string $className, MappingContext $context): mixed => $value,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must define a class-string/');

        $strategy->convert(new ObjectType(''), [], new MappingContext([]));
    }

    #[Test]
    public function theObjectGuardHandsBackAValueItCannotIdentifyATargetFor(): void
    {
        // The trait's own version of the question, answered the other way: where the object
        // strategy raises, a strategy built on the guard trait returns the value untouched, because
        // it has no class to convert towards. Driven through the date strategy, which uses it.
        $strategy = new DateTimeValueConversionStrategy();

        self::assertSame('untouched', $strategy->convert(new ObjectType(''), 'untouched', new MappingContext([])));
    }

    #[Test]
    public function thePassthroughStrategyClaimsEverythingAndChangesNothing(): void
    {
        // The chain's final fallback, and the reason the converter's own no-strategy-matched guard
        // is unreachable in production. Nothing ahead of it declines a type it would have to
        // handle, so it is never reached there - which is exactly what makes it worth pinning:
        // whatever does reach it must come back unchanged rather than half-converted.
        $strategy = new PassthroughValueConversionStrategy();
        $context  = new MappingContext([]);
        $value    = ['left' => 'alone'];

        self::assertTrue($strategy->supports(new BuiltinType(TypeIdentifier::INT), $value, $context));
        self::assertSame($value, $strategy->convert(new BuiltinType(TypeIdentifier::INT), $value, $context));
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
