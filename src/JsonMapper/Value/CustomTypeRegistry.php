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
use ReflectionMethod;

use function array_key_exists;
use function is_array;

/**
 * Stores custom conversion handlers keyed by class name.
 */
final class CustomTypeRegistry
{
    /**
     * @var array<class-string, callable(mixed, MappingContext):mixed>
     */
    private array $converters = [];

    /**
     * Registers the converter for the provided class name.
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
     *
     * @return callable(mixed, MappingContext):mixed
     */
    private function normalizeConverter(callable $converter): callable
    {
        if ($converter instanceof Closure) {
            $reflection = new ReflectionFunction($converter);
        } elseif (is_array($converter)) {
            $reflection = new ReflectionMethod($converter[0], $converter[1]);
        } else {
            $reflection = new ReflectionFunction(Closure::fromCallable($converter));
        }

        if ($reflection->getNumberOfParameters() >= 2) {
            return $converter;
        }

        return static function (mixed $value, MappingContext $context) use ($converter): mixed {
            return $converter($value);
        };
    }
}
