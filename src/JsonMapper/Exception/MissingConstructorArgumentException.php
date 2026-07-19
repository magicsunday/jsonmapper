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
     * @param string $path         Path pointing to the object being constructed.
     * @param string $argumentName Name of the required constructor argument that is missing.
     * @param string $className    Fully qualified class being constructed.
     */
    public function __construct(
        string $path,
        private readonly string $argumentName,
        private readonly string $className,
    ) {
        parent::__construct(
            sprintf('Missing required constructor argument %s::$%s at %s.', $className, $argumentName, $path),
            $path,
        );
    }

    /**
     * Returns the constructor argument the payload did not supply.
     *
     * Exposed separately from the message so a caller can build client-facing text without parsing
     * it - the message embeds the internal class name and must not be forwarded verbatim.
     *
     * @return string Name of the missing argument.
     */
    public function getArgumentName(): string
    {
        return $this->argumentName;
    }

    /**
     * Returns the class that could not be constructed.
     *
     * Internal information: useful for logs and for deciding what to say, not for saying it. A
     * response body naming the DTO discloses how the application is laid out.
     *
     * @return string Fully qualified class name.
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
