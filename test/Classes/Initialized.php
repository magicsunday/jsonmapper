<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue;

/**
 * Class Initialized.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class Initialized
{
    /**
     * @var int
     *
     * @ReplaceNullWithDefaultValue
     */
    public $integer = 10;

    /**
     * @var bool
     *
     * @ReplaceNullWithDefaultValue
     */
    public $bool = false;

    /**
     * @var array<string>
     *
     * @ReplaceNullWithDefaultValue
     */
    public $array = [];
}
