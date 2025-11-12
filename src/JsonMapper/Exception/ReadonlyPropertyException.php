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
 * Raised when attempting to assign to a readonly property.
 */
final class ReadonlyPropertyException extends MappingException
{
    public function __construct(string $path, string $property, string $className)
    {
        parent::__construct(
            sprintf('Readonly property %s::%s cannot be written at %s.', $className, $property, $path),
            $path,
        );
    }
}
