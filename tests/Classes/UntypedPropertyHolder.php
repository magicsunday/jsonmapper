<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

/**
 * A class with a property that has neither a native type declaration nor a docblock type,
 * so the type resolver has no metadata to work with and must fall back to its default.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UntypedPropertyHolder
{
    // The missing type is the fixture's purpose. The resolver must fall back to its default.
    // The resulting missingType.property report is silenced by a scoped phpstan.neon entry.
    public $anything = 'preset';
}
