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

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
