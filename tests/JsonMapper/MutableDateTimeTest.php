<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use DateTime;
use DateTimeInterface;
use MagicSunday\Test\Classes\MutableDateTimeHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * The date/time strategy claimed DateTimeImmutable descendants only, so a mutable DateTime
 * property was left to the object strategy, which cannot build one from a string.
 *
 * @internal
 */
final class MutableDateTimeTest extends TestCase
{
    #[Test]
    public function itMapsAMutableDateTimeProperty(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"when": "2020-05-05T10:00:00+00:00"}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertSame(
            '2020-05-05T10:00:00+00:00',
            $holder->when->format(DateTimeInterface::ATOM),
            'The payload date is parsed, not replaced by the current time.',
        );
    }

    #[Test]
    public function itMapsANullableMutableDateTimeProperty(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"optional": "2021-01-02T03:04:05+00:00"}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertInstanceOf(DateTime::class, $holder->optional);
        self::assertSame('2021-01-02T03:04:05+00:00', $holder->optional->format(DateTimeInterface::ATOM));
    }

    #[Test]
    public function itKeepsTheResultMutable(): void
    {
        // The distinguishing property of the type the caller asked for. Handing back a
        // DateTimeImmutable would satisfy every assertion above while breaking the one thing a
        // caller chooses DateTime for.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"when": "2020-05-05T10:00:00+00:00"}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);

        $returned = $holder->when->setDate(2022, 3, 4);

        self::assertSame($returned, $holder->when, 'A mutable DateTime mutates in place.');
        self::assertSame('2022-03-04', $holder->when->format('Y-m-d'));
    }

    #[Test]
    public function itRejectsAPropertyTypedByTheInterface(): void
    {
        // DateTimeInterface cannot be instantiated, so there is nothing to build. Choosing an
        // implementation would be the mapper deciding mutability on the caller's behalf.
        $result = $this->getJsonMapper()->mapWithReport(
            ['byInterface' => '2020-05-05T10:00:00+00:00'],
            MutableDateTimeHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertNull(
            (new ReflectionProperty($holder, 'byInterface'))->getValue($holder),
            'An interface-typed property is not filled by guessing an implementation.',
        );
        self::assertTrue($result->getReport()->hasErrors());
    }
}
