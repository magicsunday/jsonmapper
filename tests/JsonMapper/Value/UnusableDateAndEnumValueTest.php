<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use DateTimeImmutable;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\DateTimeHolder;
use MagicSunday\Test\Classes\EnumHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A date and an enum are both built from a scalar, and both have shapes of payload that cannot
 * supply one. Each is refused before it reaches the constructor or the case factory, because both
 * raise natively for a value they cannot read - which is invisible to error collection.
 *
 * @internal
 */
final class UnusableDateAndEnumValueTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, mixed>|bool, string}>
     */
    public static function unusableDateValueProvider(): array
    {
        return [
            // A date is a string, an integer timestamp or a float one. Everything else carries no
            // instant, and the detected type is pinned as the second column so the rows are not
            // interchangeable: collapsing "an object" and "a list" - both get_debug_type() 'array' -
            // into one row is what keeps each remaining row exercising a distinct outcome.
            'an array'  => [['year' => 2024], 'array'],
            'a boolean' => [true, 'bool'],
        ];
    }

    /**
     * @param array<array-key, mixed>|bool $value          Payload that cannot describe an instant
     * @param string                       $expectedActual The type the report names for it
     */
    #[Test]
    #[DataProvider('unusableDateValueProvider')]
    public function itReportsAPayloadThatCannotDescribeAnInstant(array|bool $value, string $expectedActual): void
    {
        $result = $this->getJsonMapper()->mapWithReport(['createdAt' => $value], DateTimeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.createdAt', $exception->getPath());
        self::assertSame(DateTimeImmutable::class, $exception->getExpectedType());
        self::assertSame($expectedActual, $exception->getActualType());
    }

    #[Test]
    public function itReportsANumberWhereAnIntervalSpecificationIsExpected(): void
    {
        // An interval is a specification string - "P1D" - and nothing else. A number passes the
        // date lane's own guard, which accepts a timestamp, so the interval lane needs its own.
        $result = $this->getJsonMapper()->mapWithReport(['timeout' => 3600], DateTimeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.timeout', $exception->getPath());
        self::assertSame('int', $exception->getActualType());
    }

    #[Test]
    public function itReportsATimezoneTheOptionBagCarriesThatDoesNotExist(): void
    {
        // The configuration validates a timezone on the way in, but the option bag is an extension
        // point a handler can write to directly, so the strategy cannot assume it was. An unknown
        // identifier makes DateTimeZone raise, and that is no MappingException - it would escape a
        // run that promised a report.
        $payload = ['createdAt' => '2024-01-01T10:00:00+00:00'];

        self::assertSame(
            [],
            $this->collectWithOptions($payload, []),
            'The control: the same payload under a valid default records nothing.',
        );

        $errors = $this->collectWithOptions(
            $payload,
            [MappingContext::OPTION_DEFAULT_TIMEZONE => 'Nowhere/Special'],
        );

        self::assertCount(1, $errors);

        // The identifier is NOT echoed as the actual type: that slot is documented as the detected
        // type of the VALUE, and an extension point can route request-influenced data into the
        // timezone. A slot that sometimes holds a type and sometimes free-form text is one a
        // consumer cannot treat safely.
        self::assertSame('string', $errors[0]->getActualType());
        self::assertStringNotContainsString('Nowhere/Special', $errors[0]->getMessage());
    }

    /**
     * @return array<string, array{array<array-key, mixed>|float, string}>
     */
    public static function unusableEnumValueProvider(): array
    {
        return [
            // As with the date provider, the two array shapes collapse to one row (both report
            // 'array'); the detected type is the second column so the float row pins something the
            // array row does not.
            'an array' => [['value' => 'active'], 'array'],
            'a float'  => [1.5, 'float'],
        ];
    }

    /**
     * @param array<array-key, mixed>|float $value          Payload that cannot name a backed enum case
     * @param string                        $expectedActual The type the report names for it
     */
    #[Test]
    #[DataProvider('unusableEnumValueProvider')]
    public function itReportsAPayloadThatCannotNameABackedEnumCase(array|float $value, string $expectedActual): void
    {
        // A backed enum is keyed by an int or a string. from() raises a native TypeError for
        // anything else, so the shape is refused before it gets there.
        $result = $this->getJsonMapper()->mapWithReport(['status' => $value], EnumHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.status', $exception->getPath());
        self::assertSame($expectedActual, $exception->getActualType());
    }

    /**
     * Maps a payload under the provided context options and returns the recorded exceptions.
     *
     * @param array<string, mixed> $payload Payload to map.
     * @param array<string, mixed> $options Context options written directly into the bag.
     *
     * @return list<TypeMismatchException> Recorded failures
     */
    private function collectWithOptions(array $payload, array $options): array
    {
        $context = new MappingContext($payload, $options);

        try {
            $this->getJsonMapper()->map($payload, DateTimeHolder::class, null, $context);
        } catch (TypeMismatchException) {
            // Recorded as well as raised; the records are what this asserts on.
        }

        $collected = [];

        foreach ($context->getErrorRecords() as $record) {
            $exception = $record->getException();

            if ($exception instanceof TypeMismatchException) {
                $collected[] = $exception;
            }
        }

        return $collected;
    }
}
