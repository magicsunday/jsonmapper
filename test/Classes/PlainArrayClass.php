<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

/**
 * Class PlainArrayClass.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class PlainArrayClass
{
    /**
     * @var int[]
     */
    public $values;

    /**
     * @return int[]
     */
    public function getValues()
    {
        return $this->values;
    }
}
