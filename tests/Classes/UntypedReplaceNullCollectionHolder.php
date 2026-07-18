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
 * An untyped collection property marked ReplaceNullWithDefaultValue. Reflection reports an
 * implicit null default for every untyped property, so this pins that such a default must not
 * pre-empt the treat-null-as-empty-collection option.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UntypedReplaceNullCollectionHolder
{
    /**
     * @var string[]
     */
    #[ReplaceNullWithDefaultValue]
    public $items;
}
