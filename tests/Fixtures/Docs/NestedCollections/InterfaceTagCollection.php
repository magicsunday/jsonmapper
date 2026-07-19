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
 * A collection whose element type is an interface.
 *
 * Testing the element type with class_exists() alone refused this before the class map ever got a
 * say, which is how a polymorphic collection is normally declared.
 *
 * @extends ArrayObject<int, TagInterface>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class InterfaceTagCollection extends ArrayObject
{
}
