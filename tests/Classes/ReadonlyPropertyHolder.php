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
 * A class with a readonly property that is not a constructor parameter, so it cannot be built
 * through the constructor and its readonly property genuinely cannot be written by assignment —
 * used to exercise the readonly-violation report.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ReadonlyPropertyHolder
{
    public readonly string $id;

    /**
     * Initialises the readonly property so a later mapping write is a genuine violation.
     */
    public function __construct()
    {
        $this->id = 'initial';
    }
}
