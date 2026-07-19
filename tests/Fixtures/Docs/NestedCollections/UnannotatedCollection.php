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

/**
 * A container that never says what it holds.
 *
 * It has a class docblock but no "extends" or "implements" annotation naming an element type, so
 * there is nothing to resolve. Pins that this falls through rather than being treated as a
 * collection of some invented element type.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnannotatedCollection extends ArrayObject
{
}
