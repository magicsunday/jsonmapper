<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use DateInterval;

/**
 * An interval subclass whose constructor demands something other than an interval spec.
 *
 * The interval branch builds through `new` unconditionally, so this raises a TypeError - a native
 * Error, which is why that catch has to be Throwable rather than Exception.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class WeirdConstructorInterval extends DateInterval
{
    /**
     * @param int $days Number of days the caller must supply
     */
    public function __construct(int $days)
    {
        parent::__construct('P' . $days . 'D');
    }
}
