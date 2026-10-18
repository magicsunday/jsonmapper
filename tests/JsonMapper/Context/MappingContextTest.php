<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Context;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_is_list;
use function array_map;

use const PHP_INT_MAX;

/**
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class MappingContextTest extends TestCase
{
    #[Test]
    public function itTracksPathSegments(): void
    {
        $context = new MappingContext(['root']);

        self::assertSame('$', $context->getPath());

        $result = $context->withPathSegment('items', function (MappingContext $child): string {
            self::assertSame('$.items', $child->getPath());

            $child->withPathSegment(0, function (MappingContext $nested): void {
                self::assertSame('$.items.0', $nested->getPath());
            });

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame('$', $context->getPath());
    }

    #[Test]
    public function itCollectsErrors(): void
    {
        $context = new MappingContext(['root']);
        $context->addError('failure');

        self::assertSame(['failure'], $context->getErrors());
    }

    #[Test]
    public function itExposesOptions(): void
    {
        $context = new MappingContext(['root'], ['flag' => true]);

        self::assertSame(['flag' => true], $context->getOptions());
        self::assertTrue($context->getOption('flag'));
        self::assertSame('fallback', $context->getOption('missing', 'fallback'));
    }

    #[Test]
    public function itProvidesTypedOptionAccessors(): void
    {
        $context = new MappingContext(['root'], [
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
        ]);

        self::assertTrue($context->shouldIgnoreUnknownProperties());
        self::assertTrue($context->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $context->getDefaultDateFormat());
        self::assertTrue($context->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itDefersTheAbortDecisionToStrictModeUntilOverridden(): void
    {
        // The only accessor with a non-constant default: absent, it answers with strict mode, so
        // map() keeps aborting without anyone having to set the option. Both fallback directions
        // are pinned - a default of plain false would leave strict map() collecting silently, and
        // a default of plain true would make mapWithReport()'s override the only thing standing
        // between a lenient caller and an exception.
        self::assertTrue((new MappingContext(['root'], [
            MappingContext::OPTION_STRICT_MODE => true,
        ]))->shouldAbortOnError(), 'Absent option defers to strict mode.');

        self::assertFalse((new MappingContext(['root']))->shouldAbortOnError(), 'Lenient never aborts.');

        // Set explicitly, it wins over strict mode in BOTH directions - that override is the whole
        // mechanism by which mapWithReport() collects what map() would have raised.
        self::assertFalse((new MappingContext(['root'], [
            MappingContext::OPTION_STRICT_MODE    => true,
            MappingContext::OPTION_ABORT_ON_ERROR => false,
        ]))->shouldAbortOnError(), 'The explicit option overrides strict mode.');

        self::assertTrue((new MappingContext(['root'], [
            MappingContext::OPTION_ABORT_ON_ERROR => true,
        ]))->shouldAbortOnError(), 'The explicit option overrides lenient mode too.');
    }

    #[Test]
    public function itRecordsAFailureWhenTheRunCollects(): void
    {
        $context = new MappingContext(['root']);

        $context->throwOrRecord(new TypeMismatchException('$.value', 'int', 'string'));

        self::assertSame(1, $context->getErrorCount());
    }

    #[Test]
    public function itRaisesAFailureWhenTheRunAborts(): void
    {
        $context = new MappingContext(['root'], [MappingContext::OPTION_ABORT_ON_ERROR => true]);

        try {
            $context->throwOrRecord(new TypeMismatchException('$.value', 'int', 'string'));

            self::fail('An aborting run must raise.');
        } catch (TypeMismatchException) {
            // Expected - what matters is that nothing was recorded on the way out.
        }

        // Nothing recorded: the catch site that receives the throw records it, so recording here
        // too would file the same failure twice. That is the whole reason the helper raises first.
        self::assertSame(0, $context->getErrorCount(), 'The raising path records nothing.');
    }

    #[Test]
    public function itFollowsStrictModeWhenTheAbortOptionIsAbsent(): void
    {
        // The helper asks shouldAbortOnError(), so it inherits that accessor's fallback rather
        // than repeating the decision - which is the duplication it exists to remove.
        $context = new MappingContext(['root'], [MappingContext::OPTION_STRICT_MODE => true]);

        $this->expectException(TypeMismatchException::class);

        $context->throwOrRecord(new TypeMismatchException('$.value', 'int', 'string'));
    }

    #[Test]
    public function itKeepsNoMoreRecordsThanTheCeilingAndCountsWhatItDropped(): void
    {
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 2]);

        for ($index = 0; $index < 5; ++$index) {
            $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        }

        self::assertSame(2, $context->getErrorCount());
        self::assertSame(3, $context->getDroppedErrorCount());
    }

    #[Test]
    public function itDoesNotFileTheFailureOfTheLatestRecordAgain(): void
    {
        $context = new MappingContext(['root']);
        $failure = new TypeMismatchException('$.value', 'int', 'string');

        $context->recordException($failure);
        $context->recordException($failure);

        self::assertSame(1, $context->getErrorCount());
    }

    #[Test]
    public function itFilesADifferentFailureAtTheSamePath(): void
    {
        $context = new MappingContext(['root']);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        self::assertSame(2, $context->getErrorCount());
    }

    #[Test]
    public function itAppliesADefaultCeilingWhenNoneIsSet(): void
    {
        $context = new MappingContext(['root']);

        // The documented default, as a literal: a comparison with the constant would follow it
        // wherever it moved.
        for ($index = 0; $index < 1003; ++$index) {
            $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        }

        self::assertSame(1000, $context->getErrorCount());
        self::assertSame(3, $context->getDroppedErrorCount());
    }

    #[Test]
    public function itFallsBackToTheDefaultCeilingForAnUnusableOption(): void
    {
        // A context can be built by hand with any value in the bag. A ceiling of zero would keep
        // nothing at all, and a value of the wrong type has no meaning as a number.
        foreach ([0, -1, 'many', 1.5, null] as $unusable) {
            self::assertSame(
                MappingContext::DEFAULT_MAX_ERRORS,
                (new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => $unusable]))->getMaxErrors(),
                'Unusable ceiling: ' . var_export($unusable, true),
            );
        }
    }

    #[Test]
    public function itDoesNotCountAFailureItWasToldNotToCollect(): void
    {
        $context = new MappingContext(['root'], [
            MappingContext::OPTION_COLLECT_ERRORS => false,
            MappingContext::OPTION_MAX_ERRORS     => 1,
        ]);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        self::assertSame(0, $context->getErrorCount());
        self::assertSame(0, $context->getDroppedErrorCount(), 'Nothing is dropped that was never meant to be kept.');
    }

    #[Test]
    public function itDoesNotCountADroppedFailureOfARunThatStoppedCollecting(): void
    {
        // The ceiling is reached while the run still collected. A failure that arrives after
        // collection was switched off is not one the report lost, so it is not counted either.
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 1]);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        $context->replaceOptions([MappingContext::OPTION_COLLECT_ERRORS => false]);
        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        self::assertSame(1, $context->getErrorCount());
        self::assertSame(0, $context->getDroppedErrorCount());
    }

    #[Test]
    #[DataProvider('ceilingValueProvider')]
    public function itNormalisesAConfiguredCeiling(mixed $value, int $expected): void
    {
        self::assertSame($expected, MappingContext::normaliseMaxErrors($value));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function ceilingValueProvider(): array
    {
        return [
            'an integer'                => [25, 25],
            'a numeric string'          => ['25', 25],
            'a float, even a whole one' => [25.0, MappingContext::DEFAULT_MAX_ERRORS],
            'the smallest usable value' => [1, 1],
            'zero keeps nothing'        => [0, MappingContext::DEFAULT_MAX_ERRORS],
            'a negative number'         => [-4, MappingContext::DEFAULT_MAX_ERRORS],
            'a fraction'                => [2.5, MappingContext::DEFAULT_MAX_ERRORS],
            'a word'                    => ['many', MappingContext::DEFAULT_MAX_ERRORS],
            'a boolean is not a number' => [true, MappingContext::DEFAULT_MAX_ERRORS],
            'not a number'              => [NAN, MappingContext::DEFAULT_MAX_ERRORS],
            'infinity'                  => [INF, MappingContext::DEFAULT_MAX_ERRORS],
            'null'                      => [null, MappingContext::DEFAULT_MAX_ERRORS],
            'an array'                  => [[25], MappingContext::DEFAULT_MAX_ERRORS],
        ];
    }

    #[Test]
    public function itBoundsWhatATrialHoldsByTheCeilingAboveItsStart(): void
    {
        // A trial records past the ceiling the report is held to, which the decision it serves
        // needs. It does not record without limit, or a hostile candidate would decide how much
        // is held before the trial trims it. Refusing inside a window loses nothing, so it is no
        // dropped failure.
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 2]);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        $context->withForcedErrorCollection(function (MappingContext $trial): void {
            for ($index = 0; $index < 10; ++$index) {
                $trial->recordException(new TypeMismatchException('$.value', 'int', 'string'));
            }

            self::assertSame(3, $trial->getErrorCount(), 'The window holds the ceiling above where it began.');
            self::assertSame(0, $trial->getDroppedErrorCount());
        });
    }

    #[Test]
    public function itLetsANestedWindowShareTheOuterAllowanceButAlwaysLeavesItRoomForOneRecord(): void
    {
        // Windows inside windows are what nested unions produce. Each one still has to see the
        // record its own decision needs, even when the outer allowance is spent, and a payload that
        // nests them must not collect one allowance per level.
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 3]);

        $record = static function (MappingContext $window, int $times): void {
            for ($index = 0; $index < $times; ++$index) {
                $window->recordException(new TypeMismatchException('$.value', 'int', 'string'));
            }
        };

        $context->withForcedErrorCollection(function (MappingContext $outer) use ($record): void {
            $record($outer, 10);

            self::assertSame(3, $outer->getErrorCount());

            $outer->withForcedErrorCollection(function (MappingContext $first) use ($record): void {
                $record($first, 10);

                self::assertSame(4, $first->getErrorCount(), 'The inner window gains the one record it needs.');

                $first->withForcedErrorCollection(function (MappingContext $second) use ($record): void {
                    $record($second, 10);

                    self::assertSame(5, $second->getErrorCount(), 'And so does the next, one per level.');
                });
            });
        });
    }

    #[Test]
    public function itOpensAWindowOnAContextWhoseCeilingCannotBeExceeded(): void
    {
        // A ceiling of the largest integer is a way to ask for no limit. Adding it to the records
        // already kept must not leave the integers, or the allowance of the window would be a float
        // and assigning it a native error.
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => PHP_INT_MAX]);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        $context->withForcedErrorCollection(function (MappingContext $trial): void {
            $trial->recordException(new TypeMismatchException('$.value', 'int', 'string'));

            self::assertSame(2, $trial->getErrorCount());
        });
    }

    #[Test]
    public function itRestoresTheOuterWindowWhenAnInnerOneCloses(): void
    {
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 1]);

        $context->withForcedErrorCollection(function (MappingContext $outer): void {
            $outer->recordException(new TypeMismatchException('$.value', 'int', 'string'));

            $outer->withForcedErrorCollection(function (MappingContext $inner): void {
                $inner->recordException(new TypeMismatchException('$.value', 'int', 'string'));
            });

            // Back in the outer window, still inside a trial: a refusal here is not a loss.
            $outer->recordException(new TypeMismatchException('$.value', 'int', 'string'));

            self::assertSame(0, $outer->getDroppedErrorCount());
        });
    }

    #[Test]
    public function itLetsATrialRecordPastTheCeiling(): void
    {
        // The observation that picks a union member reads how many records a candidate added, so a
        // ceiling that stopped appending inside the trial would make a failing candidate look
        // clean. What the trial wrote is the caller's to trim, and the ceiling applies again once
        // the window is closed.
        $context = new MappingContext(['root'], [MappingContext::OPTION_MAX_ERRORS => 2]);

        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));
        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        $context->withForcedErrorCollection(function (MappingContext $trial): void {
            $before = $trial->getErrorCount();

            $trial->recordException(new TypeMismatchException('$.value', 'int', 'string'));

            self::assertSame($before + 1, $trial->getErrorCount(), 'The trial still sees what it recorded.');
        });

        self::assertSame(3, $context->getErrorCount(), 'Nothing is cut while the caller may still trim.');
        self::assertSame(0, $context->getDroppedErrorCount());

        $context->trimErrors(2);
        $context->recordException(new TypeMismatchException('$.value', 'int', 'string'));

        self::assertSame(2, $context->getErrorCount());
        self::assertSame(1, $context->getDroppedErrorCount(), 'The ceiling applies again after the trial.');
    }

    #[Test]
    public function trimErrorsKeepsTheOldestRecordsInOrderAsAList(): void
    {
        $context = new MappingContext([]);

        foreach (['$.a', '$.b', '$.c', '$.d'] as $path) {
            $context->recordException(new TypeMismatchException($path, 'int', 'string'));
        }

        $context->trimErrors(2);

        $records = $context->getErrorRecords();

        self::assertTrue(array_is_list($records), 'The retained records stay a list after the cut.');
        self::assertSame(['$.a', '$.b'], array_map(static fn (MappingError $record): ?string => $record->getException()?->getPath(), $records));

        $context->trimErrors(5);

        self::assertCount(2, $context->getErrorRecords(), 'A count above the stored records cuts nothing.');

        $context->trimErrors(0);

        self::assertSame([], $context->getErrorRecords(), 'A count of zero takes every record back.');
    }
}
