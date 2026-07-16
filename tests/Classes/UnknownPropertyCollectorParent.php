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
 * Fixture nesting a {@see UnknownPropertyCollectorEntity} and carrying its own collector, so a test
 * can prove unknown keys are captured per level (parent's on the parent, child's on the child).
 */
final class UnknownPropertyCollectorParent
{
    /**
     * @param string                              $title The known property.
     * @param UnknownPropertyCollectorEntity|null $child The nested entity carrying its own collector.
     * @param array<string, mixed>                $rest  The parent-level sink for unknown source keys.
     */
    public function __construct(
        public readonly string $title = '',
        public readonly ?UnknownPropertyCollectorEntity $child = null,
        #[UnknownPropertyCollector]
        public readonly array $rest = [],
    ) {
    }
}
