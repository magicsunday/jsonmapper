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
use MagicSunday\Test\Classes\ReadonlyEntity;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDto;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDtoHolder;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\VariadicConstructor;
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

    /**
     * Targets whose constructor an argument-less instantiation can satisfy. A scalar payload
     * still produces an instance for these - that is the behaviour the guard preserves, and
     * without a case here two mutations of it survive the suite, including one that would reject
     * every scalar outright.
     *
     * @return array<string, array{class-string}>
     */
    public static function instantiableTargetProvider(): array
    {
        return [
            'optional promoted argument' => [ReadonlyEntity::class],
            'variadic argument'          => [VariadicConstructor::class],
            'no constructor at all'      => [Simple::class],
        ];
    }

    #[Test]
    #[DataProvider('scalarPayloadProvider')]
    public function itCurrentlyEscapesTheReportForAScalarPayloadAtTheTopLevel(
        string|int|float|bool $payload,
    ): void {
        // What this pins is the domain exception replacing a native ArgumentCountError. That it
        // ESCAPES mapWithReport() rather than being recorded is a separate, tracked defect in the
        // root-level error handling (issue 58). The name says "currently" on purpose: once that
        // lands, this expectation inverts to a recorded error, and the failure should read as the
        // planned flip rather than as a regression.
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper()->mapWithReport(
            $payload,
            RequiredConstructorArgumentDto::class,
        );
    }

    /**
     * @param class-string $className Target whose constructor needs no arguments.
     */
    #[Test]
    #[DataProvider('instantiableTargetProvider')]
    public function itStillInstantiatesATargetThatNeedsNoConstructorArguments(string $className): void
    {
        $result = $this->getJsonMapper()->mapWithReport('oops', $className);

        self::assertInstanceOf($className, $result->getValue());
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itLeavesAnOptionalConstructorDefaultInPlace(): void
    {
        // The optional-but-promoted boundary: this is the shape that distinguishes the guard's
        // "required parameters" predicate from the broader one the hydration path uses. Without
        // this assertion, widening the predicate to any parameter passes unnoticed.
        $result = $this->getJsonMapper()->mapWithReport('oops', ReadonlyEntity::class);

        $entity = $result->getValue();

        self::assertInstanceOf(ReadonlyEntity::class, $entity);
        self::assertSame('initial', $entity->id);
    }

    #[Test]
    public function itRecordsAMismatchForAScalarPayloadOnANestedProperty(): void
    {
        // This pins the object strategy's rejection, not the instantiator guard: the strategy
        // short-circuits the nested path before it ever reaches the instantiator. Both crashed
        // before the fix, but through different code.
        $result = $this->getJsonMapper()->mapWithReport(
            ['dto' => 'oops'],
            RequiredConstructorArgumentDtoHolder::class,
        );

        $holder = $result->getValue();
        $errors = $result->getReport()->getErrors();

        self::assertInstanceOf(RequiredConstructorArgumentDtoHolder::class, $holder);
        self::assertNull($holder->dto);
        self::assertCount(1, $errors, 'One rejected value must produce exactly one record.');
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
