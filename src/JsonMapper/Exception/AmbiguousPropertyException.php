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
 * Signals that a payload carries several spellings of the same target property.
 *
 * The property name converter folds case and separators, so `id`, `ID` and `Id` can name one
 * property. Applying them in payload order would let a spelling nobody inspected overwrite one that
 * was, so the later spelling is reported instead of applied.
 */
final class AmbiguousPropertyException extends MappingException
{
    /**
     * Creates the exception for one rejected spelling of a property.
     *
     * @param string       $path         Path to the property the spellings collide on.
     * @param string       $propertyName Name of the declared property that several payload keys name.
     * @param class-string $className    Fully qualified name of the object that declares the property.
     * @param string       $appliedKey   The payload key that came first for the property and was selected for it.
     * @param string       $rejectedKey  The payload key that named the same property later and was not applied.
     */
    public function __construct(
        string $path,
        private readonly string $propertyName,
        /** @var class-string */
        private readonly string $className,
        private readonly string $appliedKey,
        private readonly string $rejectedKey,
    ) {
        parent::__construct(
            sprintf('Ambiguous property %s on %s.', $path, $className),
            $path,
        );
    }

    /**
     * Returns the declared property that several payload keys name.
     *
     * @return string Name of the property on the PHP target.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * Provides the class that declares the property.
     *
     * Internal information: useful for logs and for deciding what to say, not for saying it. A
     * response body naming the DTO discloses how the application is laid out.
     *
     * @return class-string Fully qualified class name of the target.
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Returns the payload key selected for the property and the one that was rejected, in that order.
     *
     * They came from the payload, so escape them for whatever sink they reach and bound their length.
     *
     * @return list<string> The selected key first, then the rejected key.
     */
    public function getPayloadKeys(): array
    {
        return [$this->appliedKey, $this->rejectedKey];
    }
}
