<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Collection;

use Closure;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Value\ValueConverter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

use function is_array;
use function is_object;

/**
 * Creates collections and hydrates wrapping collection classes.
 */
final class CollectionFactory
{
    /**
     * @param Closure(class-string, array<mixed>|null):object $instantiator
     */
    public function __construct(
        private readonly ValueConverter $valueConverter,
        private readonly ClassResolver $classResolver,
        private readonly Closure $instantiator,
    ) {
    }

    /**
     * Converts the provided iterable JSON structure to a PHP array.
     */
    public function mapIterable(array|object|null $json, Type $valueType, MappingContext $context): ?array
    {
        if ($json === null) {
            return null;
        }

        if (!is_array($json) && !is_object($json)) {
            return null;
        }

        $collection = [];

        foreach ($json as $key => $value) {
            $collection[$key] = $context->withPathSegment((string) $key, function (MappingContext $childContext) use ($valueType, $value): mixed {
                return $this->valueConverter->convert($value, $valueType, $childContext);
            });
        }

        return $collection;
    }

    /**
     * Builds a collection based on the specified collection type description.
     */
    public function fromCollectionType(CollectionType $type, array|object|null $json, MappingContext $context): mixed
    {
        $collection = $this->mapIterable($json, $type->getCollectionValueType(), $context);

        $wrappedType = $type->getWrappedType();

        if (($wrappedType instanceof WrappingTypeInterface) && ($wrappedType->getWrappedType() instanceof ObjectType)) {
            $objectType = $wrappedType->getWrappedType();
            $className  = $this->classResolver->resolve($objectType->getClassName(), $json, $context);

            $instantiator = $this->instantiator;

            return $instantiator($className, $collection);
        }

        return $collection;
    }
}
