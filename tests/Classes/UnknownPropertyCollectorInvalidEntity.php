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
 * Fixture that misuses the collector attribute on a non-array property, so a test can assert the
 * mapper rejects the declaration up front instead of failing late with a native TypeError.
 */
final class UnknownPropertyCollectorInvalidEntity
{
    /**
     * @param string $name  The known property.
     * @param string $extra The invalidly-typed collector (must be array-typed).
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly string $extra = '',
    ) {
    }
}
