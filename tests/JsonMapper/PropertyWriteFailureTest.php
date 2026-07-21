<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Fixtures\PropertyWrite\IntersectionTypedHolder;
use MagicSunday\Test\Fixtures\PropertyWrite\MarkerA;
use MagicSunday\Test\Fixtures\PropertyWrite\MarkerB;
use MagicSunday\Test\Fixtures\PropertyWrite\VariadicBodyThrowingHolder;
use MagicSunday\Test\Fixtures\Shapes\AccessorOnlyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

/**
 * The last step of a mapping run is the write, and it can fail on its own: the declared type is
 * the property's, not the conversion pipeline's, and the two do not always agree. An intersection
 * is the reachable case - neither PropertyInfo nor the reflection fallback models one, so the type
 * resolver falls back to nullable mixed, which accepts every payload. The property then rejects it.
 *
 * Before this was guarded, the rejection arrived as a native TypeError wrapped by Symfony's
 * property accessor: it escaped mapWithReport() past the report the caller was promised, breaking
 * both "never let a native error escape" and "a rejected value is recorded exactly once".
 *
 * @internal
 */
final class PropertyWriteFailureTest extends TestCase
{
    #[Test]
    public function itReportsAValueTheDeclaredPropertyTypeRefuses(): void
    {
        // A valid sibling property travels in the same payload, so the run also proves the write
        // is reached and one refused property does not abort the rest of the object.
        $result = $this->getJsonMapper()->mapWithReport(
            ['both' => ['a' => 1], 'label' => 'kept'],
            IntersectionTypedHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One refused write, one record - not zero, and not two.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.both', $exception->getPath());
        self::assertSame('array', $exception->getActualType());

        // The type the PROPERTY declares, not the nullable mixed the value was converted against -
        // naming the latter would report "expected mixed, got array", which explains nothing.
        self::assertSame(
            '(' . MarkerA::class . '&' . MarkerB::class . ')|null',
            $exception->getExpectedType(),
        );

        $mapped = $result->getValue();

        self::assertInstanceOf(IntersectionTypedHolder::class, $mapped, 'The object is still built.');
        self::assertNull($mapped->both, 'And the refused property keeps its default.');
        self::assertSame('kept', $mapped->label, 'While the valid sibling is written - the accessor is reached.');
    }

    #[Test]
    public function itNamesTheRefusedTypeWhenTheWriteGoesThroughASetter(): void
    {
        // A property exposed only through an accessor pair has no reflectable backing property, so
        // deriving the expected type from reflection reports mixed - which is wrong: the setter's
        // parameter is what refused the value. The accessor already computed the refused type, and
        // that is what the record now carries.
        $result = $this->getJsonMapper()->mapWithReport(
            ['label' => ['not a string']],
            AccessorOnlyHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.label', $exception->getPath());
        self::assertSame('string', $exception->getExpectedType(), 'The setter parameter, not mixed.');
        self::assertSame('array', $exception->getActualType());
    }

    #[Test]
    public function itLetsATypeErrorFromInsideAVariadicSetterBodyPropagate(): void
    {
        // A TypeError raised inside a variadic setter body is a bug in the setter, not a payload
        // mismatch - reporting it as one would blame a valid value (the elements ARE ints) and, in
        // report mode, bury the real fault. The write guard does not catch a variadic TypeError at
        // all, so it propagates as itself.
        //
        // The fixture raises it from the setter's OWN frame, which is the shape a trace-frame
        // heuristic would have masked (it looks identical to an argument-binding refusal): this
        // pins that the lane does not classify by frame but simply lets every variadic TypeError
        // through.
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Deliberate setter-body failure/');

        $this->getJsonMapper()->mapWithReport(
            ['values' => [1, 2]],
            VariadicBodyThrowingHolder::class,
        );
    }

    #[Test]
    public function itStillWritesAValueTheDeclaredPropertyTypeAccepts(): void
    {
        // The control: the guard must not swallow the write it exists to protect. Null is the one
        // value this property accepts without an instance of both markers, so it is what pins that
        // the mapper still reaches the accessor at all.
        $result = $this->getJsonMapper()->mapWithReport(
            ['both' => null],
            IntersectionTypedHolder::class,
        );

        self::assertFalse($result->getReport()->hasErrors());
        self::assertInstanceOf(IntersectionTypedHolder::class, $result->getValue());
    }
}
