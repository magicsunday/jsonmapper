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
 * Raised when a class is hydrated through its constructor but a required, non-nullable argument
 * has no value in the source payload and no default.
 */
final class MissingConstructorArgumentException extends MappingException
{
    /**
     * @param string $path      Path pointing to the object being constructed.
     * @param string $argument  Name of the required constructor argument that is missing.
     * @param string $className Fully qualified class being constructed.
     */
    public function __construct(string $path, string $argument, string $className)
    {
        parent::__construct(
            sprintf('Missing required constructor argument %s::$%s at %s.', $className, $argument, $path),
            $path,
        );
    }
}
