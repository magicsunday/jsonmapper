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
 * Declares a constructor argument whose docblock accepts more than its native type, followed by a
 * required one.
 *
 * A string for the first argument converts against the docblock and is then refused by the
 * constructor declaration. That refusal is recorded during construction, after the missing
 * property of the second argument was reported and before the construction fails for it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class WidenedArgumentBeforeRequiredArgumentDto
{
    /**
     * @param int|string|null $age
     */
    public function __construct(
        public readonly ?int $age,
        public readonly string $name,
    ) {
    }
}
