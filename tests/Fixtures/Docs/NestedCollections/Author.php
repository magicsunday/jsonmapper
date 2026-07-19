<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

/**
 * A second element type, deliberately distinct from {@see Tag}.
 *
 * The memo is keyed by class name. With only one element type in the fixtures, a memo that
 * ignored its key and served the first resolution to every later class would be invisible.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class Author
{
    public string $alias = '';
}
