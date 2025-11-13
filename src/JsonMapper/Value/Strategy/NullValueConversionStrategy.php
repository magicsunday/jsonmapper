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

/**
 * Returns null values as-is.
 */
final class NullValueConversionStrategy implements ValueConversionStrategyInterface
{
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $value === null;
    }

    public function convert(mixed $value, Type $type, MappingContext $context): null
    {
        return null;
    }
}
