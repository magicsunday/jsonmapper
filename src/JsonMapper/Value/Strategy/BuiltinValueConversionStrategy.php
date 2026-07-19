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
use function floor;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_bool;
use function is_callable;
use function is_finite;
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
use const PHP_INT_MIN;

/**
 * Converts scalar values to the requested builtin type.
 */
final class BuiltinValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Identifiers whose values are scalar. A composite value reaching one of these is rejected
     * rather than cast: settype() does not refuse it, it produces nonsense. Only the string cast
     * announces itself, writing the literal 'Array' and emitting an "Array to string conversion"
     * warning; the bool, int and float casts are silent and well-defined, and would hand the
     * caller a plausible-looking true/1/1.0 derived from nothing. Rejecting those three is a
     * deliberate product decision, not a technical necessity. The remaining castable identifiers -
     * array, object, null - convert a composite perfectly well, which is why the check has to look
     * at the target and not only at the value.
     *
     * @var list<TypeIdentifier>
     */
    private const array SCALAR_IDENTIFIERS = [
        TypeIdentifier::BOOL,
        TypeIdentifier::FLOAT,
        TypeIdentifier::INT,
        TypeIdentifier::STRING,
    ];

    /**
     * Type identifiers settype() understands. The remaining builtin identifiers - among them
     * mixed, iterable and callable - have no cast equivalent; passing one to settype() raises a
     * ValueError, so a value targeting such a type is kept as it is.
     *
     * @var list<TypeIdentifier>
     */
    private const array CASTABLE_IDENTIFIERS = [
        TypeIdentifier::ARRAY,
        TypeIdentifier::BOOL,
        TypeIdentifier::FLOAT,
        TypeIdentifier::INT,
        TypeIdentifier::NULL,
        TypeIdentifier::OBJECT,
        TypeIdentifier::STRING,
    ];

    /**
     * Determines whether the provided type represents a builtin PHP value.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type is a builtin PHP type.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        return $type instanceof BuiltinType;
    }

    /**
     * Converts the provided value to the builtin type defined by the metadata.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Value cast to the requested builtin type when possible.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        assert($type instanceof BuiltinType);

        $normalized = $this->normalizeValue($value, $type);
        $identifier = $type->getTypeIdentifier();
        $isCastable = in_array($identifier, self::CASTABLE_IDENTIFIERS, true);

        if (
            ($normalized !== null)
            && !$this->isCompatibleValue($normalized, $identifier)
            && (
                !$isCastable
                || $this->isCompositeTargetingScalar($normalized, $identifier)
                || $this->isUnrepresentableFloatTargetingInt($normalized, $identifier)
            )
        ) {
            // Two cases have no conversion worth attempting. An identifier settype() does not know
            // - mixed, iterable, callable, the literal types - cannot be cast at all. And a
            // composite value targeting a scalar type is not something settype() refuses: it
            // produces nonsense, whether loudly (the literal 'Array' plus a PHP warning for string)
            // or silently (true/1/1.0 for bool, int and float). Both surface as a mapping exception
            // instead. The throw is the recording path: the caller records it once, which is why
            // guardCompatibility() is not consulted here.
            //
            // Scalar-to-scalar coercion is deliberately left alone - an int reaching a string
            // property is what lenient mode exists to absorb.
            throw new TypeMismatchException(
                $context->getPath(),
                $identifier->value,
                get_debug_type($normalized),
            );
        }

        if (($normalized !== null) && !$isCastable) {
            // Compatible, but there is no settype() equivalent for this identifier - mixed,
            // iterable, callable and the literal types. The value is already what it needs to be.
            return $normalized;
        }

        $this->guardCompatibility($normalized, $type, $context);

        if ($normalized === null) {
            return null;
        }

        $converted = $normalized;
        settype($converted, $identifier->value);

        return $converted;
    }

    /**
     * Normalizes common scalar representations before the conversion happens.
     *
     * @param mixed                       $value Raw value coming from the input payload.
     * @param BuiltinType<TypeIdentifier> $type  Type metadata describing the target property.
     *
     * @return mixed Normalized value that is compatible with the builtin type conversion.
     */
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

        // Only a float the int type can actually hold is a representation of an int. Anything else
        // is left alone and reaches the compatibility guard, which reports the mismatch instead of
        // quietly discarding what was lost - the conversion happened before any check ran, so it
        // was invisible even in strict mode.
        //
        if (($identifier === 'int') && is_float($value) && $this->isIntRepresentable($value) && (floor($value) === $value)) {
            return (int) $value;
        }

        if ($identifier === 'float' && is_int($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Validates that the value matches the builtin type or records a mismatch.
     *
     * @param mixed                       $value   Normalized value used during conversion.
     * @param BuiltinType<TypeIdentifier> $type    Type metadata describing the target property.
     * @param MappingContext              $context Mapping context providing configuration such as strict mode.
     *
     * @return void
     */
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

    /**
     * Determines whether the builtin type allows null values.
     *
     * @param BuiltinType<TypeIdentifier> $type Type metadata describing the target property.
     *
     * @return bool TRUE when the builtin type can be null.
     */
    private function allowsNull(BuiltinType $type): bool
    {
        return $type->isNullable();
    }

    /**
     * Determines whether a float targets the int type without being representable as one.
     *
     * Rejected rather than cast, for the same reason a composite reaching a scalar is: the result
     * would carry no information from the payload. 9.3e18 exceeds PHP_INT_MAX, so casting wraps it
     * to a negative number - a sign flip presented as a successful coercion - and PHP emits a "not
     * representable as an int" warning while doing it. INF and NAN are the same class of value.
     *
     * A merely fractional float is NOT covered here: 3.9 reaching an int property is a genuine
     * coercion that loses only the fraction, and lenient mode exists to absorb it (recorded, so
     * the loss stays visible).
     *
     * @param mixed          $value      Normalized value used during conversion.
     * @param TypeIdentifier $identifier Identifier of the builtin type to check against.
     *
     * @return bool TRUE when a float cannot be represented as the targeted int.
     */
    private function isUnrepresentableFloatTargetingInt(mixed $value, TypeIdentifier $identifier): bool
    {
        return ($identifier === TypeIdentifier::INT)
            && is_float($value)
            && !$this->isIntRepresentable($value);
    }

    /**
     * Determines whether a float can be cast to int without overflowing.
     *
     * Tested WITHOUT casting. The obvious round-trip, (float) (int) $value === $value, performs
     * the very cast being guarded against, so it emits the overflow warning on exactly the inputs
     * it is meant to reject. Comparing against the limits as floats stays silent.
     *
     * Both bounds are derived from PHP_INT_MIN, which makes the check exact on a 32-bit and a
     * 64-bit build alike without asking which one is running. PHP_INT_MIN is a power of two on
     * either - -2^63 or -2^31 - so it converts to a double with no rounding, and its negation is
     * therefore exactly PHP_INT_MAX + 1. Comparing against that with a strict < admits every
     * representable int and nothing beyond, on both builds.
     *
     * (float) PHP_INT_MAX cannot serve the same purpose: it is exact on 32-bit but rounds UP to
     * 2^63 on 64-bit, so a single comparison against it is wrong on one build or the other. Nor
     * can the rounding be detected at runtime - comparing it against PHP_INT_MAX widens the int
     * operand to the same rounded float and always reports "equal".
     *
     * @param float $value Value checked for representability.
     *
     * @return bool TRUE when the value fits into an int.
     */
    private function isIntRepresentable(float $value): bool
    {
        $lowerBound = (float) PHP_INT_MIN;

        return is_finite($value)
            && ($value >= $lowerBound)
            && ($value < -$lowerBound);
    }

    /**
     * Determines whether a composite value targets a scalar identifier, a combination settype()
     * would silently mangle instead of refusing.
     *
     * @param mixed          $value      Normalized value used during conversion.
     * @param TypeIdentifier $identifier Identifier of the builtin type to check against.
     *
     * @return bool TRUE when a composite value targets a scalar identifier.
     */
    private function isCompositeTargetingScalar(mixed $value, TypeIdentifier $identifier): bool
    {
        return (is_array($value) || is_object($value))
            && in_array($identifier, self::SCALAR_IDENTIFIERS, true);
    }

    /**
     * Checks whether the value matches the builtin type identifier.
     *
     * @param mixed          $value      Normalized value used during conversion.
     * @param TypeIdentifier $identifier Identifier of the builtin type to check against.
     *
     * @return bool TRUE when the value matches the identifier requirements.
     */
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
            'true'     => $value === true,
            'false'    => $value === false,
            'null'     => $value === null,
            default    => true,
        };
    }
}
