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

/**
 * Represents the result of collecting mapping errors.
 */
final class MappingReport
{
    /**
     * @param list<MappingError> $errors
     */
    public function __construct(private readonly array $errors)
    {
    }

    /**
     * @return list<MappingError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }
}
