<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value;

use Closure;
use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use ReflectionFunction;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function sprintf;

/**
 * Decorates a closure so that it can be used as a type handler.
 */
final class ClosureTypeHandler implements TypeHandlerInterface
{
    private Closure $converter;

    /**
     * @param non-empty-string $className                      Type alias handled by the converter.
     * @param callable(mixed):mixed|callable(mixed, MappingContext):mixed $converter Callable receiving the mapped value and
     *                                                                               optionally the mapping context.
     */
    public function __construct(private readonly string $className, callable $converter)
    {
        $this->converter = $this->normalizeConverter($converter);
    }

    /**
     * Determines whether the given type is supported.
     *
     * The handler accepts only object types that match the configured class name; other type instances are rejected.
     */
    public function supports(Type $type, mixed $value): bool
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        return $type->getClassName() === $this->className;
    }

    /**
     * Converts the provided value to the supported type using the configured converter.
     *
     * @throws LogicException When the supplied type is not supported by this handler.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        if (!$this->supports($type, $value)) {
            throw new LogicException(sprintf('Handler does not support type %s.', $type::class));
        }

        return ($this->converter)($value, $context);
    }

    /**
     * Normalizes a user-supplied callable into the internal converter signature.
     *
     * The converter may accept either one argument (the value) or two arguments (value and mapping context). Single-argument
     * callables are wrapped so that the mapping context can be provided when invoking the handler.
     *
     * @param callable(mixed):mixed|callable(mixed, MappingContext):mixed $converter
     */
    private function normalizeConverter(callable $converter): Closure
    {
        $closure    = $converter instanceof Closure ? $converter : Closure::fromCallable($converter);
        $reflection = new ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() >= 2) {
            return $closure;
        }

        // Ensure the converter always accepts the mapping context even if the original callable does not need it.
        return static fn (mixed $value, MappingContext $context): mixed => $closure($value);
    }
}
