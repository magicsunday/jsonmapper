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
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Converts object values by delegating to the mapper callback.
 */
final class ObjectValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * @param callable(mixed, class-string, MappingContext):mixed $mapper
     */
    public function __construct(
        private readonly ClassResolver $classResolver,
        private readonly Closure $mapper,
    ) {
    }

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $type instanceof ObjectType;
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        \assert($type instanceof ObjectType);

        $className = $this->classResolver->resolve($type->getClassName(), $value, $context);

        $mapper = $this->mapper;

        return $mapper($value, $className, $context);
    }
}
