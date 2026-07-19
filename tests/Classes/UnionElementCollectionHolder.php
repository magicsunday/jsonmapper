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
 * Collections whose ELEMENT type is a union or nullable.
 *
 * Union resolution lived on the property path only, so a collection element declared this way
 * matched no strategy and was handed back unconverted.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionElementCollectionHolder
{
    /**
     * @var array<int, Simple|null>
     */
    public array $nullableItems = [];

    /**
     * @var array<int, Simple|string>
     */
    public array $unionItems = [];
}
