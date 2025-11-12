<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Exception;

use function sprintf;

/**
 * Signals that a JSON value could not be converted to the expected type.
 */
final class TypeMismatchException extends MappingException
{
    public function __construct(
        string $path,
        private readonly string $expectedType,
        private readonly string $actualType,
    ) {
        parent::__construct(
            sprintf('Type mismatch at %s: expected %s, got %s.', $path, $expectedType, $actualType),
            $path,
        );
    }

    public function getExpectedType(): string
    {
        return $this->expectedType;
    }

    public function getActualType(): string
    {
        return $this->actualType;
    }
}
