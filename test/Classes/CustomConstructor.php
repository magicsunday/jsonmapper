<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

/**
 * Class CustomConstructor.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class CustomConstructor
{
    /**
     * @var string
     */
    public $name;

    /**
     * CustomConstructor constructor.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
