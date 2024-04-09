<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

/**
 * Class VariadicSetterClass.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class VariadicSetterClass
{
    /**
     * @var int[]
     */
    private $values;

    /**
     * @return int[]
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param int ...$values
     *
     * @return void
     */
    public function setValues(...$values)
    {
        $this->values = $values;
    }
}
