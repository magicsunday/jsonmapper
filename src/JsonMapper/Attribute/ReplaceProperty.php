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
 * Attribute used to instruct the mapper to rename a JSON field.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ReplaceProperty
{
    /**
     * @param string $value Name of the incoming JSON field that should be renamed.
     * @param string $replaces Target property name that receives the value.
     */
    public function __construct(
        public string $value,
        public string $replaces,
    ) {
    }
}
