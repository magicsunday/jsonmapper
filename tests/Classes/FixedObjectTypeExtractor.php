<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Answers every property with a nullable object type of one fixed class name.
 *
 * A reported case handed back the source text of an intersection as the class name of an object
 * type, and a declaration can name a class that resolves to nothing. This extractor reproduces both
 * whichever version of the type metadata is installed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class FixedObjectTypeExtractor implements PropertyTypeExtractorInterface
{
    /**
     * Creates the extractor with the name every object type reports.
     *
     * @param string $className Name the object type reports, whether or not it is a class
     */
    public function __construct(private string $className)
    {
    }

    /**
     * Answers nothing, because the mapper asks for the single type through getType().
     *
     * @param string                  $class    Class that declares the property
     * @param string                  $property Name of the property
     * @param array<array-key, mixed> $context  Extraction context
     */
    public function getTypes(string $class, string $property, array $context = []): null
    {
        return null;
    }

    /**
     * Answers a nullable object type that carries the configured name.
     *
     * @param string                  $class    Class that declares the property
     * @param string                  $property Name of the property
     * @param array<array-key, mixed> $context  Extraction context
     *
     * @return Type Nullable object type named after the configured string
     */
    public function getType(string $class, string $property, array $context = []): Type
    {
        return Type::nullable(Type::object($this->className));
    }
}
