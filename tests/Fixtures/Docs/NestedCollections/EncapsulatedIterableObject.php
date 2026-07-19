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
 * The encapsulated sibling of {@see IterableDataObject}.
 *
 * That fixture declares public properties, so it only exercises half the container check. An
 * ordinary DTO keeps its state private behind accessors, which is the more common shape - and a
 * check that looked only at public properties would hijack it and empty it just as silently.
 *
 * @implements IteratorAggregate<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class EncapsulatedIterableObject implements IteratorAggregate
{
    private string $title = 'untouched';

    /**
     * Returns the mapped title.
     *
     * @return string Title written by the mapper, or the sentinel when nothing was written
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the title.
     *
     * @param string $title Title taken from the payload
     *
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Returns an empty iterator; the contents are irrelevant to what this fixture pins.
     *
     * @return Traversable<int, Tag> Always empty
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}
