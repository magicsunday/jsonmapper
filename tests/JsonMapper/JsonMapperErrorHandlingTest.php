<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\DateTimeHolder;
use MagicSunday\Test\Classes\EnumHolder;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\ReadonlyEntity;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;

/**
 * @internal
 */
final class JsonMapperErrorHandlingTest extends TestCase
{
    #[Test]
    public function itCollectsErrorsInReports(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport([
                'name'    => 'John Doe',
                'unknown' => 'value',
            ], Person::class);

        self::assertInstanceOf(Person::class, $result->getValue());

        $report = $result->getReport();
        self::assertTrue($report->hasErrors());
        self::assertSame(1, $report->getErrorCount());

        $error = $report->getErrors()[0];
        self::assertSame('Unknown property $.unknown on ' . Person::class . '.', $error->getMessage());
        self::assertInstanceOf(UnknownPropertyException::class, $error->getException());
    }

    #[Test]
    public function itThrowsOnUnknownPropertiesInStrictMode(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $this->getJsonMapper()
            ->map(
                [
                    'name'    => 'John Doe',
                    'unknown' => 'value',
                ],
                Person::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itThrowsOnMissingRequiredProperties(): void
    {
        $this->expectException(MissingPropertyException::class);

        $this->getJsonMapper()
            ->map(
                [],
                Person::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itThrowsOnTypeMismatch(): void
    {
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper()
            ->map(
                ['name' => 123],
                Base::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itThrowsOnInvalidCollectionPayloads(): void
    {
        $this->expectException(CollectionMappingException::class);

        $this->getJsonMapper()
            ->map(
                [
                    'name'        => 'John Doe',
                    'simpleArray' => 'invalid',
                ],
                Base::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itReportsTypeMismatchesInLenientMode(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['name' => 123],
                Base::class,
            );

        $report = $result->getReport();
        self::assertTrue($report->hasErrors());

        $exception = $report->getErrors()[0]->getException();
        self::assertInstanceOf(TypeMismatchException::class, $exception);
    }

    #[Test]
    public function itCollectsNestedErrorsAcrossObjectGraphs(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'simple' => [
                        'int'     => 'oops',
                        'name'    => 456,
                        'unknown' => 'value',
                    ],
                ],
                Base::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(3, $errors);

        $errorsByPath = [];
        foreach ($errors as $error) {
            $errorsByPath[$error->getPath()] = $error;
        }

        self::assertArrayHasKey('$.simple.int', $errorsByPath);
        self::assertSame(
            'Type mismatch at $.simple.int: expected int, got string.',
            $errorsByPath['$.simple.int']->getMessage(),
        );
        self::assertInstanceOf(TypeMismatchException::class, $errorsByPath['$.simple.int']->getException());

        self::assertArrayHasKey('$.simple.name', $errorsByPath);
        self::assertSame(
            'Type mismatch at $.simple.name: expected string, got int.',
            $errorsByPath['$.simple.name']->getMessage(),
        );
        self::assertInstanceOf(TypeMismatchException::class, $errorsByPath['$.simple.name']->getException());

        self::assertArrayHasKey('$.simple.unknown', $errorsByPath);
        self::assertSame(
            'Unknown property $.simple.unknown on ' . Simple::class . '.',
            $errorsByPath['$.simple.unknown']->getMessage(),
        );
        self::assertInstanceOf(UnknownPropertyException::class, $errorsByPath['$.simple.unknown']->getException());
    }

    #[Test]
    public function itReportsReadonlyPropertyViolations(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport([
                'id' => 'changed',
            ], ReadonlyEntity::class);

        $entity = $result->getValue();

        self::assertInstanceOf(ReadonlyEntity::class, $entity);
        self::assertSame('initial', $entity->id);

        $errors = $result->getReport()->getErrors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(ReadonlyPropertyException::class, $errors[0]->getException());
        self::assertSame('Readonly property ' . ReadonlyEntity::class . '::id cannot be written at $.id.', $errors[0]->getMessage());
    }

    #[Test]
    public function itThrowsOnInvalidNestedCollectionEntriesInStrictMode(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Type mismatch at $.simpleArray.1.int: expected int, got string.');

        $this->getJsonMapper()
            ->map(
                [
                    'simpleArray' => [
                        ['id' => 1, 'int' => 1, 'name' => 'Valid'],
                        ['id' => 2, 'int' => 'oops', 'name' => 'Broken'],
                    ],
                ],
                Base::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itThrowsWhenRequiredPropertyIsNullInStrictMode(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "string", "null" given at property path "name".');

        $this->getJsonMapper()
            ->map(
                ['name' => null],
                Person::class,
                null,
                null,
                JsonMapperConfiguration::strict(),
            );
    }

    #[Test]
    public function itReportsInvalidDateTimeValuesInLenientMode(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['createdAt' => 'not-a-date'],
                DateTimeHolder::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.createdAt: expected DateTimeImmutable, got string.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itReportsInvalidEnumValuesInLenientMode(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['status' => 'archived'],
                EnumHolder::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.status: expected MagicSunday\\Test\\Fixtures\\Enum\\SampleStatus, got string.',
            $errors[0]->getMessage(),
        );
    }
}
