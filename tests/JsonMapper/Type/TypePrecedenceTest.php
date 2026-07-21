<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Type;

use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Fixtures\TypePrecedence\AccessorWideningHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\ParentTypedConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\RefiningPropertyHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\ScalarWideningConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\SelfTypedConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\UntypedConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\VariadicConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\WideningConstructorHolder;
use MagicSunday\Test\Fixtures\TypePrecedence\WideningPropertyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Property types are resolved docblock-first, which is right when the docblock REFINES the native
 * declaration - `array` narrowed to `string[]` is the library's core capability - and wrong when it
 * WIDENS it: a docblock cannot grant a value the target itself rejects.
 *
 * The two lanes failed differently. On a PROPERTY the write guard caught the refusal and reported
 * it, so the run stayed intact. On a CONSTRUCTOR parameter the value reached `new $className()` and
 * the native TypeError escaped the report entirely - and that lane escaped for more than the
 * widening case, because a parameter the metadata cannot type at all is just as unconstrained.
 *
 * So the guard sits at the constructor, keyed on the parameter's own declaration rather than on the
 * presence of a contradicting docblock. What it must NOT do is judge a value against a declaration
 * it is not handed to: a setter may accept more than the field it writes, and refusing there would
 * drop values the class accepts. Both directions are pinned below.
 *
 * @internal
 */
final class TypePrecedenceTest extends TestCase
{
    #[Test]
    public function itReportsANullTheDocblockWidensOntoANonNullableProperty(): void
    {
        // The docblock says int|null, the property says int. The property lane never escaped: the
        // write guard refuses the assignment and the refusal is reported against the property's own
        // declared type.
        $result = $this->getJsonMapper()->mapWithReport(['value' => null], WideningPropertyHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'The widened null is the only fault recorded.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.value', $exception->getPath());
        self::assertSame('int', $exception->getExpectedType());
        self::assertSame('null', $exception->getActualType());

        $mapped = $result->getValue();

        self::assertInstanceOf(WideningPropertyHolder::class, $mapped);
        self::assertSame(7, $mapped->value, 'The property keeps its declared default.');
    }

    #[Test]
    public function itRefusesANullTheDocblockWidensOntoANonNullablePromotedParameter(): void
    {
        // The lane the write guard cannot cover: the value would reach `new $className()` and raise
        // a native TypeError that escapes mapWithReport() entirely.
        $result = $this->getJsonMapper()->mapWithReport(['value' => null], WideningConstructorHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'The widened null is the only fault recorded.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.value', $exception->getPath());
        self::assertSame('int', $exception->getExpectedType());
        self::assertSame('null', $exception->getActualType());

        $mapped = $result->getValue();

        self::assertInstanceOf(WideningConstructorHolder::class, $mapped);
        self::assertSame(7, $mapped->value, 'The constructor default stands in.');
    }

    #[Test]
    public function itRefusesAScalarTheDocblockWidensOntoAPromotedParameter(): void
    {
        // Nullability is only the most common overstep. A docblock widening `int` to `int|string`
        // escaped the same way, so the guard cannot be a null check wearing a general name.
        $result = $this->getJsonMapper()->mapWithReport(
            ['value' => 'abc'],
            ScalarWideningConstructorHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'The widened string is the only fault recorded.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.value', $exception->getPath());
        self::assertSame('int', $exception->getExpectedType());
        self::assertSame('string', $exception->getActualType());

        $mapped = $result->getValue();

        self::assertInstanceOf(ScalarWideningConstructorHolder::class, $mapped);
        self::assertSame(7, $mapped->value, 'The constructor default stands in.');
    }

    #[Test]
    public function itRefusesAValueTheConstructorRejectsEvenWithNoDocblockToBlame(): void
    {
        // No docblock widens anything here. The property carries no type at all, so the resolver
        // falls back to its permissive default and the null passes conversion - then meets a
        // constructor parameter that is natively `int`. Keying the guard on a contradicting
        // docblock would miss this entirely, which is why it keys on the declaration.
        $result = $this->getJsonMapper()->mapWithReport(
            [
                'label' => 'x',
                'count' => null,
            ],
            UntypedConstructorHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'The rejected null is the only fault recorded.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.count', $exception->getPath());
        self::assertSame('int', $exception->getExpectedType());
        self::assertSame('null', $exception->getActualType());

        $mapped = $result->getValue();

        self::assertInstanceOf(UntypedConstructorHolder::class, $mapped);
        self::assertSame(3, $mapped->count, 'The constructor default stands in.');
    }

    #[Test]
    public function itDropsOnlyTheRefusedElementOfAVariadicAndKeepsItsSiblings(): void
    {
        // A variadic is judged element by element, like a collection: the null in the middle is
        // refused under its own index and the two valid strings still reach the constructor.
        // Refusing the whole spread would discard values the payload got right.
        $result = $this->getJsonMapper()->mapWithReport(
            ['tags' => ['ok', null, 'also']],
            VariadicConstructorHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'Only the null element is refused.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.tags.1', $exception->getPath(), 'The refusal is reported under the element index.');
        self::assertSame('string', $exception->getExpectedType());
        self::assertSame('null', $exception->getActualType());

        $mapped = $result->getValue();

        self::assertInstanceOf(VariadicConstructorHolder::class, $mapped);
        self::assertSame(['ok', 'also'], $mapped->tags, 'The valid siblings survive the refusal.');
    }

    #[Test]
    public function itResolvesASelfTypedParameterAgainstItsDeclaringClass(): void
    {
        // The declaration is `self`, which reflection reports literally on the older supported PHP
        // versions rather than as the class it stands for. The untyped property leaves `self`
        // unresolved, so an object payload is not recursively mapped - it reaches the constructor
        // as a raw array. The array is what discriminates: a scalar here is caught by the object
        // conversion lane with or without this guard, but a non-empty array reaches `new
        // $className()` and raises a native TypeError unless the guard resolves `self` to the
        // declaring class and refuses it first.
        $result = $this->getJsonMapper()->mapWithReport(
            ['child' => ['nested' => true]],
            SelfTypedConstructorHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        // The refused value plus the required argument it leaves missing - the same two-error shape
        // any required parameter produces when its value is rejected.
        self::assertCount(2, $errors, 'The refusal and the resulting missing argument.');

        $mismatch = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $mismatch);
        self::assertSame('$.child', $mismatch->getPath());
        self::assertSame(SelfTypedConstructorHolder::class, $mismatch->getExpectedType());
        self::assertSame('array', $mismatch->getActualType());

        self::assertNull($result->getValue(), 'A required argument was refused, so no object is built.');
    }

    #[Test]
    public function itResolvesAParentTypedParameterAgainstTheBaseClass(): void
    {
        // `parent` is the relative type a `self`/`static` shortcut would resolve wrongly: it stands
        // for the base, not the class being built. The untyped property leaves it unresolved, so a
        // raw array reaches the constructor and must be reported against the base class rather than
        // raising a native TypeError.
        $result = $this->getJsonMapper()->mapWithReport(
            ['origin' => ['nested' => true]],
            ParentTypedConstructorHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors, 'The refusal and the resulting missing argument.');

        $mismatch = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $mismatch);
        self::assertSame('$.origin', $mismatch->getPath());
        self::assertSame(
            'MagicSunday\\Test\\Fixtures\\TypePrecedence\\ParentTypedConstructorBase',
            $mismatch->getExpectedType(),
            'The relative type resolved to the base, not to the class being built.',
        );
        self::assertSame('array', $mismatch->getActualType());

        self::assertNull($result->getValue(), 'A required argument was refused, so no object is built.');
    }

    #[Test]
    public function itHonoursASetterThatAcceptsMoreThanTheFieldItWrites(): void
    {
        // The direction the guard must not overreach in. The backing field is a non-nullable int,
        // but the write goes through a setter that accepts null - so the field's declaration is not
        // the one the value has to satisfy. Judging against it would refuse a value the class
        // documents itself as taking.
        $result = $this->getJsonMapper()->mapWithReport(['value' => null], AccessorWideningHolder::class);

        self::assertFalse($result->getReport()->hasErrors(), 'The setter accepts null.');

        $mapped = $result->getValue();

        self::assertInstanceOf(AccessorWideningHolder::class, $mapped);
        self::assertSame(42, $mapped->getValue(), 'The setter ran and applied its own fallback.');
    }

    #[Test]
    public function itStillHonoursADocblockThatRefinesTheNativeType(): void
    {
        // The control the fix must not break: `array` plus a docblock element type is the whole
        // point of reading docblocks. The payload is STRINGS onto an `int[]` docblock, so the
        // refinement has to convert them - asserting string elements onto `string[]` would pass
        // under an untyped array too and discriminate nothing.
        $result = $this->getJsonMapper()->mapWithReport(
            ['numbers' => ['1', '2']],
            RefiningPropertyHolder::class,
        );

        self::assertFalse($result->getReport()->hasErrors());

        $mapped = $result->getValue();

        self::assertInstanceOf(RefiningPropertyHolder::class, $mapped);
        self::assertSame([1, 2], $mapped->numbers, 'The docblock element type converted the strings.');
    }

    #[Test]
    public function itKeepsNullabilityThePropertyActuallyDeclares(): void
    {
        // A genuinely nullable property still accepts null, so the guard cannot be "refuse null
        // whenever a docblock mentions it".
        $result = $this->getJsonMapper()->mapWithReport(
            ['optional' => null],
            RefiningPropertyHolder::class,
        );

        self::assertFalse($result->getReport()->hasErrors());

        $mapped = $result->getValue();

        self::assertInstanceOf(RefiningPropertyHolder::class, $mapped);
        self::assertNull($mapped->optional);
    }
}
