<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use RuntimeException;

/**
 * Announces loudly that it was constructed, so a test can tell whether a default expression ran.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ThrowingDefault
{
    public function __construct()
    {
        throw new RuntimeException('A default expression was evaluated that nothing needed.');
    }
}
