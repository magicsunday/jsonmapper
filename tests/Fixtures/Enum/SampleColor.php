<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Enum;

/**
 * A pure enum without a backing type. Its cases carry no scalar value, so a payload can only
 * address them by case name.
 */
enum SampleColor
{
    case Red;
    case Blue;
}
