<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use FilesystemIterator;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Report\MappingReport;
use MagicSunday\JsonMapper\Report\MappingResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function file_get_contents;
use function method_exists;
use function preg_match_all;
use function realpath;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * The documentation is only useful while it still describes the real API. A renamed or removed
 * method leaves the samples silently broken - a reader copies them and gets a fatal error, and
 * nothing in the suite notices, because no test reads the markdown.
 *
 * This checks the method calls the samples make on a variable whose type they fix by convention.
 * That is narrower than "the samples are correct": a call written in another shape is invisible
 * here, which is what {@see itKeepsGuardingAtLeastAsManyCallsAsItDoesToday()} exists to catch.
 * It found MappingResult::getMappedValue(), which never existed.
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
        'result' => MappingResult::class,
        'mapper' => JsonMapper::class,
        'error'  => MappingError::class,
        'config' => JsonMapperConfiguration::class,
    ];

    /**
     * Methods whose return type the samples then call into. The docs reach MappingReport only as
     * `$result->getReport()->hasErrors()`, so without following the chain those calls - and the
     * whole MappingReport surface - would go unchecked while the test still looked thorough.
     *
     * @var array<class-string, array<string, class-string>>
     */
    private const array CHAINED_RETURNS = [
        MappingResult::class => [
            'getReport' => MappingReport::class,
        ],
    ];

    /**
     * Static entry points the samples use instead of a variable, with the class they return.
     *
     * @var array<string, class-string>
     */
    private const array STATIC_ENTRY_POINTS = [
        'JsonMapperConfiguration' => JsonMapperConfiguration::class,
        'JsonMapper'              => JsonMapper::class,
    ];

    /**
     * Number of documented calls currently covered. A drop means a sample changed into a shape
     * this test no longer recognises, which would leave it green while guarding nothing.
     */
    private const int GUARDED_CALL_FLOOR = 35;

    /**
     * Every committed markdown file, so a newly added recipe is covered without further wiring.
     *
     * @return array<string, array{string}>
     */
    public static function documentationFileProvider(): array
    {
        $cases = [];

        foreach (self::collectDocumentationFiles() as $file) {
            $cases[self::toRelativePath($file)] = [$file];
        }

        return $cases;
    }

    /**
     * Collects the committed markdown files.
     *
     * Walked recursively rather than globbed: glob() does not treat ** as "any depth", so a
     * recipe nested one level deeper would be skipped without a word.
     *
     * @return list<string> Absolute paths of the documentation files
     */
    private static function collectDocumentationFiles(): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::repositoryRoot(), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if ($entry->getExtension() !== 'md') {
                continue;
            }

            $path = $entry->getPathname();

            // Build output and dependencies are not ours to document.
            if (str_contains($path, '/.build/') || str_contains($path, '/vendor/')) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * Returns the repository root.
     *
     * @return string Absolute path to the repository root
     */
    private static function repositoryRoot(): string
    {
        $root = realpath(__DIR__ . '/../..');

        return $root === false ? __DIR__ . '/../..' : $root;
    }

    /**
     * Strips the repository root so failure messages name the file the way a reader would.
     *
     * @param string $file Absolute path of a documentation file
     *
     * @return string Path relative to the repository root
     */
    private static function toRelativePath(string $file): string
    {
        return str_replace(self::repositoryRoot() . '/', '', $file);
    }

    /**
     * Returns the methods a documentation file calls on the conventionally typed variables.
     *
     * @param string $documentation Raw markdown contents
     *
     * @return list<array{class-string, string}> Pairs of class name and called method
     */
    private static function extractDocumentedCalls(string $documentation): array
    {
        $calls = [];

        foreach (self::VARIABLE_TYPES as $variable => $className) {
            // Captures the whole call chain, so `$result->getReport()->getErrors()` yields both
            // links instead of only the first.
            preg_match_all(
                sprintf('/\$%s((?:->[A-Za-z_][A-Za-z0-9_]*\()+)/', $variable),
                $documentation,
                $matches
            );

            foreach ($matches[1] as $chain) {
                $calls = [...$calls, ...self::walkChain($className, $chain)];
            }
        }

        foreach (self::STATIC_ENTRY_POINTS as $literal => $className) {
            preg_match_all(
                sprintf('/%s::((?:[A-Za-z_][A-Za-z0-9_]*\()(?:[^\n]*?->[A-Za-z_][A-Za-z0-9_]*\()*)/', $literal),
                $documentation,
                $matches
            );

            foreach ($matches[1] as $chain) {
                // A fluent configuration returns its own type, so every link in
                // `JsonMapperConfiguration::strict()->withErrorCollection(true)` belongs to it.
                preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\(/', $chain, $methods);

                foreach ($methods[1] as $method) {
                    $calls[] = [$className, $method];
                }
            }
        }

        return $calls;
    }

    /**
     * Resolves each link of a call chain to the class it is invoked on.
     *
     * @param class-string $className Type the chain starts on
     * @param string       $chain     Raw chain text, e.g. `->getReport()->hasErrors(`
     *
     * @return list<array{class-string, string}> Pairs of class name and called method
     */
    private static function walkChain(string $className, string $chain): array
    {
        preg_match_all('/->([A-Za-z_][A-Za-z0-9_]*)\(/', $chain, $matches);

        $calls   = [];
        $current = $className;

        foreach ($matches[1] as $method) {
            $calls[] = [$current, $method];

            // Once a link's return type is unknown the rest of the chain cannot be attributed,
            // so it is left unchecked rather than blamed on the wrong class.
            $next = self::CHAINED_RETURNS[$current][$method] ?? null;

            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return $calls;
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

        foreach (self::extractDocumentedCalls($documentation) as [$className, $method]) {
            self::assertTrue(
                method_exists($className, $method),
                sprintf(
                    '%s documents %s::%s(), which does not exist.',
                    self::toRelativePath($file),
                    $className,
                    $method
                )
            );
        }
    }

    #[Test]
    public function itKeepsGuardingAtLeastAsManyCallsAsItDoesToday(): void
    {
        // Several documentation files match nothing at all, so their rows above assert only that
        // the file is readable. That is tolerable per file, but it means the guard can quietly
        // degrade to a no-op: rename $result to $mappingResult across the docs, or move the
        // samples to a static call form, and every row stays green while nothing is verified.
        // This floor makes that shrinkage a failure instead of a silence.
        $guarded = 0;

        foreach (self::collectDocumentationFiles() as $file) {
            $documentation = file_get_contents($file);

            if ($documentation === false) {
                continue;
            }

            $guarded += count(self::extractDocumentedCalls($documentation));
        }

        self::assertGreaterThanOrEqual(
            self::GUARDED_CALL_FLOOR,
            $guarded,
            $guarded . ' guarded. Fewer documented calls are guarded than before - a sample changed into a shape this '
            . 'test no longer recognises. Extend the recognised shapes rather than lowering the floor.',
        );
    }
}
