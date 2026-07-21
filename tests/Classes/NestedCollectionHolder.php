<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

/**
 * A collection whose elements are themselves collections, so a null element is a null COLLECTION -
 * the shape on which the treat-null-as-empty-collection policy must reach the element path the same
 * way it reaches a top-level collection property.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NestedCollectionHolder
{
    /**
     * @var array<int, array<int, Simple>>
     */
    public array $rows = [];
}
