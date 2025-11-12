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
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\JsonMapper\Report\MappingReport;
use MagicSunday\JsonMapper\Report\MappingResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MappingResultTest extends TestCase
{
    #[Test]
    public function itExposesValueAndReport(): void
    {
        $report = new MappingReport([
            new MappingError('$.foo', 'Unknown', new UnknownPropertyException('$.foo', 'foo', self::class)),
        ]);

        $result = new MappingResult(['foo' => 'bar'], $report);

        self::assertSame(['foo' => 'bar'], $result->getValue());
        self::assertSame($report, $result->getReport());
    }
}
