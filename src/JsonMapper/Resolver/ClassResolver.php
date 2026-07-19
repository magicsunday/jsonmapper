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
use function array_map;
use function class_exists;
use function get_debug_type;
use function in_array;
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
     * Classes each resolver entry is permitted to return, keyed by the base class it handles. An
     * entry absent from this map is unrestricted.
     *
     * @var array<class-string, list<class-string>>
     */
    private array $allowedTargets = [];

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
     * @param class-string                                                            $className      Base class or interface the resolver handles.
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver       Callback returning a concrete class based on the JSON payload and optional mapping context.
     * @param list<string>|null                                                       $allowedTargets Classes the resolver may return. Null leaves it
     *                                                                                                unrestricted, which is the default for backwards
     *                                                                                                compatibility - see the note below.
     *
     * @phpstan-param class-string $className
     * @phpstan-param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver
     *
     * @throws DomainException When the base class or a listed target does not exist.
     */
    public function add(string $className, Closure $resolver, ?array $allowedTargets = null): void
    {
        $this->assertClassString($className);
        $this->classMap[$className] = $resolver;

        if ($allowedTargets === null) {
            unset($this->allowedTargets[$className]);

            return;
        }

        // Validated on registration rather than on resolution: a typo in the list would otherwise
        // narrow it silently, and the resolver would start refusing a class the consumer believes
        // it permitted - at request time, on a payload that looks fine.
        $this->allowedTargets[$className] = array_map(
            $this->assertClassString(...),
            $allowedTargets,
        );
    }

    /**
     * Resolves the class name for the provided JSON payload.
     *
     * @param class-string   $className Base class name configured in the resolver map.
     * @param mixed          $json      Raw JSON fragment inspected to determine the target class.
     * @param MappingContext $context   Mapping context passed to resolution callbacks when required.
     *
     * @return class-string Fully-qualified class name that should be instantiated for the payload.
     */
    public function resolve(string $className, mixed $json, MappingContext $context): string
    {
        if (!array_key_exists($className, $this->classMap)) {
            return $this->assertClassString($className);
        }

        $mapped = $this->classMap[$className];

        if (!$mapped instanceof Closure) {
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

        $this->assertAllowedTarget($className, $resolved);

        return $this->assertClassString($resolved);
    }

    /**
     * Verifies the resolved class against the allowlist registered for this entry.
     *
     * A resolver's input is the payload, so a consumer who returns a class name taken from it -
     * the naive discriminator - lets an attacker choose which class gets instantiated, with
     * constructor arguments that also come from the payload. class_exists(), which is all
     * assertClassString() can check, is satisfied by every object-injection gadget in the
     * autoloader. Being named on a list the consumer wrote is a different question, and the only
     * one that helps.
     *
     * Checked BEFORE assertClassString(), so an unlisted name is reported as not permitted rather
     * than as not a class - the two failures call for different responses.
     *
     * @param class-string $className Base class whose entry produced the value.
     * @param string       $resolved  Class name the resolver returned.
     *
     * @throws DomainException When an allowlist exists and does not name the resolved class.
     */
    private function assertAllowedTarget(string $className, string $resolved): void
    {
        if (!array_key_exists($className, $this->allowedTargets)) {
            return;
        }

        if (in_array($resolved, $this->allowedTargets[$className], true)) {
            return;
        }

        throw new DomainException(
            sprintf(
                'Class resolver for %s returned %s, which its allowed-target list does not permit.',
                $className,
                $resolved,
            ),
        );
    }

    /**
     * Executes a resolver callback while adapting the invocation to its declared arity.
     *
     * @param Closure(mixed):class-string|Closure(mixed, MappingContext):class-string $resolver User-provided resolver that determines the concrete class; the parameter list defines whether the mapping context can be injected.
     * @param mixed                                                                   $json     JSON fragment forwarded to the resolver so it can inspect discriminator values.
     * @param MappingContext                                                          $context  Context object passed when supported to supply additional mapping metadata.
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
