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
 * Lacks required properties in a nested object, then holds a value of the wrong type.
 *
 * The missing records come first, so a report that is already full refuses the type failure
 * behind them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MissingThenMismatchDto
{
    public TwoRequiredPropertiesDto $nested;

    public int $count = 0;
}
