<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use DateTime;

/**
 * A caller's own mutable date class.
 *
 * The documentation promises "or your own subclass", and only the builtin classes were pinned - a
 * predicate narrowed to those two names would keep the suite green while dropping this shape.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class CustomDateTime extends DateTime
{
}
