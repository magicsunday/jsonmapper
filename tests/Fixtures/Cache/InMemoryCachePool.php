<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class InMemoryCachePool implements CacheItemPoolInterface
{
    /**
     * @var array<string, InMemoryCacheItem>
     */
    private array $items = [];

    private int $saveCalls = 0;

    private int $hitCount = 0;

    /**
     * @return InMemoryCacheItem
     */
    public function getItem(string $key): CacheItemInterface
    {
        if (isset($this->items[$key])) {
            $item = $this->items[$key];

            if ($item->isHit()) {
                ++$this->hitCount;
            }

            return $item;
        }

        $item              = new InMemoryCacheItem($key);
        $this->items[$key] = $item;

        return $item;
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, InMemoryCacheItem>
     */
    public function getItems(array $keys = []): iterable
    {
        if ($keys === []) {
            return $this->items;
        }

        /** @var array<string, InMemoryCacheItem> $result */
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }

        return $result;
    }

    public function hasItem(string $key): bool
    {
        if (!isset($this->items[$key])) {
            return false;
        }

        return $this->items[$key]->isHit();
    }

    public function clear(): bool
    {
        $this->items     = [];
        $this->saveCalls = 0;
        $this->hitCount  = 0;

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

        ++$this->saveCalls;

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

    public function getSaveCalls(): int
    {
        return $this->saveCalls;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }
}
