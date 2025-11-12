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
final class MappingResult
{
    public function __construct(
        private readonly mixed $value,
        private readonly MappingReport $report,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getReport(): MappingReport
    {
        return $this->report;
    }
}
