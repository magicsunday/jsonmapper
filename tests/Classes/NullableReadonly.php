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
 * A final readonly value object with a nullable, promoted constructor argument that has no
 * default, exercising the null fallback for a missing nullable argument.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class NullableReadonly
{
    /**
     * @param string|null $note The optional note
     */
    public function __construct(
        public ?string $note,
    ) {
    }
}
