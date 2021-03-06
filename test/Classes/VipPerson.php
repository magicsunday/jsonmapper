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
 * Class VipPerson.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class VipPerson extends Person
{
    /**
     * @var bool
     */
    public bool $is_vip = true;

    /**
     * Number of oscars won.
     *
     * @var int
     */
    public int $oscars;

    /**
     * @var string
     */
    public string $name;
}
