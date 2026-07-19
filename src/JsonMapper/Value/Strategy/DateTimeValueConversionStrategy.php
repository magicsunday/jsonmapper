<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use DateInterval;
use DateTimeInterface;
use Exception;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function class_exists;
use function get_debug_type;
use function is_a;
use function is_int;
use function is_string;

/**
 * Converts ISO-8601 strings and timestamps into date/time value objects.
 */
final class DateTimeValueConversionStrategy implements ValueConversionStrategyInterface
{
    use ObjectTypeConversionGuardTrait;

    /**
     * Determines whether the requested type is a supported date or interval class.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the type represents a supported date/time object.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if (!$objectType instanceof ObjectType) {
            return false;
        }

        $className = $objectType->getClassName();

        if (is_a($className, DateInterval::class, true)) {
            return true;
        }

        // Any DateTimeInterface implementation, mutable included - convert() builds whatever class
        // the property asks for. class_exists() is what keeps the interface itself out: it is
        // false for an interface, and picking an implementation for a property typed
        // DateTimeInterface would be the mapper deciding mutability on the caller's behalf.
        return is_a($className, DateTimeInterface::class, true) && class_exists($className);
    }

    /**
     * Converts ISO-8601 strings and timestamps into the desired date/time object.
     *
     * The concrete class comes from the property, so a mutable DateTime stays mutable and an
     * immutable one stays immutable - the caller's choice is not second-guessed.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Instance of the configured date/time class.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        return $this->convertObjectValue(
            $type,
            $context,
            $value,
            static function (string $className, mixed $value) use ($context) {
                if (!is_string($value) && !is_int($value)) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                if (is_a($className, DateInterval::class, true)) {
                    if (!is_string($value)) {
                        throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                    }

                    try {
                        return new $className($value);
                    } catch (Exception) {
                        throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                    }
                }

                if (is_string($value)) {
                    $parsed = $className::createFromFormat($context->getDefaultDateFormat(), $value);

                    if ($parsed instanceof DateTimeInterface) {
                        return $parsed;
                    }
                }

                $formatted = is_int($value) ? '@' . $value : $value;

                try {
                    return new $className($formatted);
                } catch (Exception) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }
            }
        );
    }
}
