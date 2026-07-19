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
use MagicSunday\Test\Classes\UnionObjectHolder;
use MagicSunday\Test\Classes\UnionScalarHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Union candidate selection used to be decided by counting recorded errors. With error collection
 * switched off nothing was recorded, the count never moved, and the FIRST candidate always won -
 * so an unrelated configuration flag silently changed which type a value was mapped to.
 *
 * @internal
 */
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

    /**
     * @param bool $collect Whether the configuration collects errors.
     */
    #[Test]
    #[DataProvider('errorCollectionProvider')]
    public function itDoesNotLeakCandidateEvaluationErrorsIntoTheReport(bool $collect): void
    {
        // Evaluating candidates has to try types that will not match. Those attempts are internal
        // and must not show up as errors for a value that ultimately mapped fine.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withErrorCollection($collect))
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
    }
}
