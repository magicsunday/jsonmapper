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
use MagicSunday\Test\Classes\EnumCollectionHolder;
use MagicSunday\Test\Classes\UnitEnumHolder;
use MagicSunday\Test\Fixtures\Enum\SampleColor;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers pure enums. Their cases carry no scalar value, so a payload addresses them by case name.
 * Before this was handled, such a property fell through to the object strategy and reached the
 * instantiator, which failed with a native "Cannot instantiate enum" error.
 *
 * The holder's sentinel default is the case no test maps onto, so every assertion on the property
 * distinguishes a rejected value from a written one.
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
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.color: expected MagicSunday\\Test\\Fixtures\\Enum\\SampleColor, got string.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itMatchesTheCaseNameExactly(): void
    {
        // Case names are identifiers, not values - a case-insensitive match would silently accept
        // a payload the enum does not define. This is the only case that rejects such a match.
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => 'red'],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itRecordsAMismatchForANonStringValue(): void
    {
        // true rather than an int on purpose: PHP evaluates 'Red' == true as true, so this payload
        // is the one a loosened comparison would wrongly accept. An int would pass either way and
        // prove nothing.
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => true],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertSame(SampleColor::Blue, $holder->color);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.color: expected MagicSunday\\Test\\Fixtures\\Enum\\SampleColor, got bool.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itAcceptsNullOnANullablePureEnumProperty(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['color' => null],
            UnitEnumHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(UnitEnumHolder::class, $holder);
        self::assertNull($holder->color);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itMapsPureEnumsInsideACollection(): void
    {
        // Collection elements reach the strategies through a different caller than a plain
        // property, so the case-name resolution needs its own coverage there.
        $result = $this->getJsonMapper()->mapWithReport(
            ['colors' => ['Red', 'Blue']],
            EnumCollectionHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(EnumCollectionHolder::class, $holder);
        self::assertSame([SampleColor::Red, SampleColor::Blue], $holder->colors);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itDropsOnlyTheElementThatNamesNoCase(): void
    {
        // An element that cannot be converted is dropped on its own; its valid siblings survive.
        // This test previously pinned the opposite - the whole list was discarded - and was
        // written as the visible decision point for exactly this change.
        $result = $this->getJsonMapper()->mapWithReport(
            ['colors' => ['Red', 'Green', 'Blue']],
            EnumCollectionHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(EnumCollectionHolder::class, $holder);
        // Asserted without re-indexing on purpose: dropping an element must not leave a gap in
        // the keys, or the declared list type would no longer hold.
        self::assertSame(
            [SampleColor::Red, SampleColor::Blue],
            $holder->colors,
            'The valid elements must survive an invalid sibling, without a gap in the keys.',
        );
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.colors.1: expected MagicSunday\\Test\\Fixtures\\Enum\\SampleColor, got string.',
            $errors[0]->getMessage(),
            'The recorded error must name the index of the element that was dropped.',
        );
    }

    #[Test]
    public function itThrowsInStrictModeForAnUnknownCaseName(): void
    {
        // The type token is part of the expectation: both strict tests would otherwise pass on
        // each other's input, since the two rejection reasons share a throw site and a path.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('$.color: expected', '/') . '.*' . preg_quote('got string.', '/') . '/',
        );

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['color' => 'Green'],
            UnitEnumHolder::class,
        );
    }

    #[Test]
    public function itThrowsInStrictModeForANonStringValue(): void
    {
        // resolveUnitEnumCase() throws from a single site for two distinct reasons, so both need
        // their strict-mode counterpart.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('$.color: expected', '/') . '.*' . preg_quote('got bool.', '/') . '/',
        );

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['color' => true],
            UnitEnumHolder::class,
        );
    }
}
