<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use Closure;
use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function get_debug_type;
use function is_array;
use function is_object;
use function preg_match;

/**
 * Converts object values by delegating to the mapper callback.
 *
 * @internal This is not a public extension point. Register conversions through
 *           {@see \MagicSunday\JsonMapper\Value\TypeHandlerInterface} via JsonMapper::addTypeHandler().
 */
final readonly class ObjectValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * One segment of a class name, which may use the bytes above the ASCII set.
     */
    private const string NAME_SEGMENT = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    /**
     * The shape of a class name, optionally with a leading separator.
     *
     * The runtime name of an anonymous class is a class name followed by the marker for anonymous
     * classes, a NUL byte and the place it was declared, and such a class can be instantiated, so
     * that suffix is let through. The pattern is anchored at the very end, so a name that merely
     * ends in a line break does not pass for well formed.
     */
    private const string CLASS_NAME_PATTERN = '/^\\\\?' . self::NAME_SEGMENT . '(?:\\\\' . self::NAME_SEGMENT . ')*(?:@anonymous\x00.*)?$/sD';

    /**
     * Creates the strategy with the class resolver and mapper callback.
     *
     * @param ClassResolver                                                    $classResolver Resolver used to select the concrete class to instantiate.
     * @param Closure(mixed, class-string, MappingContext, class-string):mixed $mapper        Callback responsible for mapping values into objects. It receives the resolved class and, last, the declared one.
     */
    public function __construct(
        private ClassResolver $classResolver,
        private Closure $mapper,
    ) {
    }

    /**
     * Determines whether the metadata describes an object type.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type represents an object.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        return $type instanceof ObjectType;
    }

    /**
     * Delegates conversion to the mapper for the resolved class.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Value returned by the mapper callback.
     *
     * @throws LogicException
     * @throws TypeMismatchException When the value does not match the declared type.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        if (!$type instanceof ObjectType) {
            throw new LogicException('ObjectValueConversionStrategy requires an object type.');
        }

        $className = $this->resolveClassName($type);

        // A name that is no class name at all (for example the source text of an intersection)
        // cannot be resolved. It is a payload that does not fit and is reported at the property,
        // unlike a well-formed name that resolves to nothing, which stays a configuration error.
        if (preg_match(self::CLASS_NAME_PATTERN, $className) !== 1) {
            throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
        }

        $resolvedClass = $this->classResolver->resolve($className, $value, $context);

        if (
            ($value !== null)
            && !is_array($value)
            && !is_object($value)
            && !$context->shouldAllowScalarToObjectCasting()
        ) {
            // A scalar carries no property values, so it cannot populate an object. Throwing is
            // the single recording path here: the caller records it once and leaves the property
            // alone. Recording and then continuing would hand the scalar to the mapper, which
            // either builds a meaningless empty instance or - for a class with required
            // constructor arguments - rejects it and records the same mismatch a second time.
            //
            // The mismatch names the declared class, which is metadata. The resolved class may have
            // been derived from the payload, and a consumer may place the expected type in a response.
            throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
        }

        $mapper = $this->mapper;

        return $mapper($value, $resolvedClass, $context, $className);
    }

    /**
     * Resolves the class name from the provided object type.
     *
     * @param ObjectType<class-string> $type Object type metadata describing the target property.
     *
     * @return class-string Concrete class name extracted from the metadata.
     *
     * @throws LogicException
     */
    private function resolveClassName(ObjectType $type): string
    {
        $className = $type->getClassName();

        if ($className === '') {
            throw new LogicException('Object type must define a class-string.');
        }

        /** @var class-string $className */
        return $className;
    }
}
