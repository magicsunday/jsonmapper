<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\EnumHolder;
use MagicSunday\Test\Classes\IntBackedEnumHolder;
use MagicSunday\Test\Fixtures\Enum\SamplePriority;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * Covers enum payloads whose scalar type does not match the enum's backing type. Under
 * strict_types the case factory rejects those with a TypeError before any lookup happens, so they
 * used to escape the error-collection contract entirely.
 *
 * The rejected payloads deliberately do not correspond to the holder's sentinel default: were the
 * mapper to start coercing the value instead of rejecting it, the property would end up holding a
 * different case, which the assertions notice.
 *
 * @internal
 */
final class EnumValueConversionTest extends TestCase
{
    #[Test]
    public function itRecordsAMismatchForAStringValueOnAnIntBackedEnum(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => '2'],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertSame(SamplePriority::Low, $holder->priority);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.priority: expected MagicSunday\\Test\\Fixtures\\Enum\\SamplePriority, got string.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itRecordsAMismatchForAnIntValueOnAStringBackedEnum(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['status' => 1],
            EnumHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        // The holder's property is non-nullable and has no default, so the initialization state
        // is what proves the rejected value never reached it.
        self::assertInstanceOf(EnumHolder::class, $holder);
        self::assertFalse(
            (new ReflectionProperty($holder, 'status'))->isInitialized($holder),
            'The rejected value must not have been written to the property.',
        );
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.status: expected MagicSunday\\Test\\Fixtures\\Enum\\SampleStatus, got int.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itRecordsAMismatchForAValueOutsideTheEnumCases(): void
    {
        // A correctly typed but unknown value takes the ValueError path, which was already
        // handled. Pinning it here keeps the two rejection reasons from drifting apart.
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => 99],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertSame(SamplePriority::Low, $holder->priority);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.priority: expected MagicSunday\\Test\\Fixtures\\Enum\\SamplePriority, got int.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itThrowsInStrictModeForAMismatchingBackingType(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('$.priority', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['priority' => '2'],
            IntBackedEnumHolder::class,
        );
    }

    #[Test]
    public function itThrowsInStrictModeForAValueOutsideTheEnumCases(): void
    {
        // The two rejection reasons share a catch block now, so strict mode has to be pinned for
        // both - otherwise merging them could quietly change the pre-existing path.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('$.priority', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['priority' => 99],
            IntBackedEnumHolder::class,
        );
    }

    #[Test]
    public function itStillMapsAValueMatchingTheBackingType(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => 2],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertSame(SamplePriority::High, $holder->priority);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAcceptsNullOnANullableEnumProperty(): void
    {
        // The property is declared nullable, so null is a legitimate value rather than a mismatch.
        // The sentinel default discriminates cleanly: null means the property was overwritten.
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => null],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertNull($holder->priority);
        self::assertFalse($result->getReport()->hasErrors());
    }
}
