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
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Report\MappingResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function method_exists;
use function preg_match_all;
use function sprintf;

/**
 * Reads the documentation and checks that the API it names actually exists.
 *
 * A test that transcribes a documented snippet by hand proves nothing about the document: the
 * transcription is what runs, and the markdown could say anything. These checks take the method
 * names out of the documentation itself, so a rename in either direction fails CI.
 *
 * @internal
 */
final class DocsApiReferenceTest extends TestCase
{
    private const string DOCS_PATH = __DIR__ . '/../../docs/';

    /**
     * Documentation files and the class whose API they describe.
     *
     * @return array<string, array{string, class-string, string}>
     */
    public static function documentedConfigurationApiProvider(): array
    {
        return [
            'API reference withers' => [
                'API.md',
                JsonMapperConfiguration::class,
                '/`(with[A-Za-z]+)\(/',
            ],
        ];
    }

    /**
     * @param string       $file      Documentation file relative to the docs directory.
     * @param class-string $className Class the documented methods must exist on.
     * @param string       $pattern   Pattern capturing the documented method names.
     */
    #[Test]
    #[DataProvider('documentedConfigurationApiProvider')]
    public function itOnlyDocumentsMethodsThatExist(string $file, string $className, string $pattern): void
    {
        $documentation = file_get_contents(self::DOCS_PATH . $file);

        self::assertIsString($documentation, sprintf('Documentation file %s must be readable.', $file));

        preg_match_all($pattern, $documentation, $matches);

        $documented = $matches[1];

        self::assertNotSame([], $documented, sprintf('No method names found in %s - has the format changed?', $file));

        foreach ($documented as $method) {
            self::assertTrue(
                method_exists($className, $method),
                sprintf('%s documents %s::%s(), which does not exist.', $file, $className, $method),
            );
        }
    }

    #[Test]
    public function itDocumentsTheResultAccessorsThatExist(): void
    {
        $recipe = file_get_contents(self::DOCS_PATH . 'recipes/error-handling.md');

        self::assertIsString($recipe);

        preg_match_all('/\$result->([A-Za-z]+)\(/', $recipe, $matches);

        $documented = $matches[1];

        self::assertNotSame([], $documented, 'The recipe must demonstrate at least one result accessor.');

        foreach ($documented as $method) {
            self::assertTrue(
                method_exists(MappingResult::class, $method),
                sprintf('The error-handling recipe calls %s::%s(), which does not exist.', MappingResult::class, $method),
            );
        }
    }

    #[Test]
    public function itDocumentsMapperMethodsThatExist(): void
    {
        $reference = file_get_contents(self::DOCS_PATH . 'API.md');

        self::assertIsString($reference);

        preg_match_all('/`(map|mapWithReport|addType|createWithDefaults)\(/', $reference, $matches);

        $documented = $matches[1];

        self::assertNotSame([], $documented, 'The API reference must name the mapper entry points.');

        foreach ($documented as $method) {
            self::assertTrue(
                method_exists(JsonMapper::class, $method),
                sprintf('The API reference documents %s::%s(), which does not exist.', JsonMapper::class, $method),
            );
        }
    }
}
