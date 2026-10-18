<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\Test\Fixtures\PropertyWrite\MarkerA;
use MagicSunday\Test\Fixtures\PropertyWrite\MarkerB;

/**
 * Exposes a property of an intersection type through an accessor pair only.
 *
 * Depending on the version of the type metadata, such a property is modelled as an intersection or
 * derived as an object type named after the source text of the intersection. The second shape is
 * the one the object strategy has to report instead of resolving.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class VirtualPropertyHolder
{
    private (MarkerA&MarkerB)|null $stored = null;

    public function getVirtual(): (MarkerA&MarkerB)|null
    {
        return $this->stored;
    }

    public function setVirtual((MarkerA&MarkerB)|null $virtual): void
    {
        $this->stored = $virtual;
    }
}
