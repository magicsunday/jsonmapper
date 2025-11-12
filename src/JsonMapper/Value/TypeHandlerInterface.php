<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value;

use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;

/**
 * Describes a custom type handler used during value conversion.
 */
interface TypeHandlerInterface
{
    /**
     * Determines whether the handler supports the provided type and value.
     *
     * @param Type  $type  Type to convert.
     * @param mixed $value Value extracted from the JSON payload.
     */
    public function supports(Type $type, mixed $value): bool;

    /**
     * Converts the value for the provided type.
     *
     * @param Type           $type    Type to convert.
     * @param mixed          $value   Value extracted from the JSON payload.
     * @param MappingContext $context Current mapping context.
     *
     * @return mixed The converted value.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed;
}
