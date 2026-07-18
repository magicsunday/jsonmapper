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

/**
 * Covers enum payloads whose scalar type does not match the enum's backing type. Under
 * strict_types the case factory rejects those with a TypeError before any lookup happens, so they
 * used to escape the error-collection contract entirely.
 *
 * @internal
 */
final class EnumValueConversionTest extends TestCase
{
    #[Test]
    public function itRecordsAMismatchForAStringValueOnAnIntBackedEnum(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => '1'],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();

        // The sentinel default proves the rejected value was not written.
        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertSame(SamplePriority::Low, $holder->priority);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itRecordsAMismatchForAnIntValueOnAStringBackedEnum(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['status' => 1],
            EnumHolder::class,
        );

        self::assertInstanceOf(EnumHolder::class, $result->getValue());
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itThrowsInStrictModeForAMismatchingBackingType(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/priority/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['priority' => '1'],
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
    public function itRecordsAMismatchForAValueOutsideTheEnumCases(): void
    {
        // An unknown but correctly typed value takes the ValueError path, which was already
        // handled. Pinning it here keeps the two rejection reasons from drifting apart.
        $result = $this->getJsonMapper()->mapWithReport(
            ['priority' => 99],
            IntBackedEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IntBackedEnumHolder::class, $holder);
        self::assertSame(SamplePriority::Low, $holder->priority);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }
}
