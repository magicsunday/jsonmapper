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
 * A class combining a promoted readonly constructor parameter with an additional settable
 * property, so both the constructor argument and the after-construction assignment paths run.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MixedConstructorEntity
{
    public string $note = 'none';

    /**
     * @param string $id The identifier, set through the constructor
     */
    public function __construct(
        public readonly string $id = 'initial',
    ) {
    }
}
