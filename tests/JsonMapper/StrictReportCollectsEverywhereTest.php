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
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\IntListHolder;
use MagicSunday\Test\Classes\UnionScalarHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function preg_quote;

/**
 * mapWithReport() collects instead of aborting - but that decision has to be taken at EVERY site
 * that can abort a run, not only at the one the entry point happens to route through.
 *
 * Routing the root handler through the context left five further sites asking the configuration
 * directly. Each of them still threw under strict mode, so the very first one reached turned the
 * documented "collects everything" back into "aborts on the first failure" - and did so silently,
 * because the outer catch recorded the escaped exception and made the report look plausible.
 *
 * The sites, and what covers each here:
 *   - the collection factory's non-iterable guard  -> itReports...ForACollectionProperty/Root
 *   - the collection factory's element loop        -> itKeepsValidSiblings..., itCollectsEvery...
 *   - the builtin strategy's compatibility guard   -> reached through the element tests
 *   - the builtin strategy's null branch           -> NOT covered: the null strategy is registered
 *     first and answers every null, so nothing reaches it through the converter chain
 *   - the union fallback in convertUnionValue()    -> NOT covered: unreachable, see the test below
 *
 * The last two are named rather than quietly omitted: a list that claims complete coverage is
 * worse than one that says where it stops.
 *
 * @internal
 */
final class StrictReportCollectsEverywhereTest extends TestCase
{
    #[Test]
    public function itKeepsValidSiblingsOfARejectedCollectionElement(): void
    {
        // A composite reaching an int element is REJECTED, not coerced - a wrong-typed scalar
        // would be cast and recorded instead, and would stay in the collection. The invalid entry
        // sits in the MIDDLE deliberately: aborting on the first failure and dropping the offender
        // are indistinguishable when the bad element comes last.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => [1, ['nested' => 'value'], 3]],
            IntListHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IntListHolder::class, $holder);
        self::assertSame([1, 3], $holder->values, 'The siblings of the rejected element survive.');
    }

    #[Test]
    public function itRecordsARejectedCollectionElementExactlyOnce(): void
    {
        // Two sites record the same element failure - the factory's element loop and, once the
        // exception escapes, the property loop above it. Aborting made the duplicate invisible;
        // collecting ships it to the caller.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => [1, ['nested' => 'value'], 3]],
            IntListHolder::class,
        );

        self::assertSame(1, $result->getReport()->getErrorCount(), 'One bad element, one record.');

        $error = $result->getReport()->getErrors()[0];

        // The record's path is read off the context, and the catch that records sits OUTSIDE the
        // element's path segment - so an element failure used to be filed under the collection
        // itself. The exception carried the right path all along, which is what made the
        // discrepancy invisible: whoever caught it saw $.values.1 while the report said $.values.
        self::assertSame('$.values.1', $error->getPath(), 'The record names the element, not its collection.');
        self::assertSame($error->getException()?->getPath(), $error->getPath(), 'Record and exception agree.');
    }

    #[Test]
    public function itCollectsEveryRejectedCollectionElement(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => [['a' => 1], 2, ['b' => 2]]],
            IntListHolder::class,
        );

        $paths = array_map(
            static fn (MappingError $error): string => $error->getPath(),
            $result->getReport()->getErrors(),
        );

        self::assertSame(['$.values.0', '$.values.2'], $paths, 'Both bad elements are reported by index.');
    }

    #[Test]
    public function itReportsANonIterablePayloadForACollectionPropertyWithoutCrashing(): void
    {
        // The site that turns a recorded failure into a native crash. mapIterable() answers a
        // non-collection payload with null - the same sentinel it uses for "no collection was
        // asked for" - and used to throw before returning it whenever strict mode was on. Once
        // aborting moved to the entry point, strict mapWithReport() reached the return instead,
        // and the null travelled on to the property accessor, which rejects it with a Symfony
        // InvalidTypeException. A run that promised a report crashed with a foreign exception.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => 'not-a-collection'],
            IntListHolder::class,
        );

        self::assertInstanceOf(IntListHolder::class, $result->getValue());
        self::assertSame([], $result->getValue()->values, 'The property holds an empty collection.');
        self::assertInstanceOf(
            CollectionMappingException::class,
            $result->getReport()->getErrors()[0]->getException(),
            'The failure is reported as a mapping error, not raised as a native one.',
        );
    }

    #[Test]
    public function itReportsANonIterablePayloadForACollectionRootWithoutCrashing(): void
    {
        // The same sentinel reaching the other consumer: the top-level collection lane hands the
        // value straight to the wrapper's constructor, and ArrayObject::__construct() rejects null
        // with a TypeError - a native error escaping a run that asked for a report, which the
        // error-handling contract rules out explicitly.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            'not-a-collection',
            null,
            Collection::class,
        );

        self::assertTrue($result->getReport()->hasErrors(), 'The failure is reported.');
        self::assertInstanceOf(
            CollectionMappingException::class,
            $result->getReport()->getErrors()[0]->getException(),
        );
    }

    #[Test]
    public function itDistinguishesAnAbsentCollectionFromAnEmptyOne(): void
    {
        // The other consumer of the same null: a null payload for a collection root. Wrapping it
        // used to raise a TypeError from ArrayObject's constructor. It now yields null, NOT an
        // empty collection - inventing one would override treatNullAsEmptyCollection, which the
        // caller has explicitly left off, and would make the two settings indistinguishable.
        $absent = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            null,
            null,
            Collection::class,
        );

        self::assertNull($absent->getValue(), 'A null payload produces no collection at all.');

        // With the option on, the same payload becomes a real, empty collection - which is what
        // makes the assertion above a statement about the option rather than about null handling.
        $empty = $this->getJsonMapper(
            config: JsonMapperConfiguration::strict()->withTreatNullAsEmptyCollection(true),
        )->mapWithReport(null, null, Collection::class);

        self::assertInstanceOf(Collection::class, $empty->getValue());
        self::assertCount(0, $empty->getValue());
    }

    #[Test]
    public function itCollectsAUnionThatMatchesNoCandidate(): void
    {
        // Deliberately NOT a test of the union fallback in convertUnionValue(): that block needs a
        // union whose members are all null types, which Symfony's TypeInfo does not produce, so it
        // cannot be reached from here. What a value matching no candidate really does is rethrow
        // the last rejected candidate's failure, which the property loop then records - and the
        // message is what proves which of the two happened. The fallback would say "int|string";
        // this says "string", the last candidate tried.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['value' => ['unmappable' => true]],
            UnionScalarHolder::class,
        );

        self::assertSame(
            'Type mismatch at $.value: expected string, got array.',
            $result->getReport()->getErrors()[0]->getMessage(),
            'The last rejected candidate is what gets reported.',
        );
        self::assertSame(1, $result->getReport()->getErrorCount(), 'Rejected trials are trimmed away.');
    }

    #[Test]
    public function itStillAbortsOnTheFirstFailureInStrictMap(): void
    {
        // The counterpart: map() must keep raising, or the fix would have turned strict mode into
        // a no-op rather than moving the decision to the entry point.
        //
        // TWO bad elements, and the path pinned to the FIRST of them: with a single bad element an
        // implementation that collected everything and threw at the end would pass identically.
        // The type is pinned too - an implementation that crashed with a native error carrying the
        // same path would otherwise satisfy a message-only expectation.
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('$.values.0', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['values' => [['a' => 1], 2, ['b' => 2]]],
            IntListHolder::class,
        );
    }
}
