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
 * A non-nullable property marked ReplaceNullWithDefaultValue that the constructor seeds with a
 * value, while declaring no default the mapper can read: the property itself has none, and the
 * optional constructor parameter is deliberately not promoted, so it is ignored as a default
 * source. Used to pin that a rejected null leaves the constructor-initialised value intact.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ReplaceNullWithNullDefaultHolder
{
    #[ReplaceNullWithDefaultValue]
    public int $count;

    /**
     * @param int|null $count Optional seed value for the non-nullable property.
     */
    public function __construct(?int $count = null)
    {
        $this->count = $count ?? 7;
    }
}
