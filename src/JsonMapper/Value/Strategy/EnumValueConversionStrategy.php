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
use TypeError;
use UnitEnum;
use ValueError;

use function enum_exists;
use function get_debug_type;
use function is_a;
use function is_int;
use function is_string;

/**
 * Converts scalar JSON values into enum cases. A backed enum is addressed by case value, a pure
 * enum by case name.
 */
final class EnumValueConversionStrategy implements ValueConversionStrategyInterface
{
    use ObjectTypeConversionGuardTrait;

    /**
     * Determines whether the provided type is an enum.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type resolves to an enum, backed or pure.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if (!$objectType instanceof ObjectType) {
            return false;
        }

        return enum_exists($objectType->getClassName());
    }

    /**
     * Converts the JSON scalar into the matching enum case.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Backed enum instance returned by the case factory method.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        return $this->convertObjectValue(
            $type,
            $context,
            $value,
            static function (string $className, mixed $value) use ($context) {
                // A pure enum is a UnitEnum but not a BackedEnum, so it has no case factory to
                // call - its cases are addressed by name instead.
                if (
                    !is_a($className, BackedEnum::class, true)
                    && is_a($className, UnitEnum::class, true)
                ) {
                    return self::resolveUnitEnumCase($className, $value, $context);
                }

                if (!is_int($value) && !is_string($value)) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                try {
                    /** @var BackedEnum $enum */
                    $enum = $className::from($value);
                } catch (TypeError|ValueError) {
                    // ValueError means the value is not one of the cases. TypeError means it does
                    // not even match the backing type - under strict_types the case factory
                    // rejects a string for an int-backed enum before any lookup happens, which
                    // JSON payloads from loosely typed APIs routinely trigger. Both are the same
                    // thing to a caller: this value does not name a case.
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                return $enum;
            }
        );
    }

    /**
     * Resolves the enum case a pure enum payload addresses. Without a backing type the cases carry
     * no scalar value, so the case name is the only thing a payload can name. The comparison is
     * exact: a case name is an identifier, and a loose match would accept a payload the enum does
     * not define.
     *
     * @param class-string<UnitEnum> $className Fully qualified name of the pure enum to resolve against.
     * @param mixed                  $value     Raw value coming from the input payload.
     * @param MappingContext         $context   Mapping context providing the current path.
     *
     * @return UnitEnum Enum case whose name equals the provided value.
     *
     * @throws TypeMismatchException When the value is not a string or names no case.
     */
    private static function resolveUnitEnumCase(
        string $className,
        mixed $value,
        MappingContext $context,
    ): UnitEnum {
        if (is_string($value)) {
            foreach ($className::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }
        }

        throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
    }
}
