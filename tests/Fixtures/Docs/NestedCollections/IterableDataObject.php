<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * An ordinary data object that also happens to be iterable and to say what it yields.
 *
 * The negative control for the collection-class detection. Declaring an element type is not
 * enough to make something a collection: routing this to the collection factory builds it from
 * the payload's elements and silently drops every property below - the object is of the right
 * class and its fields are empty.
 *
 * @implements IteratorAggregate<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class IterableDataObject implements IteratorAggregate
{
    public string $title = 'untouched';

    public int $count = -1;

    /**
     * Returns an iterator over the contained tags.
     *
     * @return Traversable<int, Tag> Iterator over the contained tags
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}
