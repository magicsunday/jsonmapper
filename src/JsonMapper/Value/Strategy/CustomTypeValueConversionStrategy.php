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

/**
 * Handles conversion of registered custom types.
 */
final readonly class CustomTypeValueConversionStrategy implements ValueConversionStrategyInterface
{
    public function __construct(
        private CustomTypeRegistry $registry,
    ) {
    }

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $this->registry->supports($type, $value);
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        return $this->registry->convert($type, $value, $context);
    }
}
