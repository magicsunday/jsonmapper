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
 * One link of a chain of nested objects, used to nest a payload as deep as a test needs.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthChainNode
{
    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var DepthChainNode|null
     */
    public ?DepthChainNode $child = null;
}
