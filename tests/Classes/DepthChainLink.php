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
 * One link of a chain whose child is a plain object property rather than a nullable one, so a
 * failure deep in the chain is not judged as the choice of a union member.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthChainLink
{
    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var DepthChainLink
     */
    public DepthChainLink $child;
}
