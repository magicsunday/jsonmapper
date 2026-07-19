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
 *   - the builtin strategy's compatibility guard   -> itKeepsACoercibleElementMismatch...
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
        // Two sites CAN record the same element failure: the factory's element loop, and the
        // property loop above it once the exception escapes. Collecting is what prevents the
        // second one - the element loop records inside the segment and does not rethrow, so
        // nothing reaches the property loop. Rethrowing here is what produced the duplicate, and
        // discarded the rejected element's valid siblings along with it.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => [1, ['nested' => 'value'], 3]],
            IntListHolder::class,
        );

        self::assertSame(1, $result->getReport()->getErrorCount(), 'One bad element, one record.');

        $error = $result->getReport()->getErrors()[0];

        // The record's path is read off the context, and the catch that records used to sit
        // OUTSIDE the element's path segment, so an element failure was filed under the collection
        // itself. The exception carried the right path all along, which is what made the
        // discrepancy invisible: whoever caught it saw $.values.1 while the report said $.values.
        self::assertSame('$.values.1', $error->getPath(), 'The record names the element, not its collection.');
        self::assertSame($error->getException()?->getPath(), $error->getPath(), 'Record and exception agree.');
    }

    #[Test]
    public function itKeepsACoercibleElementMismatchAndRecordsItOnce(): void
    {
        // The builtin strategy's compatibility guard, which no other test here reaches: the element
        // tests use a COMPOSITE, and a composite targeting a scalar is rejected earlier, by a
        // different site. A wrong-typed SCALAR takes the other route - it is coerced and recorded
        // rather than dropped, which is the documented lenient contract.
        //
        // Both assertions are needed to pin the guard's abort decision. Were it still asking the
        // configuration, it would rethrow under strict: the element loop above would drop the
        // element AND record the same failure a second time, giving [1, 3] and two records.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['values' => [1, 'abc', 3]],
            IntListHolder::class,
        );

        $holder = $result->getValue();

        self::assertInstanceOf(IntListHolder::class, $holder);
        self::assertSame(
            [1, 0, 3],
            $holder->values,
            'A coercible mismatch is recorded and kept, not dropped.',
        );
        self::assertSame(
            1,
            $result->getReport()->getErrorCount(),
            'Recorded once - the guard must not rethrow into the loop that records again.',
        );
    }

    #[Test]
    public function itCollectsEveryRejectedCollectionElement(): void
    {
        // Two bad elements, not one: a loop that recorded only the first would satisfy both
        // single-offender tests above. The paths are pinned in order, so a record filed under the
        // collection rather than the element fails here as well.
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
        // The site that turned a recorded failure into a native crash. mapIterable() used to answer
        // a non-collection payload with null - the same sentinel it still uses for "no collection
        // was asked for" - and threw before returning it whenever strict mode was on. Once
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
        // The same sentinel reaching the other consumer: the top-level collection lane handed the
        // value straight to the wrapper's constructor, and ArrayObject::__construct() rejected null
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

        // The VALUE is asserted too, and that is what makes this test discriminate. Two separate
        // changes block the old TypeError - mapIterable() returning [] for a recorded failure, and
        // wrapCollection()'s null guard - so asserting only the report lets either one cover for
        // the other. Revert the [] and the guard turns the run into a null from wrapCollection(),
        // which mapCollection() reports as "not handled", so map() falls through and hands back the
        // RAW PAYLOAD - the string 'not-a-collection' - with the record still in place and the test
        // still green. Not merely an absence: unmapped input returned as if it had been mapped.
        self::assertInstanceOf(
            Collection::class,
            $result->getValue(),
            'A recorded failure still yields a collection, not an absence.',
        );
        self::assertCount(0, $result->getValue(), 'Empty, because no element could be mapped.');
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

        // And it is REPORTED. The same null against a non-nullable collection property is recorded
        // by convertValue()'s guard, so leaving the root lane silent would recreate the very split
        // this entry point exists to remove - a caller checking hasErrors() would see a clean run
        // and then dereference null. The value stays null either way, so the option remains
        // observable; only the report changes.
        self::assertTrue($absent->getReport()->hasErrors(), 'The absent collection is reported.');
        self::assertInstanceOf(
            TypeMismatchException::class,
            $absent->getReport()->getErrors()[0]->getException(),
        );

        // With the option on, the same payload becomes a real, empty collection - which is what
        // makes the assertion above a statement about the option rather than about null handling.
        $empty = $this->getJsonMapper(
            config: JsonMapperConfiguration::strict()->withTreatNullAsEmptyCollection(true),
        )->mapWithReport(null, null, Collection::class);

        self::assertInstanceOf(Collection::class, $empty->getValue());
        self::assertCount(0, $empty->getValue());
        self::assertFalse($empty->getReport()->hasErrors(), 'With the option on, null is not a failure.');
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
