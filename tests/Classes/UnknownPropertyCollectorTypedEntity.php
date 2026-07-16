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
 * Fixture whose collector carries a concrete element type, so a test can feed an explicit key named
 * after the collector and assert it is mapped as the declared property rather than diverted into the
 * collector map.
 */
final class UnknownPropertyCollectorTypedEntity
{
    /**
     * @param string                $name  The known property.
     * @param array<string, string> $extra The collector, typed so an explicit same-named key maps cleanly.
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly array $extra = [],
    ) {
    }
}
