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
 * Routing the root handler through the context left four further sites asking the configuration
 * directly: the two in the collection factory, the guard in the builtin strategy, and the union
 * fallback. Each of them still threw under strict mode, so the very first one reached turned the
 * documented "collects everything" back into "aborts on the first failure" - and did so silently,
 * because the outer catch recorded the escaped exception and made the report look plausible.
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
    public function itCollectsAUnionThatMatchesNoCandidate(): void
    {
        // The union fallback is the last of the four sites: with no candidate accepting the value
        // it recorded a mismatch and then threw, aborting a run that asked for a report.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['value' => ['unmappable' => true]],
            UnionScalarHolder::class,
        );

        self::assertTrue($result->getReport()->hasErrors(), 'The unmatched union is reported.');
    }

    #[Test]
    public function itStillAbortsOnTheFirstFailureInStrictMap(): void
    {
        // The counterpart: map() must keep raising, or the fix would have turned strict mode into
        // a no-op rather than moving the decision to the entry point.
        $this->expectExceptionMessageMatches('/' . preg_quote('$.values.1', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            ['values' => [1, ['nested' => 'value'], 3]],
            IntListHolder::class,
        );
    }
}
