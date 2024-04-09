<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Annotation\ReplaceProperty;

/**
 * Class ReplacePropertyTestClass.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @replaceProperty("type", replaces="ftype")
 * @replaceProperty("name", replaces="super-cryptic-name")
 */
class ReplacePropertyTestClass
{
    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $untouchedProperty;

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}
