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
 * A list of integers, used to observe what a rejected ELEMENT does to its siblings. The invalid
 * entry has to sit between two valid ones: an element loop that aborts on the first failure and
 * one that drops the offender look identical when the bad entry comes last.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class IntListHolder
{
    /**
     * @var array<int, int>
     */
    public array $values = [];
}
