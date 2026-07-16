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
 * Fixture whose collector is a private promoted property. The default reflection extractor does not
 * expose it, so it is absent from the mapper's declared-property list — the one configuration in
 * which the diversion's declared-property membership check no longer excludes the collector's own
 * key, making the explicit `!== $collectorProperty` guard load-bearing.
 */
final class UnknownPropertyCollectorHiddenEntity
{
    /**
     * @param string               $name  The known property.
     * @param array<string, mixed> $extra The private collector, invisible to the property extractor.
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        private readonly array $extra = [],
    ) {
    }

    /**
     * Exposes the otherwise-private collected map for assertion.
     *
     * @return array<string, mixed> The collected unknown keys.
     */
    public function extra(): array
    {
        return $this->extra;
    }
}
