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
 * The false counterpart to TrueTypedPropertyHolder. Both literal identifiers take the same
 * conversion path, so each needs its own case - otherwise a copy-paste slip in the compatibility
 * check would ship unnoticed. Left uninitialized for the same reason as its sibling.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class FalseTypedPropertyHolder
{
    public false $flag;
}
