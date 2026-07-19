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
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Report\MappingReport;
use MagicSunday\JsonMapper\Report\MappingResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function glob;
use function method_exists;
use function preg_match_all;
use function sprintf;
use function str_replace;

/**
 * The documentation is only useful while it still describes the real API. A renamed or removed
 * method leaves the samples silently broken - a reader copies them and gets a fatal error, and
 * nothing in the suite notices, because no test reads the markdown.
 *
 * This walks the committed samples and checks every method call made on a variable whose type the
 * samples fix by convention. It caught `MappingResult::getMappedValue()`, which never existed.
 *
 * @internal
 */
final class DocsApiReferenceTest extends TestCase
{
    /**
     * Variable names the documentation uses consistently, mapped to the class they hold. A sample
     * that introduces a new variable for one of these types has to be added here, otherwise its
     * calls go unchecked.
     *
     * @var array<string, class-string>
     */
    private const array VARIABLE_TYPES = [
        'result'        => MappingResult::class,
        'report'        => MappingReport::class,
        'mapper'        => JsonMapper::class,
        'configuration' => JsonMapperConfiguration::class,
        'error'         => MappingError::class,
    ];

    /**
     * Every committed markdown file, so a newly added recipe is covered without further wiring.
     *
     * @return array<string, array{string}>
     */
    public static function documentationFileProvider(): array
    {
        $files = [];

        foreach (['/../../docs/**/*.md', '/../../docs/*.md', '/../../*.md'] as $pattern) {
            $matches = glob(__DIR__ . $pattern);

            if ($matches !== false) {
                $files = [...$files, ...$matches];
            }
        }

        $cases = [];

        foreach ($files as $file) {
            $cases[str_replace(__DIR__ . '/../../', '', $file)] = [$file];
        }

        return $cases;
    }

    /**
     * @param string $file Absolute path of the markdown file under inspection.
     */
    #[Test]
    #[DataProvider('documentationFileProvider')]
    public function itOnlyDocumentsMethodsThatExist(string $file): void
    {
        $documentation = file_get_contents($file);

        self::assertIsString($documentation, sprintf('%s could not be read.', $file));

        foreach (self::VARIABLE_TYPES as $variable => $className) {
            preg_match_all(
                sprintf('/\$%s->([A-Za-z_][A-Za-z0-9_]*)\(/', $variable),
                $documentation,
                $matches
            );

            foreach ($matches[1] as $method) {
                self::assertTrue(
                    method_exists($className, $method),
                    sprintf(
                        '%s documents %s::%s(), which does not exist.',
                        str_replace(__DIR__ . '/../../', '', $file),
                        $className,
                        $method
                    )
                );
            }
        }
    }
}
