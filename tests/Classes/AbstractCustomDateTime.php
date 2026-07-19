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
 * An abstract date class.
 *
 * class_exists() is true for it, so a guard built on that alone claims it and reaches `new`,
 * raising "Cannot instantiate abstract class" - a native Error that escapes even lenient mode.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
abstract class AbstractCustomDateTime extends DateTime
{
}
