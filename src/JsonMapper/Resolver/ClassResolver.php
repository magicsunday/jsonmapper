<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Resolver;

use Closure;
use DomainException;
use MagicSunday\JsonMapper\Context\MappingContext;
use ReflectionFunction;

use function array_key_exists;
use function class_exists;
use function get_debug_type;
use function interface_exists;
use function is_string;
use function sprintf;

/**
 * Resolves class names using the configured class map.
 */
final class ClassResolver
{
    /**
     * @var array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string>
     *
     * @phpstan-var array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string>
     */
    private array $classMap;

    /**
     * @param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap Map of base class names to explicit targets or resolver callbacks.
     *
     * @phpstan-param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap
     */
    public function __construct(array $classMap = [])
    {
        $this->classMap = $this->validateClassMap($classMap);
    }

    /**
     * Adds a custom resolution rule.
     *
     * @param class-string $className Base class or interface the resolver handles.
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver Callback returning a concrete class based on the JSON payload and optional mapping context.
     *
     * @phpstan-param class-string $className
     * @phpstan-param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver
     */
    public function add(string $className, Closure $resolver): void
    {
        $this->assertClassString($className);
        $this->classMap[$className] = $resolver;
    }

    /**
     * Resolves the class name for the provided JSON payload.
     *
     * @param class-string $className Base class name configured in the resolver map.
     * @param mixed $json Raw JSON fragment inspected to determine the target class.
     * @param MappingContext $context Mapping context passed to resolution callbacks when required.
     *
     * @return class-string Fully-qualified class name that should be instantiated for the payload.
     */
    public function resolve(string $className, mixed $json, MappingContext $context): string
    {
        if (!array_key_exists($className, $this->classMap)) {
            return $this->assertClassString($className);
        }

        $mapped = $this->classMap[$className];

        if (!($mapped instanceof Closure)) {
            return $this->assertClassString($mapped);
        }

        $resolved = $this->invokeResolver($mapped, $json, $context);

        if (!is_string($resolved)) {
            throw new DomainException(
                sprintf(
                    'Class resolver for %s must return a class-string, %s given.',
                    $className,
                    get_debug_type($resolved),
                ),
            );
        }

        return $this->assertClassString($resolved);
    }

    /**
     * Executes a resolver callback while adapting the invocation to its declared arity.
     *
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver User-provided resolver that determines the concrete class; the parameter list defines whether the mapping context can be injected.
     * @param mixed $json JSON fragment forwarded to the resolver so it can inspect discriminator values.
     * @param MappingContext $context Context object passed when supported to supply additional mapping metadata.
     *
     * @return mixed Raw resolver result that will subsequently be validated as a class-string.
     */
    private function invokeResolver(Closure $resolver, mixed $json, MappingContext $context): mixed
    {
        $reflection = new ReflectionFunction($resolver);

        // Inspect the closure signature to decide whether to pass the mapping context argument.
        if ($reflection->getNumberOfParameters() >= 2) {
            return $resolver($json, $context);
        }

        return $resolver($json);
    }

    /**
     * Validates the configured class map entries eagerly to fail fast on invalid definitions.
     *
     * @param array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> $classMap Map of discriminated base classes to either target classes or resolver closures; each entry is asserted for existence.
     *
     * @return array<class-string, class-string|Closure(mixed):class-string|Closure(mixed, MappingContext):class-string> Sanitised map ready for runtime lookups.
     *
     * @throws DomainException When a class key or mapped class name is empty or cannot be autoloaded.
     */
    private function validateClassMap(array $classMap): array
    {
        foreach ($classMap as $sourceClass => $mapping) {
            $this->assertClassString($sourceClass);

            if ($mapping instanceof Closure) {
                continue;
            }

            $this->assertClassString($mapping);
        }

        return $classMap;
    }

    /**
     * Ensures the provided class reference is non-empty and refers to a loadable class or interface.
     *
     * @param string $className Candidate class-string; invalid or unknown names trigger a DomainException.
     *
     * @return class-string Validated class or interface name safe to return to callers.
     *
     * @throws DomainException When the name is empty or cannot be resolved by the autoloader.
     */
    private function assertClassString(string $className): string
    {
        if ($className === '') {
            throw new DomainException('Resolved class name must not be empty.');
        }

        if (!class_exists($className) && !interface_exists($className)) {
            throw new DomainException(sprintf('Resolved class %s does not exist.', $className));
        }

        /** @var class-string $className */
        return $className;
    }
}
