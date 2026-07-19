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
use ArrayIterator;
use ArrayObject;
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
        // Deliberately answers on the class shape alone, without resolving the annotation. A
        // predicate that raised for a container declaring no element type would decide the
        // question by throwing, and no strategy registered after this one could ever be asked.
        // Whether the container can actually be filled is a conversion concern.
        return ($type instanceof CollectionType) || $this->isCollectionClass($type);
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
     * @return CollectionType<GenericType<ObjectType<mixed>>> Collection type naming the declared class
     */
    private function resolveFromClassAnnotation(Type $type): CollectionType
    {
        assert($type instanceof ObjectType);

        $className = $type->getClassName();

        // supports() established this already; repeating it costs a tenth of a microsecond and
        // keeps the class-string guarantee at the point of use rather than across two calls.
        assert(class_exists($className));

        // A container that never says what it holds cannot be filled, and letting it fall through
        // hands the raw array to the property accessor, which rejects it with an exception from
        // Symfony naming neither the annotation nor the fix. The resolver owns that guidance so
        // the two entry points cannot drift apart.
        $annotated = $this->docBlockTypeResolver->resolveOrFail($className);
        $wrapped   = $annotated->getWrappedType();

        assert($wrapped instanceof GenericType);

        return Type::collection(
            Type::generic(Type::object($className), ...$wrapped->getVariableTypes())
        );
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
     * @param Type $type Type metadata describing the target property.
     *
     * @return bool TRUE when the type names a traversable container without own properties
     */
    private function isCollectionClass(Type $type): bool
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        $className = $type->getClassName();

        if (($className === '') || !class_exists($className)) {
            return false;
        }

        // A subclass of one of the array containers is a collection by construction - its storage
        // is inherited, so properties it declares are helpers rather than mapped state. Judging it
        // by its own properties would refuse a perfectly ordinary collection that keeps a counter.
        if (is_a($className, ArrayObject::class, true) || is_a($className, ArrayIterator::class, true)) {
            return true;
        }

        if (!is_a($className, Traversable::class, true) && !is_a($className, ArrayAccess::class, true)) {
            return false;
        }

        // Anything else is judged by whether it declares state of its own: a data object that
        // merely implements IteratorAggregate keeps its properties and must not be hydrated from
        // the payload's elements.
        return (new ReflectionClass($className))->getProperties() === [];
    }
}
