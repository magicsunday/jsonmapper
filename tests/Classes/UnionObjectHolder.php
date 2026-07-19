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
 * A union with an object member. This is the shape that actually exercises the candidate
 * selection: an object candidate with a bad nested field RECORDS an error rather than throwing,
 * and the recorded count is the only signal the selection has to go on. A scalar-only union
 * rejects by throwing instead and therefore cannot tell the two implementations apart.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionObjectHolder
{
    public Person|string $value = 'untouched';
}
