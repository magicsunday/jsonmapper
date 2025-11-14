<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\Docs\NestedCollections\Article;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\ArticleCollection;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\NestedTagCollection;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Tag;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\TagCollection;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DocsNestedCollectionsTest extends TestCase
{
    #[Test]
    public function itMapsTheNestedCollectionsRecipe(): void
    {
        $mapper = $this->getJsonMapper();

        $json = $this->getJsonAsObject('[
            {
                "tags": [
                    [{"name": "php"}],
                    [{"name": "json"}]
                ]
            }
        ]');

        $articles = $mapper->map($json, Article::class, ArticleCollection::class);

        self::assertInstanceOf(ArticleCollection::class, $articles);
        self::assertCount(1, $articles);
        self::assertTrue($articles->offsetExists(0));

        $article = $articles[0];
        self::assertInstanceOf(Article::class, $article);

        /** @var NestedTagCollection<int, TagCollection<int, Tag>> $tags */
        $tags = $article->tags;
        self::assertCount(2, $tags);
        self::assertContainsOnlyInstancesOf(TagCollection::class, $tags);

        self::assertTrue($tags->offsetExists(0));
        $firstRow = $tags[0];
        self::assertInstanceOf(TagCollection::class, $firstRow);
        self::assertCount(1, $firstRow);
        self::assertContainsOnlyInstancesOf(Tag::class, $firstRow);
        self::assertTrue($firstRow->offsetExists(0));

        $firstTag = $firstRow[0];
        self::assertInstanceOf(Tag::class, $firstTag);
        self::assertSame('php', $firstTag->name);

        self::assertTrue($tags->offsetExists(1));
        $secondRow = $tags[1];
        self::assertInstanceOf(TagCollection::class, $secondRow);
        self::assertCount(1, $secondRow);
        self::assertContainsOnlyInstancesOf(Tag::class, $secondRow);
        self::assertTrue($secondRow->offsetExists(0));

        $secondTag = $secondRow[0];
        self::assertInstanceOf(Tag::class, $secondTag);
        self::assertSame('json', $secondTag->name);
    }
}
