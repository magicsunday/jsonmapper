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
 * Fixture for the nesting depth limit, with an earlier property refused for its depth ahead of a
 * union whose member recovers from the limit and meets it again.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthProbeOuter
{
    /**
     * @var DepthChainNode|null
     */
    public ?DepthChainNode $a = null;

    /**
     * @var DepthProbeInner|string|null
     */
    public DepthProbeInner|string|null $p = null;
}
