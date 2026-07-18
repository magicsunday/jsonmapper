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
 * A non-nullable property marked ReplaceNullWithDefaultValue whose declared default is itself
 * NULL, used to pin that the attribute shortcut must not assign that null to a non-nullable
 * target. The default lives on the optional constructor parameter, which is deliberately not
 * promoted so the property is hydrated through the setter path.
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
