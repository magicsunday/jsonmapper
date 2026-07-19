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
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use Symfony\Component\TypeInfo\Type;

/**
 * Decides what a null payload means for the declared type.
 */
final class NullValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Determines whether the incoming value represents a null assignment.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the value is exactly null.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        // Every null, not only the ones the type accepts. Declining a null for a non-nullable type
        // does not reject it - it hands it to the next strategy, and what happens then depends on
        // the target: a builtin refuses it, but an object type instantiates a class that needs no
        // constructor arguments, turning the null into a fully-formed object with default values
        // and no record. This strategy owns the question, so it has to answer it for every null.
        return $value === null;
    }

    /**
     * Preserves the absence of a value, or rejects it when the type forbids null.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return null Null when the type accepts it.
     *
     * @throws TypeMismatchException When the declared type forbids null.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): null
    {
        if (!$type->isNullable()) {
            // The throw is the recording path - the caller records it once. Rejecting here rather
            // than declining in supports() keeps the decision with the strategy that owns nulls,
            // instead of leaving it to whichever strategy happens to run next.
            throw new TypeMismatchException(
                $context->getPath(),
                (string) $type,
                'null',
            );
        }

        return null;
    }
}
