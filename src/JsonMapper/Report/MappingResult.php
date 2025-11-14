<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Report;

/**
 * Represents the outcome of a mapping operation and its report.
 */
final readonly class MappingResult
{
    /**
     * @param mixed         $value  The mapped value returned by the mapper.
     * @param MappingReport $report Report containing diagnostics for the mapping operation.
     */
    public function __construct(
        private mixed $value,
        private MappingReport $report,
    ) {
    }

    /**
     * Returns the mapped value that was produced by the mapper.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Provides the report with the diagnostics gathered during mapping.
     */
    public function getReport(): MappingReport
    {
        return $this->report;
    }
}
