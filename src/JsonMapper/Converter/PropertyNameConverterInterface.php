<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Converter;

/**
 * PropertyNameConverterInterface.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
interface PropertyNameConverterInterface
{
    /**
     * Convert the specified JSON property name to its PHP property name.
     *
     * @param string $name Raw property name exactly as it appears in the JSON structure.
     *
     * @return string Normalised PHP property name that matches the target object's naming scheme.
     */
    public function convert(string $name): string;
}
