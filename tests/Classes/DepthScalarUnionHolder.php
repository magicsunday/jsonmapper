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
 * Fixture for the nesting depth limit, with two unions of an object and a scalar.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthScalarUnionHolder
{
    /**
     * @var DepthChainNode|int
     */
    public DepthChainNode|int $a = 0;

    /**
     * @var DepthChainNode|int
     */
    public DepthChainNode|int $b = 0;
}
