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
     * @param list<MappingError> $errors
     */
    public function __construct(private array $errors)
    {
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
}
