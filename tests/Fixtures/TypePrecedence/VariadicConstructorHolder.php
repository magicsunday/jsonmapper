<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\TypePrecedence;

/**
 * A variadic constructor whose element type a permissive property lets payloads reach unchecked.
 *
 * The property is deliberately untyped, so the raw list arrives at the variadic without the
 * conversion lane narrowing it first. Pins that a single refused element is dropped on its own,
 * reported under its own index, and its valid siblings still reach the constructor.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class VariadicConstructorHolder
{
    /**
     * The collected variadic values, untyped so the payload reaches the constructor unconverted.
     *
     * @var array<array-key, mixed>
     */
    public array $tags = [];

    public function __construct(string ...$tags)
    {
        $this->tags = $tags;
    }
}
