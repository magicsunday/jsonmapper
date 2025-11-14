<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\Docs\QuickStart\Article;
use MagicSunday\Test\Fixtures\Docs\QuickStart\ArticleCollection;
use MagicSunday\Test\Fixtures\Docs\QuickStart\Comment;
use MagicSunday\Test\Fixtures\Docs\QuickStart\CommentCollection;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DocsQuickStartTest extends TestCase
{
    #[Test]
    public function itMapsTheReadmeQuickStartExample(): void
    {
        $mapper = $this->getJsonMapper();

        $single = $this->getJsonAsObject('{"title":"Hello world","comments":[{"message":"First!"}]}');
        $article = $mapper->map($single, Article::class);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Hello world', $article->title);
        self::assertInstanceOf(CommentCollection::class, $article->comments);
        self::assertCount(1, $article->comments);
        self::assertContainsOnlyInstancesOf(Comment::class, $article->comments);
        self::assertSame('First!', $article->comments[0]->message);

        $list = $this->getJsonAsObject('[{"title":"Hello world","comments":[{"message":"First!"}]},{"title":"Second","comments":[]}]');
        $articles = $mapper->map($list, Article::class, ArticleCollection::class);

        self::assertInstanceOf(ArticleCollection::class, $articles);
        self::assertCount(2, $articles);
        self::assertContainsOnlyInstancesOf(Article::class, $articles);
        self::assertSame('Hello world', $articles[0]->title);
        self::assertSame('Second', $articles[1]->title);
        self::assertInstanceOf(CommentCollection::class, $articles[0]->comments);
        self::assertCount(1, $articles[0]->comments);
        self::assertInstanceOf(CommentCollection::class, $articles[1]->comments);
        self::assertCount(0, $articles[1]->comments);
    }
}
