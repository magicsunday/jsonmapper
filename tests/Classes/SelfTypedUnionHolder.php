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
 * A class with a union that names its own class by the keyword.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class SelfTypedUnionHolder
{
    /**
     * Name of the holder.
     */
    public string $name = '';

    /**
     * A value that is either another holder or a string.
     */
    public self|string|null $either = null;
}
