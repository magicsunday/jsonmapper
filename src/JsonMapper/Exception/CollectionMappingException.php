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
 * Signals that a collection value could not be mapped.
 */
final class CollectionMappingException extends MappingException
{
    /**
     * @param string $path       Path to the collection that failed to map.
     * @param string $actualType Type reported for the value that was expected to be iterable.
     */
    public function __construct(string $path, private readonly string $actualType)
    {
        parent::__construct(
            sprintf('Expected iterable value at %s but received %s.', $path, $actualType),
            $path,
        );
    }

    /**
     * Returns the detected type of the value that could not be treated as iterable.
     *
     * Callers can surface the type to API consumers to explain why the mapper refused
     * to process the collection.
     *
     * @return string Type information describing the non-iterable value.
     */
    public function getActualType(): string
    {
        return $this->actualType;
    }
}
