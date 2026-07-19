<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * The other half of the cache-key collision pair, deliberately in the GLOBAL namespace: its name is
 * character-for-character what folding MagicSunday\Test\Classes\Ns\Item's backslashes produces. A
 * legacy PEAR-style class name is exactly the shape that collides, which is why the pair is real
 * rather than contrived.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MagicSunday_Test_Classes_Ns_Item
{
    public int $value = 0;
}
