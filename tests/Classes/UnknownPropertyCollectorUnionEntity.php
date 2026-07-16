<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;

/**
 * Fixture whose collector is declared with a union that includes a non-array, non-null member
 * (`array|int`), so a test can assert such a declaration is rejected: a collector holds an array map
 * and must not also permit a scalar. (A non-nullable union is used because the code style rewrites
 * the valid `array|null` form to `?array`, which the named-type branch already accepts.).
 */
final class UnknownPropertyCollectorUnionEntity
{
    /**
     * @param string                   $name  The known property.
     * @param array<string, mixed>|int $extra The invalid union-typed collector (a non-array member).
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly array|int $extra = [],
    ) {
    }
}
