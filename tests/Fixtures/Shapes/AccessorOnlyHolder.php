<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Shapes;

/**
 * A property that exists only as an accessor pair.
 *
 * PropertyInfo reports "label" as a property because the getter and setter describe one, while
 * reflection finds no property of that name - so the write has to go through the setter.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class AccessorOnlyHolder
{
    private string $stored = '';

    /**
     * Returns the stored label.
     *
     * @return string Label written through setLabel()
     */
    public function getLabel(): string
    {
        return $this->stored;
    }

    /**
     * Stores the provided label.
     *
     * @param string $label Label to store.
     */
    public function setLabel(string $label): void
    {
        $this->stored = $label;
    }
}
