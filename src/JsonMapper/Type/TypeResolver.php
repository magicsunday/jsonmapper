<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Type;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Resolves property types using Symfony's PropertyInfo component.
 */
final class TypeResolver
{
    private const CACHE_KEY_PREFIX = 'jsonmapper.property_type.';

    private BuiltinType $defaultType;

    public function __construct(
        private readonly PropertyInfoExtractorInterface $extractor,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
        $this->defaultType = new BuiltinType(TypeIdentifier::STRING);
    }

    /**
     * Resolves the declared type for the provided property.
     *
     * @param class-string $className
     * @param string       $propertyName
     *
     * @return Type
     */
    public function resolve(string $className, string $propertyName): Type
    {
        $cached = $this->getCachedType($className, $propertyName);

        if ($cached instanceof Type) {
            return $cached;
        }

        $type = $this->extractor->getType($className, $propertyName);

        if ($type instanceof UnionType) {
            $type = $type->getTypes()[0];
        }

        if ($type === null) {
            $type = $this->resolveFromReflection($className, $propertyName);
        }

        $resolved = $type ?? $this->defaultType;

        $this->storeCachedType($className, $propertyName, $resolved);

        return $resolved;
    }

    /**
     * Returns a cached type if available.
     *
     * @param class-string $className
     * @param string       $propertyName
     *
     * @return Type|null
     */
    private function getCachedType(string $className, string $propertyName): ?Type
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $item = $this->cache->getItem($this->buildCacheKey($className, $propertyName));
        } catch (CacheInvalidArgumentException) {
            return null;
        }

        if (!$item->isHit()) {
            return null;
        }

        $cached = $item->get();

        return $cached instanceof Type ? $cached : null;
    }

    /**
     * Stores the resolved type in cache when possible.
     *
     * @param class-string $className
     * @param string       $propertyName
     * @param Type         $type
     */
    private function storeCachedType(string $className, string $propertyName, Type $type): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $item = $this->cache->getItem($this->buildCacheKey($className, $propertyName));
            $item->set($type);
            $this->cache->save($item);
        } catch (CacheInvalidArgumentException) {
            // Intentionally ignored: caching failures must not block type resolution.
        }
    }

    /**
     * Builds a cache key that fulfils PSR-6 requirements.
     *
     * @param class-string $className
     * @param string       $propertyName
     *
     * @return string
     */
    private function buildCacheKey(string $className, string $propertyName): string
    {
        return self::CACHE_KEY_PREFIX . strtr($className, '\\', '_') . '.' . $propertyName;
    }

    /**
     * @param class-string $className
     */
    private function resolveFromReflection(string $className, string $propertyName): ?Type
    {
        try {
            $property = new ReflectionProperty($className, $propertyName);
        } catch (ReflectionException) {
            return null;
        }

        $reflectionType = $property->getType();

        if ($reflectionType instanceof ReflectionNamedType) {
            return $this->createTypeFromNamedReflection($reflectionType);
        }

        if ($reflectionType instanceof ReflectionUnionType) {
            $allowsNull = false;
            $primary    = null;

            foreach ($reflectionType->getTypes() as $innerType) {
                if (!$innerType instanceof ReflectionNamedType) {
                    continue;
                }

                if ($innerType->getName() === 'null') {
                    $allowsNull = true;

                    continue;
                }

                $primary ??= $innerType;
            }

            if ($primary instanceof ReflectionNamedType) {
                return $this->createTypeFromNamedReflection($primary, $allowsNull || $primary->allowsNull());
            }
        }

        return null;
    }

    private function createTypeFromNamedReflection(ReflectionNamedType $type, ?bool $nullable = null): ?Type
    {
        $name = $type->getName();

        if ($type->isBuiltin()) {
            $identifier = TypeIdentifier::tryFrom($name);

            if ($identifier === null) {
                return null;
            }

            $resolved = Type::builtin($identifier);
        } else {
            $resolved = Type::object($name);
        }

        $allowsNull = $nullable ?? $type->allowsNull();

        if ($allowsNull) {
            return Type::nullable($resolved);
        }

        return $resolved;
    }
}
