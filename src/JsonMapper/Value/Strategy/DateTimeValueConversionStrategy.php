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
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        if (!($type instanceof ObjectType)) {
            return false;
        }

        $className = $type->getClassName();

        if ($className === '') {
            return false;
        }

        return is_a($className, DateTimeImmutable::class, true) || is_a($className, DateInterval::class, true);
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        if (!($type instanceof ObjectType)) {
            return $value;
        }

        $className = $type->getClassName();

        if ($value === null) {
            if ($type->isNullable()) {
                return null;
            }

            throw new TypeMismatchException($context->getPath(), $className, 'null');
        }

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

        $formatted = is_int($value) ? '@' . $value : $value;

        try {
            return new $className($formatted);
        } catch (Exception) {
            throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
        }
    }
}
