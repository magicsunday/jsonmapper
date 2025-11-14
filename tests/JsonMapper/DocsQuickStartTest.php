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

        $single  = $this->getJsonAsObject('{"title":"Hello world","comments":[{"message":"First!"}]}');
        $article = $mapper->map($single, Article::class);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Hello world', $article->title);

        /** @var CommentCollection<int, Comment> $comments */
        $comments = $article->comments;
        self::assertCount(1, $comments);
        self::assertTrue($comments->offsetExists(0));

        $firstComment = $comments[0];
        self::assertInstanceOf(Comment::class, $firstComment);
        self::assertSame('First!', $firstComment->message);

        $list     = $this->getJsonAsObject('[{"title":"Hello world","comments":[{"message":"First!"}]},{"title":"Second","comments":[]}]');
        $articles = $mapper->map($list, Article::class, ArticleCollection::class);

        self::assertInstanceOf(ArticleCollection::class, $articles);
        self::assertCount(2, $articles);
        self::assertTrue($articles->offsetExists(0));
        self::assertTrue($articles->offsetExists(1));

        $firstArticle = $articles[0];
        self::assertInstanceOf(Article::class, $firstArticle);
        self::assertSame('Hello world', $firstArticle->title);

        /** @var CommentCollection<int, Comment> $firstArticleComments */
        $firstArticleComments = $firstArticle->comments;
        self::assertCount(1, $firstArticleComments);
        self::assertTrue($firstArticleComments->offsetExists(0));

        $firstArticleComment = $firstArticleComments[0];
        self::assertInstanceOf(Comment::class, $firstArticleComment);
        self::assertSame('First!', $firstArticleComment->message);

        $secondArticle = $articles[1];
        self::assertInstanceOf(Article::class, $secondArticle);
        self::assertSame('Second', $secondArticle->title);

        /** @var CommentCollection<int, Comment> $secondArticleComments */
        $secondArticleComments = $secondArticle->comments;
        self::assertCount(0, $secondArticleComments);
    }
}
