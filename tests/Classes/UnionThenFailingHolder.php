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
 * A union property followed by one that fails. Resolving a union forces error collection on for
 * the duration of the candidate evaluation; the property declared AFTER it is what makes a missing
 * restore observable. With a single-property fixture nothing is mapped once the forced window
 * closes, so a leaked flag would keep the whole suite green.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionThenFailingHolder
{
    public Person|string $value = 'untouched';

    public int $count = -1;
}
