<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Report;

use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Report\MappingReport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MappingReportTest extends TestCase
{
    #[Test]
    public function itStoresErrors(): void
    {
        $exception = new MissingPropertyException('$.name', 'name', self::class);
        $errors    = [new MappingError('$.name', 'Missing', $exception)];

        $report = new MappingReport($errors);

        self::assertTrue($report->hasErrors());
        self::assertSame(1, $report->getErrorCount());
        self::assertSame($errors, $report->getErrors());
    }

    #[Test]
    public function itHandlesEmptyReports(): void
    {
        $report = new MappingReport([]);

        self::assertFalse($report->hasErrors());
        self::assertSame(0, $report->getErrorCount());
    }
}
