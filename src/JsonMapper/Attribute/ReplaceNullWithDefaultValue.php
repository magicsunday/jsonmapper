<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Attribute;

use Attribute;

/**
 * Attribute instructing the mapper to keep an existing default value.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ReplaceNullWithDefaultValue
{
}
