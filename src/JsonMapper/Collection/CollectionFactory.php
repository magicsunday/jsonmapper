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
use DomainException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Value\ValueConverter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Traversable;

use function get_debug_type;
use function get_object_vars;
use function is_array;
use function is_object;
use function iterator_to_array;

/**
 * Creates collections and hydrates wrapping collection classes.
 */
final readonly class CollectionFactory implements CollectionFactoryInterface
{
    /**
     * @param Closure(class-string, array<array-key, mixed>|null):object $instantiator
     */
    public function __construct(
        private ValueConverter $valueConverter,
        private ClassResolver $classResolver,
        private Closure $instantiator,
    ) {
    }

    /**
     * Converts the provided iterable JSON structure to a PHP array.
     *
     * @return array<array-key, mixed>|null
     */
    public function mapIterable(mixed $json, Type $valueType, MappingContext $context): ?array
    {
        if ($json === null) {
            if ($context->shouldTreatNullAsEmptyCollection()) {
                return [];
            }

            return null;
        }

        $source = match (true) {
            $json instanceof Traversable => iterator_to_array($json),
            is_array($json)              => $json,
            is_object($json)             => get_object_vars($json),
            default                      => null,
        };

        if (!is_array($source)) {
            $exception = new CollectionMappingException($context->getPath(), get_debug_type($json));
            $context->recordException($exception);

            if ($context->isStrictMode()) {
                throw $exception;
            }

            return null;
        }

        $collection = [];

        foreach ($source as $key => $value) {
            $collection[$key] = $context->withPathSegment((string) $key, fn (MappingContext $childContext): mixed => $this->valueConverter->convert($value, $valueType, $childContext));
        }

        return $collection;
    }

    /**
     * Builds a collection based on the specified collection type description.
     *
     * @return array<array-key, mixed>|object|null
     */
    public function fromCollectionType(CollectionType $type, mixed $json, MappingContext $context): mixed
    {
        $collection = $this->mapIterable($json, $type->getCollectionValueType(), $context);

        $wrappedType = $type->getWrappedType();

        if (($wrappedType instanceof WrappingTypeInterface) && ($wrappedType->getWrappedType() instanceof ObjectType)) {
            $objectType    = $wrappedType->getWrappedType();
            $className     = $this->resolveWrappedClass($objectType);
            $resolvedClass = $this->classResolver->resolve($className, $json, $context);

            $instantiator = $this->instantiator;

            return $instantiator($resolvedClass, $collection);
        }

        return $collection;
    }

    /**
     * Resolves the wrapped collection class name.
     *
     * @return class-string
     *
     * @throws DomainException
     */
    private function resolveWrappedClass(ObjectType $objectType): string
    {
        $className = $objectType->getClassName();

        if ($className === '') {
            throw new DomainException('Collection type must define a class-string for the wrapped object.');
        }

        /** @var class-string $className */
        return $className;
    }
}
