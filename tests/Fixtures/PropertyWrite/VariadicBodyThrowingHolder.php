<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\PropertyWrite;

use TypeError;

/**
 * A variadic setter whose BODY raises a TypeError, so a test can prove that a bug inside a setter
 * is left to propagate rather than being re-labelled as a payload type mismatch.
 *
 * The variadic argument binding succeeds - the elements are ints - and the failure is raised from
 * the setter's OWN frame. That is the shape a trace-frame heuristic cannot tell apart from an
 * argument-binding refusal (both report the setter as their innermost frame), so it pins that the
 * write guard does not try to classify a variadic TypeError at all: it simply lets every one
 * through.
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
        $this->values = $values;

        throw new TypeError('Deliberate setter-body failure raised from the setter frame.');
    }
}
