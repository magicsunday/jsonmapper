<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Converter;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

/**
 * CamelCasePropertyNameConverter.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class CamelCasePropertyNameConverter implements PropertyNameConverterInterface
{
    private Inflector $inflector;

    /**
     * Creates the converter with the Doctrine inflector responsible for camel case transformations.
     *
     * The inflector dependency is initialised here so it can be reused for every conversion.
     */
    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * Converts a raw JSON property name to the camelCase variant expected by PHP properties.
     *
     * @param string $name Raw property name as provided by the JSON payload.
     *
     * @return string Normalised camelCase property name that matches PHP naming conventions.
     */
    public function convert(string $name): string
    {
        return $this->inflector->camelize($name);
    }
}
