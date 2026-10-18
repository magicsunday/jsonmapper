<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\NullableIntHolder;
use MagicSunday\Test\Classes\TwoRequiredPropertiesDto;
use MagicSunday\Test\Classes\TwoRequiredPropertiesHolder;
use MagicSunday\Test\Classes\UnionChainA;
use MagicSunday\Test\Classes\UnionChainB;
use MagicSunday\Test\Classes\UnionLeadingDto;
use MagicSunday\Test\Classes\UnionLeadingTrailingHolder;
use MagicSunday\Test\Classes\UnionObjectHolder;
use MagicSunday\Test\Classes\UnionScalarHolder;
use MagicSunday\Test\Classes\UnionThenFailingHolder;
use MagicSunday\Test\Classes\UnionTwoRequiredPropertiesHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Union candidate selection used to be decided by counting recorded errors. With error collection
 * switched off nothing was recorded, the count never moved, and the FIRST candidate always won -
 * so an unrelated configuration flag silently changed which type a value was mapped to.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class UnionResolutionTest extends TestCase
{
    /**
     * Both settings of the error-collection flag. Union resolution has to behave identically under
     * either, which is the whole point of these cases.
     *
     * @return array<string, array{bool}>
     */
    public static function errorCollectionProvider(): array
    {
        return [
            'error collection on'  => [true],
            'error collection off' => [false],
        ];
    }

    /**
     * @param bool $collect Whether the configuration collects errors.
     */
    #[Test]
    #[DataProvider('errorCollectionProvider')]
    public function itRejectsAValueMatchingNoUnionMemberRegardlessOfErrorCollection(bool $collect): void
    {
        $config = JsonMapperConfiguration::lenient()->withErrorCollection($collect);

        $result = $this->getJsonMapper(config: $config)->map(
            ['value' => ['nested' => true]],
            UnionScalarHolder::class,
        );

        // Pins the user-facing contract only. It does NOT discriminate the candidate-selection fix
        // on its own: both scalar members reject this payload by throwing, and the catch in
        // resolveUnionCandidate() handles that regardless of the flag. The discriminating case is
        // itRejectsAnObjectCandidateThatRecordsRatherThanThrows below, where the candidate records
        // instead of throwing and the recorded count is the only available signal.
        self::assertInstanceOf(UnionScalarHolder::class, $result);
        self::assertSame(
            'untouched',
            $result->value,
            'A value matching no union member must not be assigned.',
        );
    }

    /**
     * @param bool $collect Whether the configuration collects errors.
     */
    #[Test]
    #[DataProvider('errorCollectionProvider')]
    public function itRejectsAnObjectCandidateThatRecordsRatherThanThrows(bool $collect): void
    {
        // The discriminating case for the fix itself. A scalar candidate rejects by throwing,
        // which the surrounding catch handles regardless of the flag - so a scalar-only union
        // cannot tell the two implementations apart. An object candidate with a bad nested field
        // RECORDS instead, and the recorded count is the only signal the selection has. With the
        // flag off nothing was recorded, so the object candidate looked like a match and a Person
        // was built from an invalid payload.
        $config = JsonMapperConfiguration::lenient()->withErrorCollection($collect);

        $result = $this->getJsonMapper(config: $config)->map(
            ['value' => ['name' => ['nested' => true]]],
            UnionObjectHolder::class,
        );

        self::assertInstanceOf(UnionObjectHolder::class, $result);
        self::assertSame('untouched', $result->value);
    }

    /**
     * @param bool $collect Whether the configuration collects errors.
     */
    #[Test]
    #[DataProvider('errorCollectionProvider')]
    public function itPicksTheMatchingCandidateRegardlessOfErrorCollection(bool $collect): void
    {
        $config = JsonMapperConfiguration::lenient()->withErrorCollection($collect);

        $result = $this->getJsonMapper(config: $config)->map(
            ['value' => 'a string'],
            UnionScalarHolder::class,
        );

        // A mapper that simply takes the first candidate would coerce this to an int and still look
        // plausible. Note that the mirror direction cannot be tested: Symfony TypeInfo normalises
        // union members, so a `string|int` declaration is indistinguishable from `int|string` at
        // runtime and no fixture can put the string member first.
        self::assertInstanceOf(UnionScalarHolder::class, $result);
        self::assertSame('a string', $result->value);
    }

    /**
     * @param bool $collect Whether the configuration collects errors.
     */
    #[Test]
    #[DataProvider('errorCollectionProvider')]
    public function itKeepsAnIntegerAsAnIntegerRegardlessOfErrorCollection(bool $collect): void
    {
        // The positive control for the int member. Deliberately NOT a discriminator: a mapper that
        // simply takes the first candidate produces 42 here too. It guards against a fix that
        // rejects everything.
        $config = JsonMapperConfiguration::lenient()->withErrorCollection($collect);

        $result = $this->getJsonMapper(config: $config)->map(
            ['value' => 42],
            UnionScalarHolder::class,
        );

        self::assertInstanceOf(UnionScalarHolder::class, $result);
        self::assertSame(42, $result->value);
    }

    #[Test]
    public function itDoesNotLeakCandidateEvaluationErrorsIntoTheReport(): void
    {
        // Evaluating candidates has to try types that will not match. Those attempts are internal
        // and must not show up as errors for a value that ultimately mapped fine.
        //
        // Deliberately not parametrised over the error-collection flag: mapWithReport() forces
        // collection on regardless of what the configuration says, so both rows would be the same
        // execution and the "regardless of the flag" claim would be unproven here.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(
                ['value' => 'a string'],
                UnionScalarHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionScalarHolder::class, $holder);
        self::assertSame('a string', $holder->value);
        self::assertFalse($result->getReport()->hasErrors(), 'A successful union match records nothing.');
    }

    #[Test]
    public function itRestoresErrorCollectionAfterResolvingAUnion(): void
    {
        // Resolving a union forces error collection on so the candidate attempts can be counted.
        // The property declared AFTER the union is what makes a missing restore observable: with a
        // single-property fixture the forced window is the last thing that happens, so a leaked
        // flag would go unnoticed and a caller who switched collection off would silently start
        // collecting for the remainder of the run.
        $payload = [
            'value' => ['name' => ['nested' => true]],
            'count' => ['a'],
        ];

        // Positive control first. Without it the assertion below passes for three different
        // reasons - the restore worked, 'count' never failed, or mapping stopped before reaching
        // it - and only the first is the contract. This proves the trailing property really does
        // fail and really is recordable.
        $collectingConfig  = JsonMapperConfiguration::lenient()->withErrorCollection(true);
        $collectingContext = new MappingContext([], $collectingConfig->toOptions());

        $this->getJsonMapper(config: $collectingConfig)->map(
            $payload,
            UnionThenFailingHolder::class,
            null,
            $collectingContext,
        );

        $recordedPaths = array_map(
            static fn (MappingError $error): string => $error->getPath(),
            $collectingContext->getErrorRecords(),
        );

        // Both the rejected union and the trailing property are recorded when collection is on.
        // Asserting the paths rather than a bare count states which failures are expected.
        self::assertSame(['$.value', '$.count'], $recordedPaths);

        $config  = JsonMapperConfiguration::lenient()->withErrorCollection(false);
        $context = new MappingContext([], $config->toOptions());

        $holder = $this->getJsonMapper(config: $config)->map(
            $payload,
            UnionThenFailingHolder::class,
            null,
            $context,
        );

        // Anchors that the fixture still behaves as designed: without this the test also passes
        // if 'count' were silently coerced, leaving nothing to record in the first place.
        self::assertInstanceOf(UnionThenFailingHolder::class, $holder);
        self::assertSame(-1, $holder->count, 'The failing property kept its default.');

        self::assertSame(
            0,
            $context->getErrorCount(),
            'The failing property after the union must not be recorded while collection is off.',
        );
        self::assertFalse(
            $context->shouldCollectErrors(),
            'The forced collection must not outlive the union it was forced for.',
        );
    }

    #[Test]
    public function itDoesNotMaterialiseAnOptionTheCallerNeverSet(): void
    {
        // The other arm of the restore. Every context the library builds comes from
        // toOptions(), which always writes the key, so only a directly constructed context
        // reaches this path. shouldCollectErrors() cannot tell the two arms apart - it reads
        // true whether the key is absent or was left behind as true - so the raw option bag is
        // the only discriminator, which is exactly why the restore distinguishes them.
        $context = new MappingContext([]);

        self::assertTrue($context->shouldCollectErrors(), 'Collection is on by default.');

        $context->withForcedErrorCollection(static fn (): bool => true);

        self::assertArrayNotHasKey(
            MappingContext::OPTION_COLLECT_ERRORS,
            $context->getOptions(),
            'An option that was never set must not be materialised by the forced window.',
        );
    }

    #[Test]
    public function itRecordsExactlyOneErrorWhenNoCandidateMatches(): void
    {
        // Pins the trimming: evaluating candidates records per member, so without the trim the
        // caller would see one error per union member instead of one for the union.
        $result = $this->getJsonMapper()->mapWithReport(
            ['value' => ['nested' => true]],
            UnionScalarHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'A rejected union value produces one record, not one per candidate.');

        // Cardinality alone would also pass if the trim collapsed to the WRONG single record - a
        // leftover candidate-level error at $.value.nested instead of the union-level one.
        self::assertSame(
            ['$.value'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
        );
    }

    #[Test]
    public function itKeepsTheStrictRecordsOfTheMatchedMember(): void
    {
        // The member is chosen on type alone, and strict mode then still holds the matched object
        // to its required properties, so what it lacks reaches the report.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['dto' => []],
            UnionTwoRequiredPropertiesHolder::class,
        );

        $holder = $result->getValue();

        self::assertSame(
            ['$.dto.first', '$.dto.second'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
        );
        self::assertInstanceOf(UnionTwoRequiredPropertiesHolder::class, $holder);
        self::assertInstanceOf(TwoRequiredPropertiesDto::class, $holder->dto);
    }

    #[Test]
    public function itRaisesTheStrictFailureOfTheMatchedMemberWhenTheRunAborts(): void
    {
        // map() raises on the first strict failure. A union member must not turn that into a
        // silent success, nor into a raise naming a member that was merely tried first.
        try {
            $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
                ['dto' => []],
                UnionTwoRequiredPropertiesHolder::class,
            );

            self::fail('A strict run that aborts has to raise the missing property.');
        } catch (MissingPropertyException $exception) {
            self::assertSame('$.dto.first', $exception->getPath());
        }
    }

    #[Test]
    public function itFilesTheRecordsOfTheMatchedMemberAtTheSamePathsAsAPlainProperty(): void
    {
        // An aborting run files the first missing property where it is and then the property that holds
        // the object. A union must leave the same trail as a plain property, not only the outer path.
        $paths = [];

        foreach ([TwoRequiredPropertiesHolder::class, UnionTwoRequiredPropertiesHolder::class] as $class) {
            $payload = ['dto' => []];
            $context = new MappingContext($payload, JsonMapperConfiguration::strict()->toOptions());

            try {
                $this->getJsonMapper(config: JsonMapperConfiguration::strict())
                    ->map($payload, $class, context: $context);

                self::fail('A strict run that aborts has to raise the missing property.');
            } catch (MissingPropertyException) {
                $paths[$class] = array_map(
                    static fn (MappingError $error): string => $error->getPath(),
                    $context->getErrorRecords(),
                );
            }
        }

        self::assertSame(['$.dto.first', '$.dto'], $paths[TwoRequiredPropertiesHolder::class]);
        self::assertSame(
            $paths[TwoRequiredPropertiesHolder::class],
            $paths[UnionTwoRequiredPropertiesHolder::class],
        );
    }

    #[Test]
    public function itLeavesNoRecordInTheContextWhenAnAbortingRunCollectsNothing(): void
    {
        // The plain property is the reference: it leaves nothing behind when collection is off.
        $counts = [];

        foreach ([TwoRequiredPropertiesHolder::class, UnionTwoRequiredPropertiesHolder::class] as $class) {
            $config  = JsonMapperConfiguration::strict()->withErrorCollection(false);
            $context = new MappingContext(['dto' => []], $config->toOptions());

            try {
                $this->getJsonMapper(config: $config)->map(['dto' => []], $class, context: $context);

                self::fail('A strict run that aborts has to raise the missing property.');
            } catch (MissingPropertyException) {
                $counts[$class] = $context->getErrorCount();
            }
        }

        self::assertSame(0, $counts[TwoRequiredPropertiesHolder::class]);
        self::assertSame(0, $counts[UnionTwoRequiredPropertiesHolder::class]);
    }

    #[Test]
    public function itRaisesTheStrictFailureOfTheMatchedMemberEvenWhenNothingIsCollected(): void
    {
        // Strict mode aborts on the first failure whether or not failures are kept, so switching
        // collection off must not turn the raise into a silent success. The plain property is the
        // reference: it raises under the same configuration.
        $config = JsonMapperConfiguration::strict()->withErrorCollection(false);

        try {
            $this->getJsonMapper(config: $config)->map(
                ['dto' => []],
                TwoRequiredPropertiesHolder::class,
            );

            self::fail('The plain property is expected to raise.');
        } catch (MissingPropertyException $plain) {
            self::assertSame('$.dto.first', $plain->getPath());
        }

        try {
            $this->getJsonMapper(config: $config)->map(
                ['dto' => []],
                UnionTwoRequiredPropertiesHolder::class,
            );

            self::fail('The union has to raise as well.');
        } catch (MissingPropertyException $union) {
            self::assertSame($plain->getPath(), $union->getPath());
        }
    }

    #[Test]
    public function itLeavesNoRecordBehindWhenCollectionIsOffAndTheRunDoesNotAbort(): void
    {
        // The combination only a context built by hand can express: strict, not collecting and
        // not aborting. The trial forces collection on, so what the matched member recorded must
        // not outlive the window in a context that was told not to keep anything.
        $context = new MappingContext(
            ['dto' => []],
            [
                MappingContext::OPTION_STRICT_MODE    => true,
                MappingContext::OPTION_COLLECT_ERRORS => false,
                MappingContext::OPTION_ABORT_ON_ERROR => false,
            ],
        );

        $holder = $this->getJsonMapper()->map(
            ['dto' => []],
            UnionTwoRequiredPropertiesHolder::class,
            null,
            $context,
        );

        self::assertInstanceOf(UnionTwoRequiredPropertiesHolder::class, $holder);
        self::assertInstanceOf(TwoRequiredPropertiesDto::class, $holder->dto);
        self::assertSame(0, $context->getErrorCount());
    }

    #[Test]
    public function itReportsNothingForTheMatchedMemberOutsideStrictMode(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['dto' => []],
            UnionTwoRequiredPropertiesHolder::class,
        );

        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itStillSettlesOnTheScalarMemberInStrictMode(): void
    {
        // Strict mode staying on during the trial must not change which member a value settles
        // on: this one has the type of the scalar member and needs no conversion.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['dto' => 5],
            UnionTwoRequiredPropertiesHolder::class,
        );

        $holder = $result->getValue();

        self::assertFalse($result->getReport()->hasErrors());
        self::assertInstanceOf(UnionTwoRequiredPropertiesHolder::class, $holder);
        self::assertSame(5, $holder->dto);
    }

    #[Test]
    public function itSettlesOnTheFirstMatchingMemberAndReportsWhatItLacks(): void
    {
        // A missing property does not decide which member is used: the first member whose types
        // match the payload is, even when a later member would need nothing reported. Looking for
        // that later member would convert every member's subtree, and a union nested in its own
        // members would then multiply the work per level.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['member' => ['first' => 'x']],
            UnionLeadingTrailingHolder::class,
        );

        $holder = $result->getValue();

        self::assertSame(
            ['$.member.second'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
        );
        self::assertInstanceOf(UnionLeadingTrailingHolder::class, $holder);
        self::assertInstanceOf(UnionLeadingDto::class, $holder->member);
    }

    #[Test]
    public function itDoesTheWorkOfOneMemberPerLevelForANestedUnionOfIncompleteObjects(): void
    {
        // The count of objects built is the deterministic witness for the work done. Trying the
        // other member after one that only lacks a property builds the whole subtree again for it
        // at every level, so the count would grow with the power of the depth instead of the depth.
        $depth   = 12;
        $payload = ['child' => null];

        for ($level = 0; $level < $depth; ++$level) {
            $payload = ['child' => $payload];
        }

        UnionChainA::$built = 0;
        UnionChainB::$built = 0;

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport($payload, UnionChainA::class);

        self::assertSame(
            $depth + 1,
            UnionChainA::$built + UnionChainB::$built,
            'The root and one object per nesting level, none of them built twice.',
        );
    }

    #[Test]
    public function itStillRejectsAMemberForAnUnknownPropertyInStrictMode(): void
    {
        // Pins what strict mode never changed: an unknown key rejects the object member, so the
        // value falls to the scalar member and the union-level mismatch is what gets reported.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['dto' => ['first' => 'a', 'second' => 'b', 'extra' => 1]],
            UnionTwoRequiredPropertiesHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertSame(
            ['$.dto'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $errors,
            ),
        );
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itKeepsNullForANullableScalarThatCannotBeConverted(): void
    {
        // A nullable property is a union with null and takes the same candidate search as any
        // other union: a payload no member accepts leaves the property without a value and
        // records the mismatch, rather than storing a coerced stand-in for it.
        $result = $this->getJsonMapper()->mapWithReport(['value' => 'abc'], NullableIntHolder::class);

        $holder = $result->getValue();

        self::assertInstanceOf(NullableIntHolder::class, $holder);
        self::assertNull($holder->value);
        self::assertSame(
            ['$.value'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
        );
    }
}
