<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\EntryPoint;

/**
 * A property whose collection wrapper cannot be instantiated - the lane the entry-point guard does
 * not cover, reached through CollectionFactory when the property is hydrated.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class CollectionPropertyHolder
{
    /**
     * A collection typed by an abstract wrapper.
     *
     * @var AbstractShapeCollection
     */
    public AbstractShapeCollection $shapes;
}
