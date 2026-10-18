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
use MagicSunday\Test\Classes\MissingThenMismatchDto;
use MagicSunday\Test\Classes\MissingThenMismatchFallbackDto;
use MagicSunday\Test\Classes\MissingThenMismatchFallbackHolder;
use MagicSunday\Test\Classes\MissingThenMismatchHolder;
use MagicSunday\Test\Classes\NestedUnionHolder;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionElementCollectionHolder;
use MagicSunday\Test\Classes\UnionObjectHolder;
use MagicSunday\Test\Classes\UnionTwoRequiredPropertiesHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

use function range;

use const PHP_INT_MAX;

/**
 * A report is bounded by the schema and the configured ceiling, not by the size of the payload.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class ErrorCeilingTest extends TestCase
{
    #[Test]
    public function itKeepsAtMostTheConfiguredNumberOfRecordsAndSaysWhatItDropped(): void
    {
        $payload = ['id' => 1];

        foreach (range(1, 10) as $index) {
            $payload['unknown' . $index] = $index;
        }

        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxErrors(3))
            ->mapWithReport($payload, Simple::class);

        $report = $result->getReport();

        self::assertSame(3, $report->getErrorCount());
        self::assertSame(7, $report->getDroppedErrorCount());
        self::assertTrue($report->isTruncated());
    }

    #[Test]
    public function itReportsACompleteReportAsNotTruncated(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxErrors(3))
            ->mapWithReport(['id' => 1, 'unknown' => 1], Simple::class);

        self::assertSame(1, $result->getReport()->getErrorCount());
        self::assertFalse($result->getReport()->isTruncated());
    }

    #[Test]
    public function itStillRejectsAUnionMemberWhenTheCeilingIsAlreadyReached(): void
    {
        // The first record fills the ceiling. The union member that follows fails on a nested
        // value, and the failure of a member is what makes the union reject it. A ceiling that
        // dropped that record would make the member look clean and build an object from a payload
        // that does not fit.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxErrors(1))
            ->mapWithReport(
                ['surprise' => 1, 'value' => ['name' => ['nested' => true]]],
                UnionObjectHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionObjectHolder::class, $holder);
        self::assertSame('untouched', $holder->value);
    }

    #[Test]
    public function itMapsAUnionWhenTheCeilingIsTheLargestInteger(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxErrors(PHP_INT_MAX))
            ->mapWithReport(
                ['surprise' => 1, 'value' => ['name' => ['nested' => true]]],
                UnionObjectHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(UnionObjectHolder::class, $holder);
        self::assertSame('untouched', $holder->value);
        self::assertFalse($result->getReport()->isTruncated());
    }

    #[Test]
    public function itKeepsTheRecordsOfAnAcceptedMemberWithinTheCeiling(): void
    {
        // The matched member lacks two required properties and the ceiling keeps one. What the
        // trial window held beyond it is neither kept nor lost silently.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(['dto' => []], UnionTwoRequiredPropertiesHolder::class);

        $report = $result->getReport();

        self::assertSame(1, $report->getErrorCount());
        self::assertSame(1, $report->getDroppedErrorCount());
        self::assertTrue($report->isTruncated());
    }

    #[Test]
    public function itCountsWhatAFullWindowRefusedOfAnAcceptedMember(): void
    {
        // One record is already kept, which leaves the window room for one more. The matched member
        // lacks two properties, so the window stores the first and refuses the second, and the one
        // it stored does not fit the ceiling either. Three failures happened, one is kept.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(['surprise' => 1, 'dto' => []], UnionTwoRequiredPropertiesHolder::class);

        $report = $result->getReport();

        self::assertSame(1, $report->getErrorCount());
        self::assertSame(2, $report->getDroppedErrorCount());
    }

    #[Test]
    public function itRejectsAMemberWhoseTypeFailureTheFullWindowRefused(): void
    {
        // The nested object files its missing properties first and the ceiling keeps one record,
        // so the window refuses the type failure that follows. The member must still be rejected
        // on it, because a failure that was refused is a failure that happened.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(
                ['value' => ['nested' => [], 'count' => 'abc']],
                MissingThenMismatchHolder::class,
            );

        $holder = $result->getValue();

        self::assertInstanceOf(MissingThenMismatchHolder::class, $holder);
        self::assertNotInstanceOf(MissingThenMismatchDto::class, $holder->value);
    }

    #[Test]
    public function itDoesNotCountTheRefusalsOfARejectedMemberAsDropped(): void
    {
        // The first member files its missing properties and then fails on a type, and the full
        // window refuses part of that. Another member is the one used, and what the rejected one
        // met is not a loss of the report: the second member lacks one property, which is kept.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(
                ['value' => ['nested' => [], 'count' => 'abc']],
                MissingThenMismatchFallbackHolder::class,
            );

        $report = $result->getReport();

        self::assertInstanceOf(MissingThenMismatchFallbackHolder::class, $result->getValue());
        self::assertInstanceOf(MissingThenMismatchFallbackDto::class, $result->getValue()->value);
        self::assertSame(1, $report->getErrorCount());
        self::assertSame(0, $report->getDroppedErrorCount());
    }

    #[Test]
    public function itSettlesTheRecordsOfNestedUnionsOnlyOnce(): void
    {
        // The inner union closes inside the window of the outer one and leaves the cut to it.
        // Settling at both levels would count the same overflow twice.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(['value' => ['dto' => []]], NestedUnionHolder::class);

        $report = $result->getReport();

        self::assertSame(1, $report->getErrorCount());
        self::assertSame(1, $report->getDroppedErrorCount());
    }

    #[Test]
    public function itCountsOnlyTheRefusalsOfTheUnionBeingSettled(): void
    {
        // Every element is a union of its own and each lacks required properties, so the
        // refusals of the earlier elements are already counted when the next one is settled.
        // Counting them again would report more dropped failures than happened.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxErrors(1))
            ->mapWithReport(
                $this->getJsonAsObject('{"unionItems": [{}, {}, {}]}'),
                UnionElementCollectionHolder::class,
            );

        $report = $result->getReport();

        self::assertSame(1, $report->getErrorCount());
        self::assertSame(5, $report->getDroppedErrorCount());
    }
}
