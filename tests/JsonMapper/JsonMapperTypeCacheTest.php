<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\Test\Fixtures\Cache\InMemoryCachePool;
use MagicSunday\Test\Fixtures\Docs\QuickStart\Article;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class JsonMapperTypeCacheTest extends TestCase
{
    #[Test]
    public function itCachesResolvedTypesWhenConfigured(): void
    {
        $cache = new InMemoryCachePool();
        $extractor = new PropertyInfoExtractor([new ReflectionExtractor()], [new PhpDocExtractor()]);
        $mapper = new JsonMapper(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            new CamelCasePropertyNameConverter(),
            [],
            $cache,
        );

        $json = $this->getJsonAsObject('{"title":"Cache","comments":[{"message":"hit"}]}');

        $mapper->map($json, Article::class);

        $initialSaveCount = $cache->getSaveCalls();
        self::assertGreaterThan(0, $initialSaveCount);
        self::assertSame(0, $cache->getHitCount());

        $mapper->map($json, Article::class);

        self::assertSame($initialSaveCount, $cache->getSaveCalls());
        self::assertSame($initialSaveCount, $cache->getHitCount());
    }
}
