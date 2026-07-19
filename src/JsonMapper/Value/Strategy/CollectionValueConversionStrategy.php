<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use ArrayAccess;
use MagicSunday\JsonMapper\Collection\CollectionDocBlockTypeResolver;
use MagicSunday\JsonMapper\Collection\CollectionFactoryInterface;
use MagicSunday\JsonMapper\Context\MappingContext;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Traversable;

use function assert;
use function class_exists;
use function is_a;

/**
 * Converts collection values using the configured factory.
 */
final readonly class CollectionValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Creates the strategy with the provided collection factory.
     *
     * @param CollectionFactoryInterface<array-key, mixed> $collectionFactory    Factory responsible for instantiating collections during conversion.
     * @param CollectionDocBlockTypeResolver               $docBlockTypeResolver Resolver reading the element type from a collection class annotation.
     */
    public function __construct(
        private CollectionFactoryInterface $collectionFactory,
        private CollectionDocBlockTypeResolver $docBlockTypeResolver = new CollectionDocBlockTypeResolver(),
    ) {
    }

    /**
     * Determines whether the supplied type represents a collection.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type is a collection type.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        return ($type instanceof CollectionType) || ($this->resolveFromClassAnnotation($type) instanceof CollectionType);
    }

    /**
     * Converts the JSON value into a collection instance.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Collection created by the factory based on the type metadata.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        $collectionType = $type instanceof CollectionType ? $type : $this->resolveFromClassAnnotation($type);

        assert($collectionType instanceof CollectionType);

        return $this->collectionFactory->fromCollectionType($collectionType, $value, $context);
    }

    /**
     * Builds a collection type for a property declared with a collection class rather than a
     * generic docblock.
     *
     * A property typed `TagCollection` carries no element information of its own; the class says
     * what it holds through its own "extends" annotation. Without this the property resolves to a
     * plain object, the payload stays a raw array, and the property accessor rejects it with a
     * foreign exception the mapper never gets to report.
     *
     * The annotation describes the PARENT - `ArrayObject<int, Tag>` - so its element types are
     * re-wrapped around the declared class. Handing the parent type on unchanged would instantiate
     * an ArrayObject where the property demands a TagCollection.
     *
     * @param Type $type Type metadata describing the target property.
     *
     * @return CollectionType<GenericType<ObjectType<mixed>>>|null Collection type naming the declared class, or null when the type is not a collection class
     */
    private function resolveFromClassAnnotation(Type $type): ?CollectionType
    {
        if (!$type instanceof ObjectType) {
            return null;
        }

        $className = $type->getClassName();

        if (($className === '') || !class_exists($className)) {
            return null;
        }

        if (!$this->isCollectionContainer($className)) {
            return null;
        }

        return $this->buildCollectionType($className);
    }

    /**
     * Determines whether the class is a container rather than a data object that happens to be
     * iterable.
     *
     * Declaring an element type is not enough on its own. A perfectly ordinary DTO may implement
     * IteratorAggregate and annotate what it yields, and routing that to the collection factory
     * would build it from the payload's elements and silently drop every property it declares -
     * the payload arrives, the object is of the right class, and its fields are empty.
     *
     * A collection wrapper holds its contents in the container it inherits from and declares no
     * state of its own, so its own properties are the discriminator.
     *
     * @param class-string $className Class backing the target type.
     *
     * @return bool TRUE when the class is a traversable container without own properties
     */
    private function isCollectionContainer(string $className): bool
    {
        if (!is_a($className, Traversable::class, true) && !is_a($className, ArrayAccess::class, true)) {
            return false;
        }

        return (new ReflectionClass($className))->getProperties() === [];
    }

    /**
     * Reads the collection class annotation and re-wraps it around the class itself.
     *
     * @param class-string $className Collection class to inspect.
     *
     * @return CollectionType<GenericType<ObjectType<mixed>>>|null Collection type naming the class, or null when it declares no element type
     */
    private function buildCollectionType(string $className): ?CollectionType
    {
        $annotated = $this->docBlockTypeResolver->resolve($className);

        if (!$annotated instanceof CollectionType) {
            return null;
        }

        $wrapped = $annotated->getWrappedType();

        if (!$wrapped instanceof GenericType) {
            return null;
        }

        return Type::collection(
            Type::generic(Type::object($className), ...$wrapped->getVariableTypes())
        );
    }
}
