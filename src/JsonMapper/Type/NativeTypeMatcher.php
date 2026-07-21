<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Type;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_map;
use function get_parent_class;
use function implode;
use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_iterable;
use function is_object;
use function is_string;

/**
 * Answers whether a value would survive a native parameter declaration.
 *
 * The mapper resolves types from metadata, which can say more than the declaration it describes:
 * a docblock that WIDENS its own native type, or a parameter the metadata could only type as
 * `mixed`. Handing such a value to `new $className()` raises a native `TypeError` from inside the
 * mapper, outside the error report - the contract break this class exists to prevent.
 *
 * The answer is deliberately one-sided: FALSE is only ever returned for a violation this class can
 * prove, and a declaration it cannot judge reads as accepted. The asymmetry is what makes the check
 * safe to apply everywhere. A missed violation leaves PHP to raise the error it would have raised
 * anyway; a fabricated one would refuse a value the target genuinely accepts, which no caller can
 * work around.
 *
 * The call sites all declare strict types, so no scalar is coerced on the way in and the check can
 * be an exact one - with the single exception PHP itself makes, an int handed to a float.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NativeTypeMatcher
{
    /**
     * Static-only utility.
     */
    private function __construct()
    {
    }

    /**
     * Reports whether the value satisfies the declared type.
     *
     * @param ReflectionType|null $type  Declaration to satisfy; an absent declaration accepts anything.
     * @param mixed               $value Value about to be handed to the declaration.
     * @param class-string        $scope Class the declaration was written in, resolving `self` and `parent`.
     *
     * @return bool FALSE only for a proven violation; TRUE whenever the value fits or cannot be judged
     */
    public static function accepts(?ReflectionType $type, mixed $value, string $scope): bool
    {
        if (!$type instanceof ReflectionType) {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        if ($type instanceof ReflectionNamedType) {
            return self::acceptsNamed($type, $value, $scope);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (self::accepts($member, $value, $scope)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (!self::accepts($member, $value, $scope)) {
                    return false;
                }
            }

            return true;
        }

        // A type shape this class does not know cannot be judged, so it is not refused.
        return true;
    }

    /**
     * Resolves a relative type name against the class the declaration was written in.
     *
     * Whether reflection hands back `self` or the class it stands for is a runtime detail that
     * changed between supported PHP versions, so both spellings have to arrive at the same answer -
     * otherwise the guard would silently wave through every value on the older one.
     *
     * @param string       $name  Type name as reflection reported it, possibly a relative keyword.
     * @param class-string $scope Class the declaration was written in.
     *
     * @return class-string The class the name stands for
     */
    private static function resolveScope(string $name, string $scope): string
    {
        if (($name === 'self') || ($name === 'static')) {
            return $scope;
        }

        if ($name === 'parent') {
            // A `parent` type is undeclarable on a class without a parent, so reflection guarantees
            // one; the scope itself is a defensive fallback that never runs.
            $parent = get_parent_class($scope);

            return $parent === false ? $scope : $parent;
        }

        // Not a relative keyword, so a concrete class the reflected non-builtin name already names.
        /** @var class-string $name */
        return $name;
    }

    /**
     * Renders a declaration as the type name an error message should carry.
     *
     * Spelled out rather than cast: `ReflectionType` is stringable only by a deprecated conversion,
     * and the rendering an error message needs is a presentation decision of this library's own.
     *
     * @param ReflectionType $type  Declaration to render.
     * @param class-string   $scope Class the declaration was written in, resolving `self` and `parent`.
     *
     * @return string The declaration in source notation, for example `?int` or `int|string`
     */
    public static function describe(ReflectionType $type, string $scope): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->isBuiltin() ? $type->getName() : self::resolveScope($type->getName(), $scope);

            // `mixed` and a standalone `null` already carry their nullability in the name.
            if ($type->allowsNull() && ($name !== 'mixed') && ($name !== 'null')) {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                // An intersection nested in a union needs its parentheses back to stay readable.
                static fn (ReflectionType $member): string => $member instanceof ReflectionIntersectionType
                    ? '(' . self::describe($member, $scope) . ')'
                    : self::describe($member, $scope),
                $type->getTypes()
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                static fn (ReflectionType $member): string => self::describe($member, $scope),
                $type->getTypes()
            ));
        }

        // The same fallback accepts() applies to a shape this class does not know.
        return 'mixed';
    }

    /**
     * Reports whether the value satisfies a single named type.
     *
     * @param ReflectionNamedType $type  Named declaration to satisfy.
     * @param mixed               $value Non-null value about to be handed to the declaration.
     * @param class-string        $scope Class the declaration was written in.
     *
     * @return bool FALSE only for a proven violation
     */
    private static function acceptsNamed(ReflectionNamedType $type, mixed $value, string $scope): bool
    {
        $name = $type->getName();

        if (!$type->isBuiltin()) {
            $resolved = self::resolveScope($name, $scope);

            return $value instanceof $resolved;
        }

        return match ($name) {
            'int' => is_int($value),
            // The one widening PHP performs even under strict types.
            'float'    => is_float($value) || is_int($value),
            'string'   => is_string($value),
            'bool'     => is_bool($value),
            'true'     => $value === true,
            'false'    => $value === false,
            'array'    => is_array($value),
            'object'   => is_object($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            // `mixed`, `null`, `void`, `never` and anything added to the language later.
            default => true,
        };
    }
}
