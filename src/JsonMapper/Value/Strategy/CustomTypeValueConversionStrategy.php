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
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Handles conversion of registered custom types.
 */
final class CustomTypeValueConversionStrategy implements ValueConversionStrategyInterface
{
    public function __construct(
        private readonly CustomTypeRegistry $registry,
    ) {
    }

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return ($type instanceof ObjectType) && $this->registry->has($type->getClassName());
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        \assert($type instanceof ObjectType);

        return $this->registry->convert($type->getClassName(), $value, $context);
    }
}
