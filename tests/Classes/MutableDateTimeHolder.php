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
use DateTimeInterface;

/**
 * The mutable date/time shapes.
 *
 * Only DateTimeImmutable was supported, so a mutable DateTime property fell through to the object
 * strategy. The interface-typed property is the case that has to keep failing: the mapper cannot
 * instantiate an interface, so it must say so rather than picking an implementation on the user's
 * behalf.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MutableDateTimeHolder
{
    public DateTime $when;

    public ?DateTime $optional = null;

    public ?DateTimeInterface $byInterface = null;
}
