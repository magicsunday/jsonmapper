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
use MagicSunday\JsonMapper\Context\MappingContext;
use ReflectionFunction;

use function array_key_exists;

/**
 * Stores custom conversion handlers keyed by class name.
 */
final class CustomTypeRegistry
{
    /**
     * @var array<string, Closure(mixed, MappingContext):mixed>
     */
    private array $converters = [];

    /**
     * Registers the converter for the provided class name.
     *
     * @param callable(mixed):mixed|callable(mixed, MappingContext):mixed $converter
     */
    public function register(string $className, callable $converter): void
    {
        $this->converters[$className] = $this->normalizeConverter($converter);
    }

    /**
     * Returns TRUE if a converter for the class exists.
     */
    public function has(string $className): bool
    {
        return array_key_exists($className, $this->converters);
    }

    /**
     * Executes the converter for the class.
     */
    public function convert(string $className, mixed $value, MappingContext $context): mixed
    {
        return $this->converters[$className]($value, $context);
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
