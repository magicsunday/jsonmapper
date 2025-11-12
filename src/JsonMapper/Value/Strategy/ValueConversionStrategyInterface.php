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
 * Contract for value conversion strategies.
 */
interface ValueConversionStrategyInterface
{
    public function supports(mixed $value, Type $type, MappingContext $context): bool;

    public function convert(mixed $value, Type $type, MappingContext $context): mixed;
}
