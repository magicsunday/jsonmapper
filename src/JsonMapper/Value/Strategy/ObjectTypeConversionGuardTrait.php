<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

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
     * Executes the provided converter when a valid object type is available.
     *
     * @param Type                           $type      Type metadata describing the target property.
     * @param callable(string, mixed): mixed $converter Callback that performs the actual conversion when a class-string is available.
     * @param mixed                          $value     Raw value coming from the input payload.
     *
     * @return mixed Result from the converter or the original value when no object type was detected.
     */
    private function convertObjectValue(Type $type, mixed $value, callable $converter): mixed
    {
        $objectType = $this->extractObjectType($type);

        // Unreachable while the chain is ordered as it is: supports() returns false for a non-object
        // type, so convert() is not called for one. It also narrows the nullable result of
        // extractObjectType(). A chain change that let a non-object type through gets the value
        // handed back untouched rather than a dereferenced null.
        if ($objectType === null) {
            return $value;
        }

        return $converter($objectType->getClassName(), $value);
    }
}
