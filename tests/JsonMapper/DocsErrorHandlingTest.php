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

        self::assertCount(1, $errors);
        self::assertInstanceOf(MissingConstructorArgumentException::class, $errors[0]->getException());
    }

    #[Test]
    public function itRunsTheEmptyStringAsNullOption(): void
    {
        // Pins the wither name the API reference documents; the reference named a method that
        // does not exist.
        $config = JsonMapperConfiguration::lenient()->withEmptyStringAsNull(true);

        self::assertTrue($config->shouldTreatEmptyStringAsNull());
    }
}
