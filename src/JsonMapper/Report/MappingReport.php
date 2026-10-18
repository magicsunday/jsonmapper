<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Report;

use MagicSunday\JsonMapper\Context\MappingError;

use function count;

/**
 * Represents the result of collecting mapping errors.
 */
final readonly class MappingReport
{
    /**
     * @param list<MappingError> $errors        Failures that were kept
     * @param int                $droppedErrors Failures that were not kept because the ceiling had been reached
     */
    public function __construct(
        private array $errors,
        private int $droppedErrors = 0,
    ) {
    }

    /**
     * @return list<MappingError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Determines whether the report contains any mapping errors.
     *
     * @return bool True when at least one {@see MappingError} has been collected, false otherwise.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Counts the number of mapping errors stored in the report.
     *
     * @return int Total amount of collected {@see MappingError} instances.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Returns how many failures were left out of the report.
     *
     * @return int Number of failures beyond the configured ceiling, zero for a complete report
     */
    public function getDroppedErrorCount(): int
    {
        return $this->droppedErrors;
    }

    /**
     * Determines whether failures were left out of the report.
     *
     * @return bool True when the ceiling was reached and the report is incomplete
     */
    public function isTruncated(): bool
    {
        return $this->droppedErrors > 0;
    }
}
