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
 * A class that names the late bound class in a collection of its docblock.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class RelativeSpellingHolder
{
    /**
     * Name of the holder.
     */
    public string $name = '';

    /**
     * Holders of the class that is mapped.
     *
     * @var static[]
     */
    public array $items = [];
}
