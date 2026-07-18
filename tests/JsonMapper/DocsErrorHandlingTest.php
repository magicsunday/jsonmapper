<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Fixtures\Docs\ErrorHandling\Article;
use MagicSunday\Test\Fixtures\Docs\ErrorHandling\ImmutableArticle;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Executes the error-handling recipe as written. The sibling recipes already have such a test;
 * this one did not, which is how two examples came to call methods that do not exist.
 *
 * @internal
 */
final class DocsErrorHandlingTest extends TestCase
{
    #[Test]
    public function itRunsTheStrictModeWithErrorCollectionExample(): void
    {
        $config = JsonMapperConfiguration::strict()->withErrorCollection(true);

        $payload = $this->getJsonAsObject('{"title":"Strict example"}');

        $result = $this->getJsonMapper()->mapWithReport($payload, Article::class, configuration: $config);

        // The method the recipe calls has to exist and return the mapped object.
        $article = $result->getValue();

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Strict example', $article->title);
    }

    #[Test]
    public function itRunsTheReportContractExample(): void
    {
        $result = $this->getJsonMapper()->mapWithReport([], ImmutableArticle::class);

        self::assertNull($result->getValue());

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One unbuildable root object produces exactly one record.');
        self::assertInstanceOf(MissingConstructorArgumentException::class, $errors[0]->getException());
    }

    #[Test]
    public function itRunsTheReportContractExampleForARejectedValue(): void
    {
        // The recipe states that a failure appears in the report as a MappingError carrying path,
        // message and exception. The payload is an int rather than an array on purpose: an array
        // would additionally trigger the builtin coercion the recipe flags as issue 63, and this
        // test is about the record's shape, not about that gap.
        $result = $this->getJsonMapper()->mapWithReport(
            ['title' => 42],
            Article::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One rejected value produces exactly one record.');
        self::assertSame('$.title', $errors[0]->getPath());
        self::assertNotSame('', $errors[0]->getMessage());
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }
}
