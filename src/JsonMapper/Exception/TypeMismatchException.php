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
    /**
     * @param string $path         Path to the offending value inside the JSON payload.
     * @param string $expectedType Type declared on the PHP target (FQCN or scalar type name).
     * @param string $actualType   Detected type of the JSON value that failed conversion.
     */
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

    /**
     * Returns the PHP type the mapper attempted to hydrate.
     *
     * Callers may use the information to build error messages that mirror the
     * DTO or property contract.
     *
     * @return string Declared PHP type expected for the JSON value.
     */
    public function getExpectedType(): string
    {
        return $this->expectedType;
    }

    /**
     * Returns the actual type the mapper observed in the JSON payload.
     *
     * Consumers can combine the value with {@see getExpectedType()} to explain
     * why the assignment failed.
     *
     * @return string Type reported for the source value.
     */
    public function getActualType(): string
    {
        return $this->actualType;
    }
}
