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
    public function __construct(string $path, private readonly string $actualType)
    {
        parent::__construct(
            sprintf('Expected iterable value at %s but received %s.', $path, $actualType),
            $path,
        );
    }

    public function getActualType(): string
    {
        return $this->actualType;
    }
}
