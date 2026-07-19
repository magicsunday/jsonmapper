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
 * Collects the collection-property shapes whose resolution is decided by the collection class
 * rather than by the property, so each can be reached as a property type.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class CollectionShapesHolder
{
    /**
     * @var StringKeyedTagCollection
     */
    public StringKeyedTagCollection $keyed;

    /**
     * @var UnannotatedCollection
     */
    public UnannotatedCollection $unannotated;

    /**
     * @var TagCollection
     */
    public TagCollection $tags;

    /**
     * @var TagCollection|null
     */
    public ?TagCollection $optional = null;
}
