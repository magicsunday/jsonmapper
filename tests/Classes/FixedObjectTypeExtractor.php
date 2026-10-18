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
     * @param string $className Name the object type reports, whether or not it is a class
     */
    public function __construct(private string $className)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getTypes(string $class, string $property, array $context = []): null
    {
        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getType(string $class, string $property, array $context = []): Type
    {
        return Type::nullable(Type::object($this->className));
    }
}
