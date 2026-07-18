<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

/**
 * A collection property with a non-empty default that is marked ReplaceNullWithDefaultValue,
 * used to pin the precedence of the attribute over the treat-null-as-empty-collection option.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ReplaceNullCollectionHolder
{
    /**
     * @var string[]
     */
    #[ReplaceNullWithDefaultValue]
    public array $items = ['preset'];
}
