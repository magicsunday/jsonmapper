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
use MagicSunday\Test\Classes\UnitEnumHolder;
use MagicSunday\Test\Fixtures\Enum\SampleColor;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers pure enums. Their cases carry no scalar value, so a payload addresses them by case name.
 * Before this was handled, such a property fell through to the object strategy and reached the
 * instantiator, which failed with a native "Cannot instantiate enum" error.
 *
 * @internal
 */
final class UnitEnumValueConversionTest extends TestCase
{
    #[Test]
    public function itMapsAPureEnumByCaseName(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => 'Red'],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();

        // The sentinel default is the other case, so this also proves the property was written.
        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Red, $holder->color);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itRecordsAMismatchForAnUnknownCaseName(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => 'Green'],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itMatchesTheCaseNameExactly(): void
    {
        // Case names are identifiers, not values - a case-insensitive match would silently accept
        // a payload the enum does not define.
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => 'red'],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itRecordsAMismatchForANonStringValue(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => 1],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itThrowsInStrictModeForAnUnknownCaseName(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/color/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['color' => 'Green'],
            UnitEnumHolder::class,
        );
    }
}
