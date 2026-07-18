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
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\NonNullableDtoHolder;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDto;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDtoHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * A scalar payload cannot supply a constructor argument, so a class that requires one cannot be
 * built from it. The mapper used to call the constructor with no arguments at all, which failed
 * with a native ArgumentCountError - in every mode, so error collection never saw it.
 *
 * @internal
 */
final class ScalarPayloadOnObjectTest extends TestCase
{
    /**
     * Scalar shapes a payload can carry. None of them can populate a constructor argument, so all
     * must be rejected the same way.
     *
     * @return array<string, array{string|int|float|bool}>
     */
    public static function scalarPayloadProvider(): array
    {
        return [
            'string'  => ['oops'],
            'integer' => [42],
            'float'   => [1.5],
            'boolean' => [true],
        ];
    }

    #[Test]
    #[DataProvider('scalarPayloadProvider')]
    public function itRaisesAMappingExceptionForAScalarPayloadAtTheTopLevel(
        string|int|float|bool $payload,
    ): void {
        // A domain exception rather than a native ArgumentCountError is what this pins. That it
        // escapes mapWithReport() instead of being recorded is a separate, already tracked gap in
        // the root-level error handling (issue 58) - the nested case below shows the difference.
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper()->mapWithReport(
            $payload,
            RequiredConstructorArgumentDto::class,
        );
    }

    #[Test]
    public function itRecordsAMismatchForAScalarPayloadOnANestedProperty(): void
    {
        // The nested path reaches the instantiator through a different caller than the top level,
        // so it needs its own case - it crashed just the same.
        $result = $this->getJsonMapper()->mapWithReport(
            ['dto' => 'oops'],
            RequiredConstructorArgumentDtoHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(RequiredConstructorArgumentDtoHolder::class, $holder);
        self::assertNull($holder->dto);
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itRecordsTheMismatchExactlyOnceOnANonNullableProperty(): void
    {
        // The nullable sibling above cannot observe this: its union path trims recorded errors
        // before rethrowing, so a duplicate record would be invisible there. One rejected value
        // must produce exactly one record - consumers count and display them.
        $result = $this->getJsonMapper()->mapWithReport(
            ['dto' => 'oops'],
            NonNullableDtoHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(NonNullableDtoHolder::class, $holder);
        self::assertFalse(
            (new ReflectionProperty($holder, 'dto'))->isInitialized($holder),
            'The rejected value must not have been written to the property.',
        );
        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.dto: expected MagicSunday\\Test\\Classes\\RequiredConstructorArgumentDto, got string.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itThrowsInStrictModeForAScalarPayload(): void
    {
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            'oops',
            RequiredConstructorArgumentDto::class,
        );
    }

    #[Test]
    public function itStillMapsAnObjectPayloadOntoTheSameTarget(): void
    {
        // The rejection must be about the payload shape, not about the target class.
        $result = $this->getJsonMapper()->mapWithReport(
            ['name' => 'accepted'],
            RequiredConstructorArgumentDto::class,
        );

        $dto = $result->getValue();

        self::assertInstanceOf(RequiredConstructorArgumentDto::class, $dto);
        self::assertSame('accepted', $dto->name);
        self::assertFalse($result->getReport()->hasErrors());
    }
}
