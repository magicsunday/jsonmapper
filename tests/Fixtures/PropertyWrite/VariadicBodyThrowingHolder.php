<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\PropertyWrite;

/**
 * A variadic setter whose BODY raises a TypeError, so a test can prove that a bug inside a setter
 * is left to propagate rather than being re-labelled as a payload type mismatch.
 *
 * The variadic argument binding succeeds - the elements are ints - and only the delegating call
 * inside the body fails, which is exactly the case the write guard must not mask.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class VariadicBodyThrowingHolder
{
    /**
     * @var int[]
     */
    private array $values = [];

    /**
     * @return int[] The stored values.
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @param int ...$values Values whose binding succeeds before the body refuses them.
     */
    public function setValues(int ...$values): void
    {
        foreach ($values as $value) {
            $this->requireString($value);
        }

        $this->values = $values;
    }

    /**
     * A strict-typed delegate the body calls with an int, raising a TypeError from inside the body.
     *
     * @param string $value Deliberately typed so an int argument refuses.
     */
    private function requireString(string $value): void
    {
        // Never reached with a valid argument; exists only to raise a body-level TypeError.
    }
}
