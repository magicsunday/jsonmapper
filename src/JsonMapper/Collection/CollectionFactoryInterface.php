<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Collection;

use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Describes the operations required to materialize collection values.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-type CollectionWrappedType BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>
 */
interface CollectionFactoryInterface
{
    /**
     * Converts the provided iterable JSON structure to a PHP array.
     *
     * @param mixed          $json      Raw JSON data representing the iterable input to normalise.
     * @param Type           $valueType Type description for the collection values.
     * @param MappingContext $context   Active mapping context carrying strictness and error reporting configuration.
     *
     * @return array<TKey, TValue>|null Normalised array representation or null when conversion fails.
     */
    public function mapIterable(mixed $json, Type $valueType, MappingContext $context): ?array;

    /**
     * Builds a collection based on the specified collection type description.
     *
     * @param CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>> $type    Resolved collection metadata from PHPDoc or attributes.
     * @param mixed                                                                    $json    Raw JSON payload containing the collection values.
     * @param MappingContext                                                           $context Mapping context controlling strict mode and error recording.
     *
     * @return array<TKey, TValue>|object|null Instantiated collection wrapper or the normalised array values.
     */
    public function fromCollectionType(CollectionType $type, mixed $json, MappingContext $context): mixed;
}
