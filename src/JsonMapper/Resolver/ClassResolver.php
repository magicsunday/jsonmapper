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
use ReflectionClass;
use ReflectionFunction;

use function array_key_exists;
use function array_map;
use function class_exists;
use function get_debug_type;
use function in_array;
use function interface_exists;
use function is_string;
use function ltrim;
use function sprintf;
use function strtolower;

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
     * Stored as normalised COMPARISON forms rather than class-strings - lower-cased and without a
     * leading backslash - because that is what makes two spellings of the same class compare equal.
     * They are never used as class names, only compared against one.
     *
     * @var array<class-string, list<string>>
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

        // Everything is validated BEFORE anything is stored, so a rejected list cannot leave the
        // resolver registered without it. Storing first made this fail OPEN: a typo in the list
        // threw, and the entry it was meant to restrict stayed live and unrestricted - the exact
        // surface the allowlist exists to close, reached by getting the guard slightly wrong. A
        // caller that logs and continues past configuration errors would never learn of it.
        //
        // Validated on registration rather than on resolution for the same reason a typo must not
        // pass: otherwise it narrows the list silently and the resolver starts refusing a class the
        // consumer believes it permitted, at request time, on a payload that looks fine.
        $validatedTargets = $allowedTargets === null
            ? null
            : $this->validateAllowedTargets($className, $allowedTargets);

        $this->classMap[$className] = $resolver;

        if ($validatedTargets === null) {
            // An entry is replaced wholesale, so a list written for the previous closure must not
            // outlive it.
            unset($this->allowedTargets[$className]);

            return;
        }

        $this->allowedTargets[$className] = $validatedTargets;
    }

    /**
     * Validates an allowed-target list and returns it in the form the check compares against.
     *
     * @param class-string $className      Base class the list belongs to.
     * @param list<string> $allowedTargets Classes the resolver may return.
     *
     * @return list<string> Normalised list
     *
     * @throws DomainException When the list is empty, or names something that is not a class.
     */
    private function validateAllowedTargets(string $className, array $allowedTargets): array
    {
        if ($allowedTargets === []) {
            // Not treated as "nothing is permitted": no caller wants a resolver that can never
            // succeed, and an empty list realistically arrives from a config lookup that found
            // nothing or a filter that removed everything. Left alone it would be the extreme case
            // of the silent narrowing this validation exists to catch, surfacing only at request
            // time.
            throw new DomainException(
                sprintf(
                    'Allowed-target list for %s is empty; omit it to leave the resolver unrestricted.',
                    $className,
                ),
            );
        }

        return array_map(
            function (string $target) use ($className): string {
                // Instantiability, not mere existence. assertClassString() accepts an interface,
                // and class_exists() additionally accepts an abstract class and an enum - none of
                // which a resolver can return for instantiation, so listing one is always a
                // mistake. Left to fail later it becomes a native Error from makeInstance(),
                // outside the error-collection contract, on a payload that looks fine.
                if (!class_exists($target) || !(new ReflectionClass($target))->isInstantiable()) {
                    throw new DomainException(
                        sprintf(
                            'Allowed target %s for %s is not an instantiable class.',
                            $target,
                            $className,
                        ),
                    );
                }

                return $this->normalizeClassName($target);
            },
            $allowedTargets,
        );
    }

    /**
     * Reduces a class name to the form two spellings of the same class share.
     *
     * PHP class names are case-insensitive and tolerate a leading backslash, so '\Circle',
     * 'circle' and Circle::class all instantiate the same class. A strict comparison is therefore
     * narrower than PHP's own resolution - which fails safe, but rejects payloads that are in fact
     * permitted, at request time. Comparing normalised forms makes the check agree with what
     * actually gets instantiated.
     *
     * @param string $className Class name in any accepted spelling.
     *
     * @return string Comparable form
     */
    private function normalizeClassName(string $className): string
    {
        return strtolower(ltrim($className, '\\'));
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

        if (in_array($this->normalizeClassName($resolved), $this->allowedTargets[$className], true)) {
            return;
        }

        // The refused name is NOT echoed. A resolver's return value can be a raw payload string -
        // that is the whole hazard the allowlist addresses - and this message escapes as a
        // DomainException, outside the mapping report, into whatever generic handler the consumer
        // wrote. Reflecting it there would put an attacker-chosen string into a response body. The
        // base class is enough to find the entry; the payload is in the request log.
        throw new DomainException(
            sprintf(
                'Class resolver for %s returned a class its allowed-target list does not permit.',
                $className,
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
