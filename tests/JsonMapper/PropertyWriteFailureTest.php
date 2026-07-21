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
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
        $result = $this->getJsonMapper()->mapWithReport(
            ['both' => ['a' => 1]],
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
