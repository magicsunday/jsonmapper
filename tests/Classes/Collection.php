<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use ArrayAccess;
use ArrayObject;

/**
 * Class Collection.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends ArrayObject<TKey, TValue>
 *
 * @implements ArrayAccess<TKey, TValue>
 */
class Collection extends ArrayObject implements ArrayAccess
{
}
