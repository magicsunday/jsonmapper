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
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\Test\Classes\CustomDateTime;
use MagicSunday\Test\Classes\MutableDateTimeHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

use function array_map;

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
        // Pins the user-facing contract, and only that. It does NOT discriminate what the
        // strategy returns: Symfony's property accessor converts a DateTimeImmutable into a
        // DateTime when the declared property type demands one, value intact - verified by
        // returning an immutable instance from the strategy, which left this green. The declared
        // type is what guarantees mutability, so a change there is what this would catch.
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
    public function itMapsACallersOwnDateSubclass(): void
    {
        // The documentation promises "or your own subclass". Pinning only the two builtin classes
        // would leave a predicate narrowed to those names green while dropping this shape.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"custom": "2020-05-05T10:00:00+00:00"}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertInstanceOf(CustomDateTime::class, $holder->custom);
        self::assertSame('2020-05-05T10:00:00+00:00', $holder->custom->format(DateTimeInterface::ATOM));
    }

    #[Test]
    public function itMapsAnIntegerTimestamp(): void
    {
        // The class docblock promises timestamps. Nothing in the suite passed an integer, so the
        // branch building "@<timestamp>" could be removed without a single test noticing.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"when": 1588672800}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertSame('2020-05-05T10:00:00+00:00', $holder->when->format(DateTimeInterface::ATOM));
    }

    #[Test]
    public function itKeepsANullPayloadNull(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"optional": null}'),
            MutableDateTimeHolder::class,
        );

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertNull($holder->optional);
    }

    #[Test]
    public function itNamesTheDeclaredClassWhenAValueDoesNotParse(): void
    {
        // The message is built from the declared class name. Only the immutable holder asserted
        // it, so a hardcoded DateTimeImmutable would have reported the wrong type here.
        $result = $this->getJsonMapper()->mapWithReport(
            ['when' => 'not a date'],
            MutableDateTimeHolder::class,
        );

        self::assertSame(
            ['Type mismatch at $.when: expected DateTime, got string.'],
            array_map(
                static fn (MappingError $error): string => $error->getMessage(),
                $result->getReport()->getErrors(),
            ),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonInstantiableDateTypeProvider(): array
    {
        return [
            'the interface itself'      => ['byInterface'],
            'an interface extending it' => ['byCustomInterface'],
            'an abstract subclass'      => ['byAbstract'],
        ];
    }

    /**
     * @param string $property Property whose declared date type cannot be instantiated.
     */
    #[Test]
    #[DataProvider('nonInstantiableDateTypeProvider')]
    public function itRefusesADateTypeItCannotInstantiate(string $property): void
    {
        // None of these can be built. Reaching `new` would raise a native Error that no
        // MappingException catch collects, so a lenient run that should return a report would
        // become a fatal instead. The valid sibling value is the positive control: the mapper has
        // to carry on rather than abort.
        $result = $this->getJsonMapper()->mapWithReport(
            [$property => '2020-05-05T10:00:00+00:00', 'when' => '2021-01-02T03:04:05+00:00'],
            MutableDateTimeHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(MutableDateTimeHolder::class, $holder);
        self::assertNull(
            (new ReflectionProperty($holder, $property))->getValue($holder),
            'A type the mapper cannot instantiate is refused, not guessed at.',
        );
        self::assertSame(
            '2021-01-02T03:04:05+00:00',
            $holder->when->format(DateTimeInterface::ATOM),
            'Control: the refusal is scoped to that property and mapping continues.',
        );
        self::assertCount(
            1,
            $result->getReport()->getErrors(),
            'Only the property with the unusable type fails.',
        );
    }
}
