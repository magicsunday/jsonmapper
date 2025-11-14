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
 * Signals that a required property is missing in the JSON payload.
 */
final class MissingPropertyException extends MappingException
{
    /**
     * @param string       $path         Path indicating where the missing property should have been present.
     * @param string       $propertyName Name of the required property defined on the PHP target.
     * @param class-string $className    Fully qualified name of the DTO or object declaring the property.
     */
    public function __construct(
        string $path,
        private readonly string $propertyName,
        /** @var class-string */
        private readonly string $className,
    ) {
        parent::__construct(
            sprintf('Missing property %s on %s.', $path, $className),
            $path,
        );
    }

    /**
     * Returns the required property name that could not be resolved from the JSON input.
     *
     * Use this to inform API clients about the field they need to provide.
     *
     * @return string Name of the missing property.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * Provides the class in which the missing property is declared.
     *
     * Consumers may use the information to scope the validation error when working with nested DTOs.
     *
     * @return class-string Fully qualified class name declaring the missing property.
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
