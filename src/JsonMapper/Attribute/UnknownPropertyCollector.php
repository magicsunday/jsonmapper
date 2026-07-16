<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Attribute;

use Attribute;

/**
 * Attribute marking a property as the sink for unknown input keys.
 *
 * Each source key that matches no declared property is collected, by its normalized name and its
 * raw, unconverted input value, into an associative `array<string, mixed>` and assigned to the
 * marked property as-is, instead of being ignored or reported. The per-value conversion pipeline is
 * bypassed, so the marked property's element type is deliberately open and the consumer interprets
 * the raw map itself. The value is only assigned when at least one unknown key is present, so the
 * property otherwise keeps its constructor default.
 *
 * The attribute is consumed through property reflection, so it must annotate a property — including
 * a promoted constructor property, which is reflected as one. The marked property must be
 * array-typed, and a class declares at most one (a second raises an error). A source key that
 * matches the collector property's name is mapped as that declared property, not collected. As with
 * ordinary mapping, two source keys that normalize to the same name collide, and the last one wins.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class UnknownPropertyCollector
{
}
