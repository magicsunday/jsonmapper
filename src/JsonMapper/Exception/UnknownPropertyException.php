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
 * Signals that the JSON input references an undefined target property.
 */
final class UnknownPropertyException extends MappingException
{
    /**
     * @param string       $path         Path to the JSON value that references the unknown property.
     * @param string       $propertyName Name of the property that does not exist on the PHP target.
     * @param class-string $className    Fully qualified name of the object that lacks the property.
     */
    public function __construct(
        string $path,
        private readonly string $propertyName,
        /** @var class-string */
        private readonly string $className,
    ) {
        parent::__construct(
            sprintf('Unknown property %s on %s.', $path, $className),
            $path,
        );
    }

    /**
     * Returns the unknown property name as provided by the JSON payload.
     *
     * Callers can expose the value in validation errors so clients can remove unsupported fields.
     * It came from the payload, so escape it for whatever sink it reaches and bound its length.
     *
     * @return string Property name that could not be mapped.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * Provides the class for which the property is unknown.
     *
     * Internal information: useful for logs and for deciding what to say, not for saying it. A
     * response body naming the DTO discloses how the application is laid out.
     *
     * @return class-string Fully qualified class name without the referenced property.
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
