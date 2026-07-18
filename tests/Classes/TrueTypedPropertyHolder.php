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
 * A property typed with the true literal. That identifier has no settype() equivalent either,
 * and unlike iterable it accepts exactly one value, so it pins the compatibility check for the
 * literal type identifiers. It is deliberately left uninitialized: a default would be
 * indistinguishable from a successfully mapped value, so only the initialization state can tell
 * an assignment apart from a skipped property.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class TrueTypedPropertyHolder
{
    public true $flag;
}
