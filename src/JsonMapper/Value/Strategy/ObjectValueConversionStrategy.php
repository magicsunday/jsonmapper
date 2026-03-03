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

/**
 * Converts object values by delegating to the mapper callback.
 */
final readonly class ObjectValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Creates the strategy with the class resolver and mapper callback.
     *
     * @param ClassResolver                                      $classResolver Resolver used to select the concrete class to instantiate.
     * @param Closure(mixed, class-string, MappingContext):mixed $mapper        Callback responsible for mapping values into objects.
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
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        if (!$type instanceof ObjectType) {
            throw new LogicException('ObjectValueConversionStrategy requires an object type.');
        }

        $className     = $this->resolveClassName($type);
        $resolvedClass = $this->classResolver->resolve($className, $value, $context);

        if ($value !== null && !is_array($value) && !is_object($value) && !$context->shouldAllowScalarToObjectCasting()) {
            $exception = new TypeMismatchException($context->getPath(), $resolvedClass, get_debug_type($value));
            $context->recordException($exception);

            if ($context->isStrictMode()) {
                throw $exception;
            }
        }

        $mapper = $this->mapper;

        return $mapper($value, $resolvedClass, $context);
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
