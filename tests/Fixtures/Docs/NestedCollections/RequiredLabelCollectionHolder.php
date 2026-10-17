<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

/**
 * Holds a collection whose constructor cannot receive the elements, reached via the property path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredLabelCollectionHolder
{
    /**
     * The collection to fill, whose required non-element constructor parameter makes it unfillable by
     * the constructor lane, so the mapper reports the failure instead of crashing.
     *
     * @var RequiredLabelTagCollection
     */
    public RequiredLabelTagCollection $tags;
}
