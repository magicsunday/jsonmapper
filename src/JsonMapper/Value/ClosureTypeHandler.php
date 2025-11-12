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

    public function __construct(private readonly string $className, callable $converter)
    {
        $this->converter = $this->normalizeConverter($converter);
    }

    public function supports(Type $type, mixed $value): bool
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        return $type->getClassName() === $this->className;
    }

    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        if (!$this->supports($type, $value)) {
            throw new LogicException(sprintf('Handler does not support type %s.', $type::class));
        }

        return ($this->converter)($value, $context);
    }

    /**
     * @param callable(mixed):mixed|callable(mixed, MappingContext):mixed $converter
     */
    private function normalizeConverter(callable $converter): Closure
    {
        $closure    = $converter instanceof Closure ? $converter : Closure::fromCallable($converter);
        $reflection = new ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() >= 2) {
            return $closure;
        }

        return static fn (mixed $value, MappingContext $context): mixed => $closure($value);
    }
}
