<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use ArrayObject;
use TypeError;

/**
 * A collection whose constructor accepts the element list but whose body raises a native TypeError.
 *
 * The constructor parameter takes the element array, so the mapper's pre-check lets the call through.
 * The fault is entirely in the constructor body, which stands in for a genuine defect in a
 * user-written collection class. Such a TypeError must propagate as itself rather than be caught and
 * re-labelled as a payload type mismatch, so the real bug stays visible.
 *
 * @extends ArrayObject<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class BodyErrorTagCollection extends ArrayObject
{
    /**
     * Accepts the element list, then raises a native TypeError from its own body.
     *
     * @param array<int, Tag> $elements The element list, which the parameter accepts.
     */
    public function __construct(array $elements)
    {
        parent::__construct($elements);

        throw new TypeError('Constructor body defect, after the element list was received.');
    }
}
