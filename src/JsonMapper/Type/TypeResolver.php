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
    private const string CACHE_KEY_PREFIX = 'jsonmapper.property_type.';

    /**
     * @var BuiltinType<TypeIdentifier::STRING>
     */
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

        if ($type === null) {
            $type = $this->resolveFromReflection($className, $propertyName);
        }

        $resolved = $type instanceof Type ? $this->normalizeType($type) : $this->defaultType;

        $this->storeCachedType($className, $propertyName, $resolved);

        return $resolved;
    }

    private function normalizeType(Type $type): Type
    {
        if ($type instanceof UnionType) {
            return $this->normalizeUnionType($type);
        }

        return $type;
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
        if (!$this->cache instanceof CacheItemPoolInterface) {
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
        if (!$this->cache instanceof CacheItemPoolInterface) {
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
            $types      = [];
            $allowsNull = false;

            foreach ($reflectionType->getTypes() as $innerType) {
                if (!$innerType instanceof ReflectionNamedType) {
                    continue;
                }

                if ($innerType->getName() === 'null') {
                    $allowsNull = true;

                    continue;
                }

                $resolved = $this->createTypeFromNamedReflection($innerType);

                if ($resolved instanceof Type) {
                    $types[] = $resolved;
                }
            }

            if ($types === []) {
                return $allowsNull ? Type::nullable($this->defaultType) : null;
            }

            $union = count($types) === 1 ? $types[0] : Type::union(...$types);

            if ($allowsNull) {
                return Type::nullable($union);
            }

            return $union;
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

    /**
     * @param UnionType<Type> $type
     */
    private function normalizeUnionType(UnionType $type): Type
    {
        $types      = [];
        $allowsNull = false;

        foreach ($type->getTypes() as $inner) {
            if ($this->isNullType($inner)) {
                $allowsNull = true;

                continue;
            }

            $types[] = $this->normalizeType($inner);
        }

        if ($types === []) {
            return $allowsNull ? Type::nullable($this->defaultType) : $this->defaultType;
        }

        $union = count($types) === 1 ? $types[0] : Type::union(...$types);

        if ($allowsNull) {
            return Type::nullable($union);
        }

        return $union;
    }

    private function isNullType(Type $type): bool
    {
        return $type instanceof BuiltinType && $type->getTypeIdentifier() === TypeIdentifier::NULL;
    }
}
