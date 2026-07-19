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
 * The other half of the cache-key collision pair. Its name folds to exactly the same string as
 * MagicSunday\Test\Classes\Ns\Item does - "MagicSunday_Test_Classes_Ns_Item" - because the old key
 * builder replaced every backslash with the underscore this class carries in its own name.
 *
 * Underscores inside a namespaced class name are the legacy PEAR-style shape the issue names, and
 * keeping the class namespaced means both halves of the pair are really autoloaded. A
 * global-namespace class would not be: PSR-4 maps this directory to MagicSunday\Test\, so composer
 * skips anything else, and a test naming it would pass without the class ever existing.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class Ns_Item
{
    public int $value = 0;
}
