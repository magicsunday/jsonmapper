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
 * Class Simple.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class Simple
{
    /**
     * PHP7.4 typed property
     */
    public int $id;

    /**
     * PHP7.4 typed property
     *
     * @var string
     */
    public string $name;

    /**
     * @var int
     */
    public $int;

    /**
     * @var float
     */
    public $float;

    /**
     * @var bool
     */
    public $bool;

    /**
     * @var string
     */
    public $string;
}
