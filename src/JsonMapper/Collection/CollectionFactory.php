<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Collection;

use ArrayAccess;
use Closure;
use DomainException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Traversable;

use function array_is_list;
use function get_debug_type;
use function get_object_vars;
use function is_a;
use function is_array;
use function is_object;
use function iterator_to_array;

/**
 * Creates collections and hydrates wrapping collection classes.
 *
 * @internal This is not a public extension point. The mapper builds it internally and offers no way
 *           to supply another, so it carries no backwards-compatibility guarantee.
 *
 * @phpstan-type CollectionWrappedType BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>
 */
final readonly class CollectionFactory
{
    /**
     * @param Closure(Type, mixed, MappingContext): mixed                                $convertValue The single value-conversion entry point, shared with the property path so an element is decided by the same null policy, null guard and union dispatch a property is.
     * @param Closure(class-string, array<array-key, mixed>|null, MappingContext):object $instantiator The wrapper instantiator; for the constructor lane it refuses an element list the constructor cannot receive, so a native error never escapes error collection.
     */
    public function __construct(
        private Closure $convertValue,
        private ClassResolver $classResolver,
        private Closure $instantiator,
    ) {
    }

    /**
     * Converts the provided iterable JSON structure to a PHP array.
     *
     * @param mixed          $json      Raw JSON data representing the collection to hydrate.
     * @param Type           $valueType Type descriptor for individual collection entries.
     * @param MappingContext $context   Active mapping context providing path and strictness information.
     *
     * @return array<array-key, mixed>|null Normalised collection data, or null when no collection was
     *                                      asked for. A recorded conversion failure yields an empty
     *                                      array, never null - consumers read null as an absence and
     *                                      hand it to a collection constructor, which rejects it
     *                                      natively.
     *
     * @throws MappingException When the value cannot be mapped.
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
            $context->throwOrRecord(
                new CollectionMappingException($context->getPath(), get_debug_type($json)),
            );

            // An empty collection, not null. Null is this method's "no collection was asked for"
            // sentinel - the nullable branch above - and every consumer reads it that way: the
            // property accessor rejects it for an array-typed property, and a wrapping collection
            // class receives it as the constructor argument. Handing it back for a RECORDED
            // failure therefore replaces a reported mapping error with a native one, which is
            // precisely what a run that promised a report must never do.
            return [];
        }

        $collection = [];

        // Dropping an element must not punch a hole into the keys: a payload that arrived as a
        // JSON array is declared as a list, and a gap would make it one no longer. Keys are only
        // carried over when the source is keyed to begin with.
        $sourceIsList = array_is_list($source);

        foreach ($source as $key => $value) {
            try {
                $converted = $context->withPathSegment((string) $key, fn (MappingContext $childContext): mixed => ($this->convertValue)($valueType, $value, $childContext));

                if ($sourceIsList) {
                    $collection[] = $converted;
                } else {
                    $collection[$key] = $converted;
                }
            } catch (MappingException $exception) {
                // An element that cannot be converted is dropped, not propagated: one invalid
                // entry must not discard its valid siblings, which is what lenient mode exists
                // for. The error is recorded here because the exception no longer travels up to
                // the caller that would have recorded it.
                //
                // Recorded from INSIDE the element's path segment. A record takes its path from
                // the context, and the context has already left the segment by the time this catch
                // runs - so recording here filed every element failure under the collection
                // instead of the element. The exception carried the correct path throughout, which
                // is exactly why the discrepancy stayed invisible.
                $context->withPathSegment(
                    (string) $key,
                    static function (MappingContext $elementContext) use ($exception): void {
                        $elementContext->recordException($exception);
                    },
                );

                // NOT throwOrRecord(): this site records BEFORE raising, and the helper does the
                // opposite. Routing it there loses the element's OWN record on an aborting run -
                // silently, since the caller still gets its exception, and a deeper record written
                // further inside the element can survive and make the report look complete. (The path is not the obstacle - withPathSegment() restores it in
                // a finally, so a throw from inside the closure propagates cleanly. What the
                // segment buys is that the record names the element rather than the collection.)
                //
                // The rethrow reaches the property loop above, which skips the failure because the
                // latest record was made from it. It still has to be limited to a run that aborts,
                // otherwise the caller loses the rejected element's valid siblings.
                if ($context->shouldAbortOnError()) {
                    throw $exception;
                }
            }
        }

        return $collection;
    }

    /**
     * Builds a collection based on the specified collection type description.
     *
     * @param CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>> $type    Resolved collection metadata from docblocks or attributes.
     * @param mixed                                                                    $json    Raw JSON payload containing the collection values.
     * @param MappingContext                                                           $context Mapping context controlling strict mode and error tracking.
     *
     * @return object|array<array-key, mixed>|null Instantiated collection wrapper or the normalised array values.
     *
     * @throws MappingException When the value cannot be mapped.
     */
    public function fromCollectionType(CollectionType $type, mixed $json, MappingContext $context): array|object|null
    {
        $wrappedType = $type->getWrappedType();
        $className   = null;

        if (($wrappedType instanceof WrappingTypeInterface) && ($wrappedType->getWrappedType() instanceof ObjectType)) {
            $className = $this->resolveWrappedClass($wrappedType->getWrappedType());

            // The wrapper name comes from type metadata, which can hold something that is no class
            // name, such as the source text of an intersection. That is a payload the declaration
            // cannot take and is reported at the property, as the object strategy does, unlike a
            // well formed name that resolves to nothing, which stays a configuration error. It is
            // judged before the elements are mapped, because a failure of an element or of the
            // payload shape would otherwise be recorded as well, and one rejected value is one record.
            if (!ClassResolver::isWellFormedName($className)) {
                throw new TypeMismatchException($context->getPath(), $className, get_debug_type($json));
            }
        }

        $collection = $this->mapIterable($json, $type->getCollectionValueType(), $context);

        if ($className !== null) {
            $resolvedClass = $this->classResolver->resolve($className, $json, $context);

            return $this->wrapElements($resolvedClass, $collection ?? [], $context);
        }

        return $collection;
    }

    /**
     * Fills a collection wrapper with the mapped elements, through the mechanism the wrapper supports.
     *
     * A wrapper that implements ArrayAccess and whose constructor takes no arguments is built empty
     * and written element by element through offsetSet, so a container keeping its elements in a
     * private storage property is filled without a list-accepting constructor. Any other wrapper -
     * one that is not ArrayAccess, or one whose constructor takes the elements - receives the whole
     * list as its constructor argument instead. The offsetSet lane is reserved for the wrapper that
     * CANNOT accept the elements any other way: a constructor that declares parameters is handed the
     * list, so a class that fills or validates through its constructor keeps doing so.
     *
     * Both collection lanes route through here - the property path via {@see fromCollectionType} and
     * the explicit-collection-class entry point - so the two cannot fill a wrapper differently. A
     * constructor that cannot receive the element list is refused through the MappingException
     * hierarchy rather than left to raise a native error that escapes error collection.
     *
     * @param class-string            $className Resolved collection class to build.
     * @param array<array-key, mixed> $elements  Mapped elements to place into the wrapper.
     * @param MappingContext          $context   Mapping context providing the path for a reported failure.
     *
     * @return object The constructed and filled collection wrapper
     *
     * @throws MappingException When the constructor lane cannot receive the mapped elements.
     */
    public function wrapElements(string $className, array $elements, MappingContext $context): object
    {
        $instantiator = $this->instantiator;

        if (is_a($className, ArrayAccess::class, true) && $this->constructorTakesNoArguments($className)) {
            /** @var ArrayAccess<array-key, mixed> $wrapper */
            $wrapper = $instantiator($className, null, $context);

            foreach ($elements as $key => $element) {
                $wrapper[$key] = $element;
            }

            return $wrapper;
        }

        // The instantiator refuses a constructor that cannot receive the element list before it calls
        // new, so a binding mismatch is reported through the MappingException hierarchy while a
        // TypeError raised inside the constructor body still propagates as itself - the same policy
        // the object-construction lane follows. The check lives there, not here, because it needs the
        // Type layer this one must not depend on.
        return $instantiator($className, $elements, $context);
    }

    /**
     * Reports whether the class's constructor accepts no arguments.
     *
     * The offsetSet lane constructs the wrapper empty, so it fits only a class whose constructor
     * cannot receive the elements at all - one that is absent or declares no parameters. A
     * constructor that declares any parameter, required or optional, is handed the elements instead,
     * so a wrapper that fills or validates through its constructor is not bypassed.
     *
     * @param class-string $className Class to inspect.
     *
     * @return bool TRUE when the constructor is absent or declares no parameters
     */
    private function constructorTakesNoArguments(string $className): bool
    {
        $constructor = (new ReflectionClass($className))->getConstructor();

        return (!$constructor instanceof ReflectionMethod) || ($constructor->getNumberOfParameters() === 0);
    }

    /**
     * Resolves the wrapped collection class name.
     *
     * @param ObjectType<class-string> $objectType
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
