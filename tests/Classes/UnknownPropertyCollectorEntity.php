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
 * Fixture whose promoted `$extra` property collects every source key that matches no declared
 * property.
 */
final class UnknownPropertyCollectorEntity
{
    /**
     * The collector default is a distinguishable sentinel (not an empty array) so a test can tell a
     * preserved constructor default apart from an unwanted empty-collection assignment.
     *
     * @param string               $name  The known property.
     * @param array<string, mixed> $extra The sink for unknown source keys, as a raw map of key to value.
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly array $extra = ['_default' => true],
    ) {
    }
}
