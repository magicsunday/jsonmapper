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
 * One property per scalar target type, each seeded with a sentinel that no test payload produces.
 * Lenient mode coerces a mismatching scalar rather than rejecting it, and that matrix had no
 * coverage at all - a change that widened rejection to every builtin type passed the whole suite.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class BuiltinCoercionHolder
{
    public string $text = 'sentinel';

    public int $number = -1;

    public float $decimal = -1.5;

    public bool $flag = false;
}
