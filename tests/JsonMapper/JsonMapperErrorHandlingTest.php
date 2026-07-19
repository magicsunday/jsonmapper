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
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\DateTimeHolder;
use MagicSunday\Test\Classes\EnumHolder;
use MagicSunday\Test\Classes\Initialized;
use MagicSunday\Test\Classes\MixedPropertyHolder;
use MagicSunday\Test\Classes\NullableStringHolder;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\ReadonlyPropertyHolder;
use MagicSunday\Test\Classes\ReadonlyValueObject;
use MagicSunday\Test\Classes\ReplaceNullCollectionHolder;
use MagicSunday\Test\Classes\ReplaceNullWithNullDefaultHolder;
use MagicSunday\Test\Classes\ReplaceNullWithoutDefaultHolder;
use MagicSunday\Test\Classes\SameNamedConstructorParameterHolder;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionHolder;
use MagicSunday\Test\Classes\UntypedPropertyHolder;
use MagicSunday\Test\Classes\UntypedReplaceNullCollectionHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

use function get_debug_type;

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
        // A readonly property that is NOT a constructor parameter cannot be built through the
        // constructor, so mapping it still surfaces a readonly-write violation. (A readonly
        // property that IS a promoted constructor parameter is hydrated via the constructor
        // instead — see ConstructorHydrationTest.)
        $result = $this->getJsonMapper()
            ->mapWithReport([
                'id' => 'changed',
            ], ReadonlyPropertyHolder::class);

        $entity = $result->getValue();

        self::assertInstanceOf(ReadonlyPropertyHolder::class, $entity);
        self::assertSame('initial', $entity->id, 'the readonly value is unchanged when it cannot be written');

        $errors = $result->getReport()->getErrors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(ReadonlyPropertyException::class, $errors[0]->getException());
        self::assertSame('Readonly property ' . ReadonlyPropertyHolder::class . '::id cannot be written at $.id.', $errors[0]->getMessage());
    }

    #[Test]
    public function itThrowsOnInvalidNestedCollectionEntriesInStrictMode(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('Type mismatch at $.simpleArray.1.int: expected int, got string.', '/') . '/');

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
    public function itThrowsTypeMismatchWhenNullIsMappedOntoNonNullablePropertyInStrictMode(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('Type mismatch at $.name: expected string, got null.', '/') . '/');

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
    public function itCollectsTypeMismatchWhenNullIsMappedOntoNonNullableScalarProperty(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['name' => null],
                Person::class,
            );

        $person = $result->getValue();

        self::assertInstanceOf(Person::class, $person);
        self::assertFalse((new ReflectionProperty(Person::class, 'name'))->isInitialized($person));

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.name: expected string, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itCollectsTypeMismatchWhenNullIsMappedOntoNonNullableObjectProperty(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'name'   => 'John Doe',
                    'simple' => null,
                ],
                Base::class,
            );

        $base = $result->getValue();

        self::assertInstanceOf(Base::class, $base);

        // Base::$simple is untyped and therefore already null before mapping, so asserting null
        // here would pass whether the value was skipped or assigned. The error assertions below
        // carry the test instead.
        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.simple: expected MagicSunday\Test\Classes\Simple, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itCollectsTypeMismatchWhenNullIsMappedOntoNonNullableCollectionProperty(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'name'        => 'John Doe',
                    'simpleArray' => null,
                ],
                Base::class,
            );

        $base = $result->getValue();

        self::assertInstanceOf(Base::class, $base);

        // As above: Base::$simpleArray is untyped and already null, so the assertion would not
        // discriminate a skip from an assignment.
        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.simpleArray: expected array, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itCollectsTypeMismatchWhenNullIsMappedOntoNonNullableUnionProperty(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['value' => null],
                UnionHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionHolder::class, $holder);
        self::assertFalse((new ReflectionProperty(UnionHolder::class, 'value'))->isInitialized($holder));

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(
            'Type mismatch at $.value: expected int|string, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itAssignsNullToUntypedPropertyWithoutError(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['anything' => null],
                UntypedPropertyHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UntypedPropertyHolder::class, $holder);
        self::assertNull($holder->anything);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsNullToNullablePropertyWithoutError(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['value' => null],
                NullableStringHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(NullableStringHolder::class, $holder);
        self::assertNull($holder->value);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itAssignsNullToMixedPropertyWithoutError(): void
    {
        // The holder seeds a sentinel default, so asserting null proves the null was actually
        // assigned rather than the property having been left untouched.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['value' => null],
                MixedPropertyHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(MixedPropertyHolder::class, $holder);
        self::assertNull($holder->value);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itReplacesEmptyStringWithDefaultValueWhenEmptyStringAsNullIsEnabled(): void
    {
        $configuration = JsonMapperConfiguration::lenient()
            ->withEmptyStringAsNull(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['integer' => ''],
                Initialized::class,
                null,
                $configuration,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(Initialized::class, $holder);
        self::assertSame(10, $holder->integer);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itPrefersReplaceNullWithDefaultValueOverTreatNullAsEmptyCollection(): void
    {
        $configuration = JsonMapperConfiguration::lenient()
            ->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['items' => null],
                ReplaceNullCollectionHolder::class,
                null,
                $configuration,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(ReplaceNullCollectionHolder::class, $holder);
        self::assertSame(['preset'], $holder->items);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itTreatsNullAsEmptyCollectionForUnionTypedCollectionProperty(): void
    {
        $configuration = JsonMapperConfiguration::lenient()
            ->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'name'             => 'John Doe',
                    'simpleCollection' => null,
                ],
                Base::class,
                null,
                $configuration,
            );

        $base = $result->getValue();

        self::assertInstanceOf(Base::class, $base);
        self::assertInstanceOf(Collection::class, $base->simpleCollection);
        self::assertCount(0, $base->simpleCollection);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itReportsTypeMismatchForNullOnAUnionTypedCollectionWithoutTheOption(): void
    {
        // Counterpart to the test above: with the option off, the same union member must not
        // absorb the null, so the mismatch surfaces with the full union description.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'name'             => 'John Doe',
                    'simpleCollection' => null,
                ],
                Base::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame('$.simpleCollection', $errors[0]->getPath());

        // The descriptor is the point of this branch: the union must be reported in full rather
        // than as whichever candidate happened to be tried last.
        self::assertStringContainsString(
            'Type mismatch at $.simpleCollection: expected ',
            $errors[0]->getMessage(),
        );
        self::assertStringEndsWith(', got null.', $errors[0]->getMessage());
    }

    #[Test]
    public function itCollectsTypeMismatchWhenNullIsMappedOntoAnnotatedPropertyWithoutDefault(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['number' => null],
                ReplaceNullWithoutDefaultHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(ReplaceNullWithoutDefaultHolder::class, $holder);
        self::assertFalse((new ReflectionProperty(ReplaceNullWithoutDefaultHolder::class, 'number'))->isInitialized($holder));

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.number: expected int, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itAssignsCompatibleValueToUntypedPropertyWithoutError(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['anything' => 'text'],
                UntypedPropertyHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UntypedPropertyHolder::class, $holder);
        self::assertSame('text', $holder->anything);
        self::assertFalse($result->getReport()->hasErrors());
    }

    /**
     * @param int|float|bool|string|array<array-key, mixed>|null $payload  Value handed to the mapper.
     * @param string                                             $expected Type the property must hold afterwards.
     */
    #[Test]
    #[DataProvider('untypedPayloadProvider')]
    public function itPreservesEveryShapeOnAnUntypedProperty(
        int|float|bool|string|array|null $payload,
        string $expected,
    ): void {
        // An array reaching a string-typed fallback used to emit "Array to string conversion" and
        // write the literal 'Array'. The composite rejection removed the artifact; this removes
        // the cause, so the value simply arrives.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['anything' => $payload],
                UntypedPropertyHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UntypedPropertyHolder::class, $holder);
        self::assertSame($expected, get_debug_type($holder->anything));
        self::assertFalse($result->getReport()->hasErrors());
    }

    /**
     * @return array<string, array{int|float|bool|string|array<array-key, mixed>|null, string}>
     */
    public static function untypedPayloadProvider(): array
    {
        return [
            'associative array' => [['a' => 1], 'array'],
            'list'              => [[1, 2, 3], 'array'],
            'int'               => [42, 'int'],
            'float'             => [1.5, 'float'],
            'bool'              => [true, 'bool'],
            'string'            => ['plain', 'string'],
            'null'              => [null, 'null'],
        ];
    }

    #[Test]
    public function itPassesAnyValueThroughAnUntypedProperty(): void
    {
        // A property declaring no type makes no claim about its value, so the mapper does not
        // invent one. The fallback used to be nullable string, which meant only strings survived:
        // anything else was reported as a mismatch and skipped.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['anything' => 42],
                UntypedPropertyHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UntypedPropertyHolder::class, $holder);
        self::assertSame(42, $holder->anything, 'The value arrives as it was decoded.');
        self::assertFalse($result->getReport()->hasErrors(), 'Nothing to report - nothing was violated.');
    }

    #[Test]
    public function itRecordsMissingConstructorArgumentWhenNullIsMappedOntoRequiredPromotedParameter(): void
    {
        // The type mismatch is collected and the value skipped, so constructor hydration runs
        // without the argument and raises MissingConstructorArgumentException even in lenient
        // mode. That second failure sits on the root object and used to escape mapWithReport()
        // entirely; both are recorded now, in the order they occur.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                [
                    'name' => null,
                    'age'  => 36,
                ],
                ReadonlyValueObject::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertNull($result->getValue());
        self::assertCount(2, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertInstanceOf(MissingConstructorArgumentException::class, $errors[1]->getException());
        self::assertMatchesRegularExpression(
            '/' . preg_quote('ReadonlyValueObject::$name', '/') . '/',
            $errors[1]->getMessage(),
        );
    }

    #[Test]
    public function itCollectsTypeMismatchWhenEmptyStringNormalizesToNullOnNonNullableProperty(): void
    {
        $configuration = JsonMapperConfiguration::lenient()
            ->withEmptyStringAsNull(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['name' => ''],
                Person::class,
                null,
                $configuration,
            );

        $person = $result->getValue();

        self::assertInstanceOf(Person::class, $person);
        self::assertFalse((new ReflectionProperty(Person::class, 'name'))->isInitialized($person));

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.name: expected string, got null.',
            $errors[0]->getMessage(),
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

    #[Test]
    public function itLeavesAConstructorInitialisedValueIntactWhenTheNullIsRejected(): void
    {
        // A rejected null must not clobber what the constructor already put in place. The holder
        // seeds 7 during construction, so the property is observable both before and after the
        // failed mapping — which is what distinguishes "value skipped" from "value overwritten".
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['count' => null],
                ReplaceNullWithNullDefaultHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(ReplaceNullWithNullDefaultHolder::class, $holder);
        self::assertSame(7, $holder->count);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.count: expected int, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itIgnoresANonPromotedConstructorParameterSharingThePropertyName(): void
    {
        // Only a promoted parameter carries the property's default. A plain parameter that
        // merely shares the name has no type relationship to the property, so its default
        // must never be assigned. With no usable default the value takes the regular pipeline
        // and its null guard.
        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['count' => null],
                SameNamedConstructorParameterHolder::class,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.count: expected int, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itKeepsTheEmptyCollectionForAnUntypedPropertyMarkedReplaceNullWithDefaultValue(): void
    {
        // An untyped property reports a null default through reflection. Treating that as a
        // usable default would strip the empty-collection behaviour the option promises.
        $configuration = JsonMapperConfiguration::lenient()
            ->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['items' => null],
                UntypedReplaceNullCollectionHolder::class,
                null,
                $configuration,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UntypedReplaceNullCollectionHolder::class, $holder);
        self::assertSame([], $holder->items);
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itNormalizesAnEmptyStringBeforeTheUnionCandidatesAreTried(): void
    {
        // Discriminating case for where the empty-string normalization happens: the union
        // accepts string, so an unnormalized "" would simply be assigned. Reaching the union
        // as null instead proves the normalization runs once, ahead of candidate selection.
        $configuration = JsonMapperConfiguration::lenient()
            ->withEmptyStringAsNull(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['value' => ''],
                UnionHolder::class,
                null,
                $configuration,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.value: expected int|string, got null.',
            $errors[0]->getMessage(),
        );
    }

    #[Test]
    public function itReportsTypeMismatchForNullOnAUnionWithoutACollectionMemberDespiteTheOption(): void
    {
        // The treat-null-as-empty-collection option must not swallow a genuine null mismatch
        // when no union member is a collection.
        $configuration = JsonMapperConfiguration::lenient()
            ->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper()
            ->mapWithReport(
                ['value' => null],
                UnionHolder::class,
                null,
                $configuration,
            );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame(
            'Type mismatch at $.value: expected int|string, got null.',
            $errors[0]->getMessage(),
        );
    }
}
