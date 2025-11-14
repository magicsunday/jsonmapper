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
 * Fallback strategy returning the value unchanged.
 */
final class PassthroughValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Always supports conversion and acts as the terminal strategy.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool Always TRUE so the strategy can act as the final fallback.
     */
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return true;
    }

    /**
     * Returns the original value without modification.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Unmodified value passed through from the input.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        return $value;
    }
}
