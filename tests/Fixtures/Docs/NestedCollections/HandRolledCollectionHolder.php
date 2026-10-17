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
 * Holds a hand-rolled collection as a property, so the mapper reaches the container through the
 * property path and fills it from the payload's list.
 *
 * The property carries no generic docblock, so the element type can only come from the collection
 * class's own `@implements` annotation.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class HandRolledCollectionHolder
{
    /**
     * The hand-rolled collection to fill. Carries no generic docblock on purpose, so the element
     * type is resolved from the collection class's own annotation rather than from here.
     *
     * @var HandRolledTagCollection
     */
    public HandRolledTagCollection $tags;
}
