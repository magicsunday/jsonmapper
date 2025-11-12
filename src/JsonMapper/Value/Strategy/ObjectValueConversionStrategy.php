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
     * @param Closure(mixed, class-string, MappingContext):mixed $mapper
     */
    public function __construct(
        private ClassResolver $classResolver,
        private Closure $mapper,
    ) {
    }

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $type instanceof ObjectType;
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        if (!($type instanceof ObjectType)) {
            throw new LogicException('ObjectValueConversionStrategy requires an object type.');
        }

        $className     = $this->resolveClassName($type);
        $resolvedClass = $this->classResolver->resolve($className, $value, $context);

        if (($value !== null) && !is_array($value) && !is_object($value)) {
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
     * @return class-string
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
