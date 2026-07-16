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
 * Fixture that marks two properties as the collector, so a test can assert the mapper rejects the
 * ambiguous declaration rather than silently honouring only the first.
 */
final class UnknownPropertyCollectorDuplicateEntity
{
    /**
     * @param array<string, mixed> $first  The first (ambiguous) collector.
     * @param array<string, mixed> $second The second (ambiguous) collector.
     */
    public function __construct(
        #[UnknownPropertyCollector]
        public readonly array $first = [],
        #[UnknownPropertyCollector]
        public readonly array $second = [],
    ) {
    }
}
