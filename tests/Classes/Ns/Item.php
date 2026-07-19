<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes\Ns;

/**
 * Half of the cache-key collision pair. Folding backslashes to underscores turns this class's name
 * into the name of its sibling in the global namespace, so both mapped to the same key.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class Item
{
    public int $value = 0;
}
