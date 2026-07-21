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
 * A docblock that WIDENS its own native declaration.
 *
 * The property rejects null; the docblock claims it accepts one. The docblock must not be able to
 * grant a value the property itself refuses - honouring it let a null through the conversion guard
 * and into the assignment, where the write guard reported it against the accessor's view of the
 * type instead of the property's own declaration.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class WideningPropertyHolder
{
    /**
     * @var int|null
     */
    public int $value = 7;
}
