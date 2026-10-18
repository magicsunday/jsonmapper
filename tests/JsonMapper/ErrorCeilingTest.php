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
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionObjectHolder;
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
}
