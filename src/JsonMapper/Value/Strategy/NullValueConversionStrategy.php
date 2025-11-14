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
use Symfony\Component\TypeInfo\Type;

/**
 * Returns null values as-is.
 */
final class NullValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Determines whether the incoming value represents a null assignment.
     *
     * @param mixed $value Raw value coming from the input payload.
     * @param Type $type Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the value is exactly null.
     */
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $value === null;
    }

    /**
     * Returns null to preserve the absence of a value.
     *
     * @param mixed $value Raw value coming from the input payload.
     * @param Type $type Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return null Always returns null for supported values.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): null
    {
        return null;
    }
}
