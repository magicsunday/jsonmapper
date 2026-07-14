<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

/**
 * A final readonly value object whose promoted constructor parameter carries the
 * ReplaceNullWithDefaultValue attribute, so an explicit null payload must fall back to the
 * parameter default even on the constructor path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ReplaceNullReadonly
{
    /**
     * @param int $limit The limit, defaulting when the payload is null
     */
    public function __construct(
        #[ReplaceNullWithDefaultValue]
        public int $limit = 10,
    ) {
    }
}
