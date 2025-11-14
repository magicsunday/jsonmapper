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
        self::assertContainsOnlyInstancesOf(Article::class, $articles);

        $article = $articles[0];
        self::assertInstanceOf(NestedTagCollection::class, $article->tags);
        self::assertCount(2, $article->tags);
        self::assertContainsOnlyInstancesOf(TagCollection::class, $article->tags);

        $firstRow = $article->tags[0];
        self::assertInstanceOf(TagCollection::class, $firstRow);
        self::assertCount(1, $firstRow);
        self::assertContainsOnlyInstancesOf(Tag::class, $firstRow);
        self::assertSame('php', $firstRow[0]->name);

        $secondRow = $article->tags[1];
        self::assertInstanceOf(TagCollection::class, $secondRow);
        self::assertCount(1, $secondRow);
        self::assertContainsOnlyInstancesOf(Tag::class, $secondRow);
        self::assertSame('json', $secondRow[0]->name);
    }
}
