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
 * Fixture for the nesting depth limit, with a union that recovers from the limit followed by a
 * property that meets it again.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthProbeInner
{
    /**
     * @var DepthChainNode|array<string, mixed>
     */
    public DepthChainNode|array $value = [];

    /**
     * @var DepthChainNode|null
     */
    public ?DepthChainNode $deep = null;
}
