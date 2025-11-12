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
use Symfony\Component\TypeInfo\Type\CollectionType;

/**
 * Describes the operations required to materialize collection values.
 *
 * @template TKey of array-key
 * @template TValue
 */
interface CollectionFactoryInterface
{
    /**
     * Converts the provided iterable JSON structure to a PHP array.
     *
     * @param Type $valueType The type description for the collection values.
     *
     * @return array<TKey, TValue>|null
     */
    public function mapIterable(mixed $json, Type $valueType, MappingContext $context): ?array;

    /**
     * Builds a collection based on the specified collection type description.
     *
     * @param CollectionType $type The collection type metadata extracted from PHPStan/Psalm annotations.
     *
     * @return array<TKey, TValue>|object|null
     */
    public function fromCollectionType(CollectionType $type, mixed $json, MappingContext $context): mixed;
}
