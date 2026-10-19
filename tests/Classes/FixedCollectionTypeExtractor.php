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
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Answers every property with a collection type whose wrapper class carries one fixed name.
 *
 * The same source text of an intersection that reaches an object type can name the wrapper of a
 * collection, and a declaration can name a wrapper that resolves to nothing. This extractor
 * reproduces both whichever version of the type metadata is installed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class FixedCollectionTypeExtractor implements PropertyTypeExtractorInterface
{
    /**
     * Creates the extractor with the name the wrapper class reports.
     *
     * @param string $className Name the wrapper reports, whether or not it is a class
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
     * Answers a collection of integers wrapped in an object type that carries the configured name.
     *
     * @param string                  $class    Class that declares the property
     * @param string                  $property Name of the property
     * @param array<array-key, mixed> $context  Extraction context
     *
     * @return Type Collection type with a wrapper named after the configured string
     */
    public function getType(string $class, string $property, array $context = []): Type
    {
        // Declared as a class-string for the type metadata, although the point of this extractor is
        // that the name need not be one.
        /** @var class-string $wrapper */
        $wrapper = $this->className;

        return Type::collection(new ObjectType($wrapper), Type::int());
    }
}
