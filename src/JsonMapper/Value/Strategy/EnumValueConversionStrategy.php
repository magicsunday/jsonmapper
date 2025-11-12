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

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if ($objectType === null) {
            return false;
        }

        $className = $objectType->getClassName();

        if (!enum_exists($className)) {
            return false;
        }

        return is_a($className, BackedEnum::class, true);
    }

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
