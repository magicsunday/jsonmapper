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
 * A collection whose annotation names a template parameter rather than a class.
 *
 * The annotation parses and yields a collection type, so the "declares nothing" guard does not
 * catch it - but the element type is still unusable, and without its own check it reaches the
 * factory and dies on a message naming neither the annotation nor the fix.
 *
 * @template T
 *
 * @extends ArrayObject<int, T>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class TemplatedCollection extends ArrayObject
{
}
