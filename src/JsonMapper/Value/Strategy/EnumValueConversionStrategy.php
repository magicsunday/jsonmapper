<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use BackedEnum;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;
use ValueError;

use function enum_exists;
use function get_debug_type;
use function is_a;
use function is_int;
use function is_string;

/**
 * Converts scalar JSON values into backed enums.
 */
final class EnumValueConversionStrategy implements ValueConversionStrategyInterface
{
    use ObjectTypeConversionGuardTrait;

    /**
     * Determines whether the provided type is a backed enum.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type resolves to a backed enum.
     */
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if (!$objectType instanceof ObjectType) {
            return false;
        }

        $className = $objectType->getClassName();

        if (!enum_exists($className)) {
            return false;
        }

        return is_a($className, BackedEnum::class, true);
    }

    /**
     * Converts the JSON scalar into the matching enum case.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Backed enum instance returned by the case factory method.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        return $this->convertObjectValue(
            $type,
            $context,
            $value,
            static function (string $className, mixed $value) use ($context) {
                if (!is_int($value) && !is_string($value)) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                try {
                    /** @var BackedEnum $enum */
                    $enum = $className::from($value);
                } catch (ValueError) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                return $enum;
            }
        );
    }
}
