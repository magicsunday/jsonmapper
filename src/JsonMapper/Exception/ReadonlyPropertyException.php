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
    /**
     * @param string $path      Path pointing to the JSON field that tried to set the readonly property.
     * @param string $property  Name of the property that cannot be written.
     * @param string $className Fully qualified class declaring the readonly property.
     */
    public function __construct(string $path, string $property, string $className)
    {
        parent::__construct(
            sprintf('Readonly property %s::%s cannot be written at %s.', $className, $property, $path),
            $path,
        );
    }
}
