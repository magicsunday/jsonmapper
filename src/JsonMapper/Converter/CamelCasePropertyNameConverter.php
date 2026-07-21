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

use function strtolower;
use function strtoupper;

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
        return $this->inflector->camelize($this->normalizeCaselessName($name));
    }

    /**
     * Lower-cases a name that carries no case information of its own.
     *
     * A SCREAMING_SNAKE key states every word boundary with a separator and says nothing with its
     * case, so the case can be discarded. Left alone, the inflector reads it as one word already
     * capitalised and answers "ADDRESS_LINE" with "aDDRESSLINE" - a name no PHP property is
     * declared under, so the key mapped to nothing at all.
     *
     * The whole name has to be caseless in this way. A name with any lower-case letter states its
     * boundaries by case as well, and flattening it would destroy them: "HTTPServer" would become
     * "httpserver", where leaving it alone at least keeps the segments a reader can see.
     *
     * @param string $name Raw property name as provided by the JSON payload.
     *
     * @return string The name, lower-cased when its case carries no information
     */
    private function normalizeCaselessName(string $name): string
    {
        $lowered = strtolower($name);

        if (($name !== strtoupper($name)) || ($name === $lowered)) {
            return $name;
        }

        return $lowered;
    }
}
