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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

use function assert;
use function filter_var;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function settype;
use function strtolower;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

/**
 * Converts scalar values to the requested builtin type.
 */
final class BuiltinValueConversionStrategy implements ValueConversionStrategyInterface
{
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $type instanceof BuiltinType;
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        assert($type instanceof BuiltinType);

        $normalized = $this->normalizeValue($value, $type);

        $this->guardCompatibility($normalized, $type, $context);

        if ($normalized === null) {
            return null;
        }

        $converted = $normalized;
        settype($converted, $type->getTypeIdentifier()->value);

        return $converted;
    }

    private function normalizeValue(mixed $value, BuiltinType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        $identifier = $type->getTypeIdentifier()->value;

        if ($identifier === 'bool') {
            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if ($normalized === '1' || $normalized === 'true') {
                    return true;
                }

                if ($normalized === '0' || $normalized === 'false') {
                    return false;
                }
            }

            if (is_int($value)) {
                if ($value === 0) {
                    return false;
                }

                if ($value === 1) {
                    return true;
                }
            }
        }

        if ($identifier === 'int' && is_string($value)) {
            $filtered = filter_var(trim($value), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            if ($filtered !== null) {
                return $filtered;
            }
        }

        if ($identifier === 'float' && is_string($value)) {
            $filtered = filter_var(trim($value), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

            if ($filtered !== null) {
                return $filtered;
            }
        }

        if ($identifier === 'int' && is_float($value)) {
            return (int) $value;
        }

        if ($identifier === 'float' && is_int($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function guardCompatibility(mixed $value, BuiltinType $type, MappingContext $context): void
    {
        $identifier = $type->getTypeIdentifier();

        if ($value === null) {
            if ($this->allowsNull($type)) {
                return;
            }

            $exception = new TypeMismatchException($context->getPath(), $identifier->value, 'null');
            $context->recordException($exception);

            if ($context->isStrictMode()) {
                throw $exception;
            }

            return;
        }

        if ($this->isCompatibleValue($value, $identifier)) {
            return;
        }

        $exception = new TypeMismatchException($context->getPath(), $identifier->value, get_debug_type($value));
        $context->recordException($exception);

        if ($context->isStrictMode()) {
            throw $exception;
        }
    }

    private function allowsNull(BuiltinType $type): bool
    {
        return $type->isNullable();
    }

    private function isCompatibleValue(mixed $value, TypeIdentifier $identifier): bool
    {
        return match ($identifier->value) {
            'int'      => is_int($value),
            'float'    => is_float($value) || is_int($value),
            'bool'     => is_bool($value),
            'string'   => is_string($value),
            'array'    => is_array($value),
            'object'   => is_object($value),
            'callable' => is_callable($value),
            'iterable' => is_iterable($value),
            'null'     => $value === null,
            default    => true,
        };
    }
}
