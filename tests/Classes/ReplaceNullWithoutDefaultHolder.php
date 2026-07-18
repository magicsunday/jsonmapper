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
 * A non-nullable property marked ReplaceNullWithDefaultValue that declares no default value,
 * used to pin that the attribute must not bypass the null type guard when there is nothing
 * to fall back to.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ReplaceNullWithoutDefaultHolder
{
    #[ReplaceNullWithDefaultValue]
    public int $number;
}
