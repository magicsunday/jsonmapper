<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\Test\Fixtures\Enum\SampleColor;

/**
 * Holds a pure enum. The sentinel default lets a test tell a rejected value apart from a
 * successfully mapped one.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnitEnumHolder
{
    public ?SampleColor $color = SampleColor::Blue;
}
