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
     * @param string $path         Path pointing to the JSON field that tried to set the readonly property.
     * @param string $propertyName Name of the property that cannot be written.
     * @param string $className    Fully qualified class declaring the readonly property.
     */
    public function __construct(
        string $path,
        private readonly string $propertyName,
        private readonly string $className,
    ) {
        parent::__construct(
            sprintf('Readonly property %s::%s cannot be written at %s.', $className, $propertyName, $path),
            $path,
        );
    }

    /**
     * Returns the property that could not be written.
     *
     * Exposed separately from the message so a caller can build client-facing text without parsing
     * it - the message embeds the internal class name and must not be forwarded verbatim. The name
     * came from the payload, so escape it for whatever sink it reaches and bound its length.
     *
     * @return string Name of the readonly property.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * Returns the class declaring the readonly property.
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
