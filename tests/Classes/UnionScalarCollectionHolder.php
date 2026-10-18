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
 * A collection whose element type is a scalar union.
 *
 * Exercises union member selection for list elements: an exact runtime-type match is preferred over
 * a coercion, and an element matching no member is dropped with a recorded error at its index.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionScalarCollectionHolder
{
    /**
     * @var array<int, int|string>
     */
    public array $items = [];
}
