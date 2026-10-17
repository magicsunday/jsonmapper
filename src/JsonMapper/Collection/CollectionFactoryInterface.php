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
use MagicSunday\JsonMapper\Exception\MappingException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Describes the operations required to materialize collection values.
 *
 * @internal This is not a public extension point. The mapper builds its own CollectionFactory and
 *           offers no way to supply another, so the interface carries no backwards-compatibility
 *           guarantee and may gain methods between releases.
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
     * @return array<TKey, TValue>|null Normalised array representation, or null when no collection was
     *                                  asked for. A recorded conversion failure yields an empty array,
     *                                  never null: consumers read null as an absence and hand it to a
     *                                  collection constructor, which rejects it natively.
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

    /**
     * Fills a collection wrapper with already-mapped elements, through the mechanism it supports.
     *
     * An ArrayAccess wrapper that constructs without arguments is built empty and written through
     * offsetSet; any other wrapper receives the elements as its constructor argument. Both collection
     * lanes route through this one method so they cannot fill a wrapper differently. A constructor
     * that cannot receive the element list is refused through the MappingException hierarchy rather
     * than left to raise a native error that escapes error collection.
     *
     * @param class-string        $className Resolved collection class to build.
     * @param array<TKey, TValue> $elements  Mapped elements to place into the wrapper.
     * @param MappingContext      $context   Mapping context providing the path for a reported failure.
     *
     * @return object The constructed and filled collection wrapper
     *
     * @throws MappingException When the constructor lane cannot receive the mapped elements.
     */
    public function wrapElements(string $className, array $elements, MappingContext $context): object;
}
