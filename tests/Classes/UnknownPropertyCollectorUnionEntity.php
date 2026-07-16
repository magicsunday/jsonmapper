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
 * Fixture whose collector is declared with a genuine union type (one member being `array`), so a
 * test can assert a union whose members include `array` is honoured rather than rejected as
 * non-array. A non-nullable union is used because the code style rewrites `array|null` to `?array`.
 */
final class UnknownPropertyCollectorUnionEntity
{
    /**
     * @param string                   $name  The known property.
     * @param array<string, mixed>|int $extra The union-typed collector, one member being array.
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly array|int $extra = [],
    ) {
    }
}
