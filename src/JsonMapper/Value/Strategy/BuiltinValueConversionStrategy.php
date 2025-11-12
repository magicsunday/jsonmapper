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
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

use function assert;

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

        $converted = $value;
        settype($converted, $type->getTypeIdentifier()->value);

        return $converted;
    }
}
