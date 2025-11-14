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
 * Class Base.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class Base
{
    /**
     * @var string
     */
    public $name;

    /**
     * A class with a constructor and arguments.
     *
     * @var CustomConstructor
     */
    public $customContructor;

    /**
     * A class without a constructor.
     *
     * @var Simple
     */
    public $simple;

    /**
     * An array of Simple instances.
     *
     * @var Simple[]
     */
    public $simpleArray;

    /**
     * A collection of Simple instances.
     *
     * @var Collection<int, Simple>|array<int, Simple>
     */
    public $simpleCollection;

    /**
     * @var CustomClass
     */
    public $customClass;

    /**
     * @var string
     */
    private string $privateProperty = '';

    /**
     * @return string
     */
    public function getPrivateProperty(): string
    {
        return $this->privateProperty;
    }

    /**
     * @param string $privateProperty
     */
    public function setPrivateProperty(string $privateProperty): void
    {
        $this->privateProperty = $privateProperty;
    }
}
