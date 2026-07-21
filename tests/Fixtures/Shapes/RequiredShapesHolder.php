<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Shapes;

use MagicSunday\Test\Fixtures\PropertyWrite\MarkerA;
use MagicSunday\Test\Fixtures\PropertyWrite\MarkerB;

/**
 * Two composite declarations, neither with a default, that answer the required question
 * differently.
 *
 * A union naming null accepts an absent value; an intersection cannot - there is no null that
 * satisfies both members - so a payload omitting it leaves the property uninitialised.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredShapesHolder
{
    /**
     * A union that accepts an absent value.
     */
    public int|string|null $optional;

    /**
     * An intersection, which no null satisfies.
     */
    public MarkerA&MarkerB $required;
}
