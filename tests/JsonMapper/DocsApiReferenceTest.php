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

use function array_keys;
use function array_map;
use function array_push;
use function file_get_contents;
use function is_dir;
use function ksort;
use function method_exists;
use function preg_match_all;
use function realpath;
use function sprintf;
use function str_replace;

/**
 * The documentation is only useful while it still describes the real API. A renamed or removed
 * method leaves the samples silently broken - a reader copies them and gets a fatal error, and
 * nothing in the suite notices, because no test reads the markdown.
 *
 * This resolves the method calls the samples make, following a chain through declared return
 * types so `$result->getReport()->hasErrors()` checks both links. It is narrower than "the
 * samples are correct": a call written in a shape not listed below is invisible here, which is
 * what the per-file expectations guard against. It found MappingResult::getMappedValue(), which
 * never existed.
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
     * Class names the samples call statically instead of through a variable.
     *
     * @var array<string, class-string>
     */
    private const array STATIC_ENTRY_POINTS = [
        'JsonMapperConfiguration' => JsonMapperConfiguration::class,
        'JsonMapper'              => JsonMapper::class,
    ];

    /**
     * Return types of the methods a sample chains through. Anything absent ends the walk, so an
     * unattributable link is left unchecked rather than blamed on the wrong class - a wrong
     * attribution that happened to name an existing method would pass and read as coverage.
     *
     * @var array<class-string, array<string, class-string>>
     */
    private const array CHAINED_RETURNS = [
        MappingResult::class => [
            'getReport' => MappingReport::class,
        ],
        JsonMapperConfiguration::class => [
            'strict'                         => JsonMapperConfiguration::class,
            'lenient'                        => JsonMapperConfiguration::class,
            'withErrorCollection'            => JsonMapperConfiguration::class,
            'withIgnoreUnknownProperties'    => JsonMapperConfiguration::class,
            'withTreatNullAsEmptyCollection' => JsonMapperConfiguration::class,
            'withStrictMode'                 => JsonMapperConfiguration::class,
        ],
    ];

    /**
     * The calls each documentation file currently contributes, in the order they are extracted.
     *
     * Named calls rather than a count, because the failure has to be readable. A bare number
     * fails with "4 does not match 12" and leaves a maintainer who just edited that file with a
     * ready-made story and nothing contradicting it - the cheapest fix is to lower the number,
     * and the guard quietly becomes a rubber stamp. With the calls spelled out, a shape
     * regression shows up as named entries disappearing from the diff, while genuinely adding a
     * sample stays a one-line change.
     *
     * Per file rather than one repository-wide total: a single sum cannot detect the degradation
     * it exists to catch, because one file losing its recognised shape is masked by another
     * gaining calls.
     *
     * @var array<string, list<string>>
     */
    private const array GUARDED_CALLS_PER_FILE = [
        'README.md' => [
            JsonMapper::class . '::map',
            JsonMapper::class . '::map',
            JsonMapper::class . '::map',
            JsonMapper::class . '::createWithDefaults',
            JsonMapper::class . '::createWithDefaults',
        ],
        'docs/API.md' => [
            JsonMapperConfiguration::class . '::lenient',
            JsonMapperConfiguration::class . '::strict',
            JsonMapperConfiguration::class . '::fromArray',
            JsonMapperConfiguration::class . '::fromContext',
            JsonMapper::class . '::createWithDefaults',
            JsonMapper::class . '::createWithDefaults',
            JsonMapper::class . '::addTypeHandler',
        ],
        // Prose only - it documents a name converter, not a call on one of the types above.
        'docs/recipes/custom-name-converter.md' => [],
        'docs/recipes/error-handling.md'        => [
            MappingResult::class . '::getValue',
            MappingResult::class . '::getValue',
            MappingResult::class . '::getReport',
            MappingReport::class . '::getErrors',
            MappingResult::class . '::getValue',
            MappingResult::class . '::getReport',
            MappingReport::class . '::getErrorCount',
            MappingResult::class . '::getReport',
            MappingReport::class . '::hasErrors',
            MappingResult::class . '::getReport',
            MappingReport::class . '::getErrors',
            JsonMapper::class . '::mapWithReport',
            JsonMapper::class . '::mapWithReport',
            JsonMapper::class . '::mapWithReport',
            JsonMapper::class . '::mapWithReport',
            MappingError::class . '::getPath',
            MappingError::class . '::getMessage',
            MappingError::class . '::getException',
            MappingError::class . '::getPath',
            MappingError::class . '::getMessage',
            JsonMapperConfiguration::class . '::strict',
            JsonMapperConfiguration::class . '::withErrorCollection',
            JsonMapperConfiguration::class . '::strict',
            JsonMapperConfiguration::class . '::lenient',
        ],
        'docs/recipes/manual-instantiation.md' => [
            JsonMapper::class . '::map',
            JsonMapper::class . '::createWithDefaults',
        ],
        'docs/recipes/mapping-with-enums.md' => [
            JsonMapper::class . '::map',
            JsonMapperConfiguration::class . '::strict',
        ],
        'docs/recipes/nested-collections.md' => [
            JsonMapper::class . '::map',
        ],
        'docs/recipes/performance.md' => [
            JsonMapper::class . '::createWithDefaults',
        ],
        'docs/recipes/type-converters.md' => [
            JsonMapper::class . '::map',
            JsonMapper::class . '::addCustomClassMapEntry',
        ],
        // Prose only - it documents attributes, not calls.
        'docs/recipes/using-attributes.md' => [],
    ];

    /**
     * Method names the documentation names in prose rather than calling.
     *
     * An API reference lists its surface as `withErrorCollection(` inside backticks, which is not
     * a call and therefore invisible to the chain patterns above. Dropping this shape when the
     * chain following was introduced would have silently stopped guarding the reference listing -
     * the largest single block of documented names in the package.
     *
     * @var array<string, class-string>
     */
    private const array PROSE_MENTIONS = [
        '/`(with[A-Za-z]+)\(/'                                => JsonMapperConfiguration::class,
        '/`(map|mapWithReport|addType|createWithDefaults)\(/' => JsonMapper::class,
    ];

    /**
     * The documentation this test guards, so a newly added recipe is covered without wiring.
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
     * Collects the documentation files belonging to this package.
     *
     * Restricted to README.md and docs/ on purpose. Walking the repository root would pull in
     * node_modules and .git, which makes the corpus depend on whether someone ran an install -
     * and would fail this test over a dependency's README that happens to mention a method of
     * ours. glob() is avoided because it has no globstar, so a recipe one level deeper would be
     * skipped without a word.
     *
     * @return list<string> Absolute paths of the documentation files
     */
    private static function collectDocumentationFiles(): array
    {
        $files    = [];
        $readme   = self::repositoryRoot() . '/README.md';
        $files[]  = $readme;
        $docsPath = self::repositoryRoot() . '/docs';

        if (!is_dir($docsPath)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if ($entry->getExtension() !== 'md') {
                continue;
            }

            $files[] = $entry->getPathname();
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
     * Both sides are normalised to forward slashes first: realpath() and the directory iterator
     * hand back backslashes on Windows, which would leave the root unstripped and turn every
     * lookup in the expectation map into a miss.
     *
     * @param string $file Absolute path of a documentation file
     *
     * @return string Path relative to the repository root
     */
    private static function toRelativePath(string $file): string
    {
        return str_replace(
            self::toForwardSlashes(self::repositoryRoot()) . '/',
            '',
            self::toForwardSlashes($file)
        );
    }

    /**
     * Normalises a path to forward slashes so it can be compared regardless of platform.
     *
     * @param string $path Path using either separator
     *
     * @return string Path using forward slashes only
     */
    private static function toForwardSlashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Returns the API calls a documentation file makes, each resolved to the class it runs on.
     *
     * @param string $documentation Raw markdown contents
     *
     * @return list<array{class-string, string}> Pairs of class name and called method
     */
    private static function extractDocumentedCalls(string $documentation): array
    {
        $calls = [];

        foreach (self::VARIABLE_TYPES as $variable => $className) {
            // The chain must not bridge arbitrary text: a pattern allowing anything up to the
            // next arrow would swallow a later call belonging to a different receiver on the
            // same line and attribute it here.
            // The argument list has to be consumed, otherwise the repetition stops before the
            // closing paren and the next link is unreachable - which silently reduced this to a
            // single-link match and left the chained classes unguarded.
            preg_match_all(
                sprintf('/\$%s((?:->[A-Za-z_][A-Za-z0-9_]*\([^()]*\))+)/', $variable),
                $documentation,
                $matches
            );

            foreach ($matches[1] as $chain) {
                preg_match_all('/->([A-Za-z_][A-Za-z0-9_]*)\(/', $chain, $methods);

                array_push($calls, ...self::attribute($className, $methods[1]));
            }
        }

        foreach (self::STATIC_ENTRY_POINTS as $literal => $className) {
            // A genuine chain only. Allowing arbitrary text between links would credit any later
            // call on the same line to this class - prose listing two unrelated methods next to
            // a static call would be attributed here, passing or failing for the wrong reason.
            preg_match_all(
                sprintf(
                    '/%s::([A-Za-z_][A-Za-z0-9_]*\([^()]*\)(?:->[A-Za-z_][A-Za-z0-9_]*\([^()]*\))*)/',
                    $literal
                ),
                $documentation,
                $matches
            );

            foreach ($matches[1] as $chain) {
                preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\(/', $chain, $methods);

                array_push($calls, ...self::attribute($className, $methods[1]));
            }
        }

        return $calls;
    }

    /**
     * Attributes each link of a call chain to the class it is invoked on.
     *
     * @param class-string $className Type the chain starts on
     * @param list<string> $methods   Method names in call order
     *
     * @return list<array{class-string, string}> Pairs of class name and called method
     */
    private static function attribute(string $className, array $methods): array
    {
        $calls   = [];
        $current = $className;

        foreach ($methods as $method) {
            $calls[] = [$current, $method];

            $next = self::CHAINED_RETURNS[$current][$method] ?? null;

            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return $calls;
    }

    /**
     * Fails when a sample calls a method that does not exist on the class it is invoked on.
     *
     * @param string $file Absolute path of the markdown file under inspection.
     */
    #[Test]
    #[DataProvider('documentationFileProvider')]
    public function itOnlyDocumentsMethodsThatExist(string $file): void
    {
        $relative      = self::toRelativePath($file);
        $documentation = file_get_contents($file);

        self::assertIsString($documentation, sprintf('%s could not be read.', $relative));

        $calls = self::extractDocumentedCalls($documentation);

        foreach ($calls as [$className, $method]) {
            self::assertTrue(
                method_exists($className, $method),
                sprintf('%s documents %s::%s(), which does not exist.', $relative, $className, $method)
            );
        }

        // Precondition for the assertion below; the authoritative instruction lives in
        // itListsEveryDocumentationFileItGuards() so it is not maintained in two places.
        self::assertArrayHasKey(
            $relative,
            self::GUARDED_CALLS_PER_FILE,
            sprintf('%s is unlisted; see itListsEveryDocumentationFileItGuards().', $relative)
        );

        // Without this the loop above is vacuous for a file whose samples changed into a shape
        // the patterns no longer recognise: zero calls found, zero assertions made, green.
        self::assertSame(
            self::GUARDED_CALLS_PER_FILE[$relative],
            array_map(
                static fn (array $call): string => $call[0] . '::' . $call[1],
                $calls
            ),
            sprintf(
                'The calls checked in %s changed. Entries disappearing without the sample being '
                . 'removed mean it changed into a shape these patterns no longer recognise - '
                . 'extend the recognised shapes rather than deleting the expectation, or the '
                . 'sample goes unchecked.',
                $relative
            )
        );
    }

    /**
     * Fails when a listed documentation file was removed or renamed, or an existing one is
     * unlisted.
     */
    /**
     * Fails when the documentation names a method in prose that does not exist.
     *
     * @param string $file Absolute path of the markdown file under inspection.
     */
    #[Test]
    #[DataProvider('documentationFileProvider')]
    public function itOnlyNamesMethodsThatExist(string $file): void
    {
        $relative      = self::toRelativePath($file);
        $documentation = file_get_contents($file);

        self::assertIsString($documentation, sprintf('%s could not be read.', $relative));

        foreach (self::PROSE_MENTIONS as $pattern => $className) {
            preg_match_all($pattern, $documentation, $matches);

            foreach ($matches[1] as $method) {
                self::assertTrue(
                    method_exists($className, $method),
                    sprintf('%s names %s::%s(), which does not exist.', $relative, $className, $method)
                );
            }
        }
    }

    /**
     * Fails when a listed documentation file was removed or renamed, or an existing one is
     * unlisted.
     */
    #[Test]
    public function itListsEveryDocumentationFileItGuards(): void
    {
        // The counterpart to the per-file assertion: that one fires for a file that exists but is
        // unlisted, this one for a listed file that has been removed or renamed, which would
        // otherwise leave a stale expectation nobody notices.
        $found = [];

        foreach (self::collectDocumentationFiles() as $file) {
            $found[self::toRelativePath($file)] = true;
        }

        $expected = self::GUARDED_CALLS_PER_FILE;
        ksort($found);
        ksort($expected);

        self::assertSame(
            array_keys($expected),
            array_keys($found),
            'The guarded documentation set changed. Add a new file with the calls it documents, and '
            . 'remove entries for files that no longer exist.'
        );
    }
}
