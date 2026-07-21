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
 * Each source key that matches no declared property is collected, under its ORIGINAL payload
 * spelling and with its raw, unconverted input value, into an associative `array<string, mixed>`
 * and assigned to the marked property as-is, instead of being ignored or reported. Key and value
 * are both stored verbatim: a name converter or a `ReplaceProperty` alias decides whether a key is
 * unknown, but neither rewrites one that is - `favourite_colour` stays `favourite_colour` rather
 * than becoming `favouriteColour`. The per-value conversion pipeline is bypassed, so the marked
 * property's element type is deliberately open and the consumer interprets the raw map itself. The
 * value is only assigned when at least one unknown key is present, so the property otherwise keeps
 * its constructor default.
 *
 * One shape is not collected: a payload key PHP canonicalises to an integer (`"42"`, `"0"`) is
 * skipped rather than collected, so the map is not a byte-for-byte round trip of a payload that
 * carries such keys.
 *
 * The attribute is consumed through property reflection, so it must annotate a property — including
 * a promoted constructor property, which is reflected as one. The marked property must be
 * array-typed, and a class declares at most one (a second raises an error). A source key that
 * matches the collector property's name is mapped as that declared property, not collected. Two
 * source keys that normalize to the same DECLARED property collide and the last one wins; two
 * COLLECTED keys cannot collide, because they keep their distinct original spellings.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class UnknownPropertyCollector
{
}
