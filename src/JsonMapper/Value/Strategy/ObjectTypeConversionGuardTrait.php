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
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Provides reusable guards for strategies operating on object types.
 *
 * @internal This is not a public extension point. Register conversions through
 *           {@see \MagicSunday\JsonMapper\Value\TypeHandlerInterface} via JsonMapper::addTypeHandler().
 */
trait ObjectTypeConversionGuardTrait
{
    /**
     * Returns the provided type when it represents an object with a class name.
     *
     * @param Type $type Type metadata describing the target property.
     *
     * @return ObjectType<class-string>|null Object type when the metadata targets a concrete class; otherwise null.
     */
    private function extractObjectType(Type $type): ?ObjectType
    {
        if (!$type instanceof ObjectType) {
            return null;
        }

        if ($type->getClassName() === '') {
            return null;
        }

        return $type;
    }

    /**
     * Ensures null values comply with the target object's nullability.
     *
     * @param mixed                    $value   Raw value coming from the input payload.
     * @param ObjectType<class-string> $type    Object type metadata describing the target property.
     * @param MappingContext           $context Mapping context providing configuration such as strict mode.
     *
     * @return void
     */
    private function guardNullableValue(mixed $value, ObjectType $type, MappingContext $context): void
    {
        if ($value !== null) {
            return;
        }

        if ($type->isNullable()) {
            return;
        }

        // Reached only by a DIRECT call to a strategy using this trait. Through the value
        // converter's chain a null is claimed by NullValueConversionStrategy first, so it never
        // arrives; the guard defends the public-SPI case where a strategy is invoked outside the
        // chain, keeping a null off a non-nullable object target.
        throw new TypeMismatchException($context->getPath(), $type->getClassName(), 'null');
    }

    /**
     * Executes the provided converter when a valid object type is available.
     *
     * @param Type                           $type      Type metadata describing the target property.
     * @param MappingContext                 $context   Mapping context providing configuration such as strict mode.
     * @param mixed                          $value     Raw value coming from the input payload.
     * @param callable(string, mixed): mixed $converter Callback that performs the actual conversion when a class-string is available.
     *
     * @return mixed Result from the converter or the original value when no object type was detected.
     */
    private function convertObjectValue(Type $type, MappingContext $context, mixed $value, callable $converter): mixed
    {
        $objectType = $this->extractObjectType($type);

        // Unreachable through the chain: supports() returns false for a non-object type, so convert()
        // is not called for one. Kept for a DIRECT call that skips supports() - it hands the value
        // back untouched rather than dereferencing a null object type.
        if ($objectType === null) {
            return $value;
        }

        $this->guardNullableValue($value, $objectType, $context);

        if ($value === null) {
            return null;
        }

        return $converter($objectType->getClassName(), $value);
    }
}
