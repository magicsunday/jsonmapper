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
 * Contract for value conversion strategies.
 */
interface ValueConversionStrategyInterface
{
    /**
     * Determines whether the strategy can convert the provided value for the requested type.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the strategy should perform the conversion.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool;

    /**
     * Converts the value into a representation compatible with the requested type.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Result of the conversion when the strategy supports the value.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed;
}
