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
 * A string-keyed collection.
 *
 * The int-keyed sibling is fed a JSON list, so its key type is never observable and an
 * implementation that dropped or reordered the annotation's type parameters would still pass.
 * This one makes the key half of the re-wrap visible.
 *
 * @extends ArrayObject<string, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class StringKeyedTagCollection extends ArrayObject
{
}
