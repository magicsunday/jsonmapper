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
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function get_debug_type;
use function is_a;
use function is_int;
use function is_string;

/**
 * Converts ISO-8601 strings into immutable date/time value objects.
 */
final class DateTimeValueConversionStrategy implements ValueConversionStrategyInterface
{
    use ObjectTypeConversionGuardTrait;

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if (!$objectType instanceof ObjectType) {
            return false;
        }

        $className = $objectType->getClassName();

        return is_a($className, DateTimeImmutable::class, true) || is_a($className, DateInterval::class, true);
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
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
