<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use Symfony\Component\TypeInfo\Type;

/**
 * Handles conversion of registered custom types.
 */
final readonly class CustomTypeValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Creates the strategy backed by the custom type registry.
     *
     * @param CustomTypeRegistry $registry Registry containing the custom handlers.
     */
    public function __construct(
        private CustomTypeRegistry $registry,
    ) {
    }

    /**
     * Determines whether the registry can handle the provided type.
     *
     * @param mixed $value Raw value coming from the input payload.
     * @param Type $type Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the registry has a matching custom handler.
     */
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $this->registry->supports($type, $value);
    }

    /**
     * Converts the value using the registered handler.
     *
     * @param mixed $value Raw value coming from the input payload.
     * @param Type $type Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Value produced by the registered custom handler.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        return $this->registry->convert($type, $value, $context);
    }
}
