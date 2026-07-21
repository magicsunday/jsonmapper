<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Shapes;

use MagicSunday\JsonMapper\Attribute\ReplaceProperty;

/**
 * One property reached by two legacy names.
 *
 * An API that renamed a field twice leaves consumers with three spellings in circulation, and a
 * class that has to accept all of them declares one alias per spelling. The attribute is
 * repeatable for exactly this.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
#[ReplaceProperty('label', replaces: 'legacy_label')]
#[ReplaceProperty('label', replaces: 'ancient-label')]
final class TwoAliasHolder
{
    /**
     * The one property all three spellings mean.
     */
    public string $label = '';
}
