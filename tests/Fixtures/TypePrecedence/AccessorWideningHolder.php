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
 * A non-nullable backing field whose setter accepts null.
 *
 * The write target is the SETTER, not the field, and PHP lets a mutator accept more than the
 * property stores. Pins that the mapper judges a value against the declaration it actually hands it
 * to: refusing the null here would drop a value the class documents itself as accepting.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class AccessorWideningHolder
{
    private int $value = 0;

    /**
     * Declared nullable although the field cannot hold null: the accessor's view is what the mapper
     * resolves the property type from, and this asymmetry between the pair and the field it wraps is
     * the whole fixture.
     *
     * @return int|null The stored value.
     */
    public function getValue(): ?int
    {
        return $this->value;
    }

    /**
     * @param int|null $value Value to store; null falls back to the sentinel.
     */
    public function setValue(?int $value): void
    {
        $this->value = $value ?? 42;
    }
}
