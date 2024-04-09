<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

/**
 * Class Person.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @property int $oscars Dynamic created property
 */
class Person
{
    /**
     * @var bool
     */
    public $is_vip = false;

    /**
     * @var string
     */
    public $name;
}
