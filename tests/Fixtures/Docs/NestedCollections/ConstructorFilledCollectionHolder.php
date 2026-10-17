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
 * Holds a collection that must be filled through its constructor, reached via the property path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ConstructorFilledCollectionHolder
{
    /**
     * The collection to fill, whose declared constructor parameter means the mapper hands it the
     * whole list rather than writing element by element.
     *
     * @var ConstructorFilledTagCollection
     */
    public ConstructorFilledTagCollection $tags;
}
