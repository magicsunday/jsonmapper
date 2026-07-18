<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Type;

use DateInterval;
use DateTimeInterface;
use MagicSunday\JsonMapper\Type\TypeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

use function array_key_exists;
use function array_values;
use function str_replace;

/**
 * @internal
 */
final class TypeResolverTest extends TestCase
{
    #[Test]
    public function itCachesResolvedTypes(): void
    {
        $typeExtractor = new StubPropertyTypeExtractor(new BuiltinType(TypeIdentifier::INT));
        $extractor     = new PropertyInfoExtractor([], [$typeExtractor]);
        $cache         = new InMemoryCachePool();
        $resolver      = new TypeResolver($extractor, $cache);

        $first  = $resolver->resolve(TypeResolverFixture::class, 'baz');
        $second = $resolver->resolve(TypeResolverFixture::class, 'baz');

        self::assertSame($first, $second);
        self::assertTrue($first->isIdentifiedBy(TypeIdentifier::INT));
        self::assertSame(1, $typeExtractor->callCount);
    }

    #[Test]
    public function itNormalizesUnionTypesBeforeCaching(): void
    {
        $typeExtractor = new StubPropertyTypeExtractor(
            new UnionType(
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::STRING),
            ),
        );
        $extractor = new PropertyInfoExtractor([], [$typeExtractor]);
        $cache     = new InMemoryCachePool();
        $resolver  = new TypeResolver($extractor, $cache);

        $type = $resolver->resolve(TypeResolverFixture::class, 'qux');

        self::assertTrue($type->isIdentifiedBy(TypeIdentifier::INT));
        self::assertSame($type, $resolver->resolve(TypeResolverFixture::class, 'qux'));
        self::assertSame(1, $typeExtractor->callCount);
    }

    #[Test]
    public function itFallsBackToNullableStringType(): void
    {
        $typeExtractor = new StubPropertyTypeExtractor(null);
        $extractor     = new PropertyInfoExtractor([], [$typeExtractor]);
        $resolver      = new TypeResolver($extractor, new InMemoryCachePool());

        $type = $resolver->resolve(TypeResolverFixture::class, 'name');

        self::assertInstanceOf(NullableType::class, $type);
        self::assertTrue($type->isIdentifiedBy(TypeIdentifier::STRING));
        self::assertTrue($type->isNullable());
        self::assertSame(1, $typeExtractor->callCount);
    }

    #[Test]
    public function itIgnoresCacheEntriesWrittenByAnEarlierSchemaVersion(): void
    {
        // A persistent pool warmed by a previous release still holds types resolved under the
        // old semantics. Without a schema token in the key those entries would be served
        // verbatim and the new behaviour would never reach an upgraded deployment.
        $typeExtractor = new StubPropertyTypeExtractor(null);
        $extractor     = new PropertyInfoExtractor([], [$typeExtractor]);
        $cache         = new InMemoryCachePool();

        // Mirrors the key the previous release wrote: prefix + FQCN with backslashes replaced.
        $legacyKey = 'jsonmapper.property_type.'
            . str_replace('\\', '_', TypeResolverFixture::class)
            . '.name';
        $legacyItem = $cache->getItem($legacyKey);
        $legacyItem->set(new BuiltinType(TypeIdentifier::STRING));
        $cache->save($legacyItem);

        $resolver = new TypeResolver($extractor, $cache);
        $type     = $resolver->resolve(TypeResolverFixture::class, 'name');

        // The stale non-nullable entry must not win; the current fallback is nullable.
        self::assertTrue($type->isNullable());
        self::assertSame(1, $typeExtractor->callCount);

        // Control: without this, a drifted key scheme would make the priming above miss for the
        // wrong reason, the resolver would fall through to a fresh resolve, and both assertions
        // would still pass — leaving the test permanently green while asserting nothing. Proving
        // the resolver writes under the versioned key anchors the miss to the schema token.
        $versionedKey = 'jsonmapper.property_type.v2.'
            . str_replace('\\', '_', TypeResolverFixture::class)
            . '.name';

        self::assertTrue(
            $cache->getItem($versionedKey)->isHit(),
            'The resolver must store the freshly resolved type under the schema-versioned key.',
        );
    }
}

/**
 * Lightweight in-memory cache pool implementation for testing purposes only.
 */
final class InMemoryCachePool implements CacheItemPoolInterface
{
    /**
     * @var array<string, InMemoryCacheItem>
     */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!array_key_exists($key, $this->items)) {
            return new InMemoryCacheItem($key);
        }

        return $this->items[$key];
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->items) && $this->items[$key]->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = $item instanceof InMemoryCacheItem
            ? $item
            : new InMemoryCacheItem($item->getKey(), $item->get(), $item->isHit());

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

/**
 * @internal
 */
final class InMemoryCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value = null,
        private bool $hit = false,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit   = true;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        return $this;
    }
}

/**
 * Simple type extractor stub that records calls and returns configured types.
 */
final class StubPropertyTypeExtractor implements PropertyTypeExtractorInterface
{
    /**
     * @var array<int, Type|null>
     */
    private array $results;

    private int $index = 0;

    public int $callCount = 0;

    public function __construct(?Type ...$results)
    {
        $this->results = array_values($results);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function getType(string $class, string $property, array $context = []): ?Type
    {
        ++$this->callCount;

        if (!array_key_exists($this->index, $this->results)) {
            return null;
        }

        return $this->results[$this->index++];
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function getTypes(string $class, string $property, array $context = []): null
    {
        return null;
    }
}

/**
 * @internal
 */
final class TypeResolverFixture
{
}
