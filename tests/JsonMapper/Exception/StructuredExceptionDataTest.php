<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Exception;

use FilesystemIterator;
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\Test\Classes\Person;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_map;
use function array_unique;
use function array_values;
use function is_subclass_of;
use function realpath;
use function sort;
use function str_replace;

/**
 * A mapping message embeds the internal class name and the property name the payload supplied, so
 * forwarding it to an API client discloses the DTO namespace and reflects an attacker-chosen string
 * back unescaped. The documentation says to build client-facing text from the structured getters
 * instead - which is only advice a consumer can follow if every exception HAS them.
 *
 * Two did not: the constructor-argument and readonly-property failures carried their class and
 * member names in the message alone, so following the advice meant parsing the very string the
 * advice says not to trust.
 *
 * @internal
 */
final class StructuredExceptionDataTest extends TestCase
{
    #[Test]
    public function itExposesTheConstructorArgumentWithoutParsingTheMessage(): void
    {
        $exception = new MissingConstructorArgumentException('$.user', 'name', Person::class);

        self::assertSame('name', $exception->getArgumentName());
        self::assertSame(Person::class, $exception->getClassName());
        self::assertSame('$.user', $exception->getPath());
    }

    #[Test]
    public function itExposesTheReadonlyPropertyWithoutParsingTheMessage(): void
    {
        $exception = new ReadonlyPropertyException('$.user.id', 'id', Person::class);

        self::assertSame('id', $exception->getPropertyName());
        self::assertSame(Person::class, $exception->getClassName());
        self::assertSame('$.user.id', $exception->getPath());
    }

    /**
     * Every mapping exception, and the accessors a consumer needs to rebuild the failure without
     * touching getMessage().
     *
     * Each accessor is given as a named closure rather than a method name: calling it is what
     * proves a consumer can reach the value, and it keeps the call statically checkable.
     *
     * @return array<string, array{MappingException, array<string, callable(): string>}>
     */
    public static function structuredAccessorProvider(): array
    {
        $missingArgument = new MissingConstructorArgumentException('$', 'name', Person::class);
        $readonly        = new ReadonlyPropertyException('$', 'id', Person::class);
        $missing         = new MissingPropertyException('$', 'name', Person::class);
        $unknown         = new UnknownPropertyException('$', 'name', Person::class);
        $mismatch        = new TypeMismatchException('$', 'int', 'string');
        $collection      = new CollectionMappingException('$', 'string');

        return [
            'missing constructor argument' => [
                $missingArgument,
                [
                    'getPath'         => $missingArgument->getPath(...),
                    'getArgumentName' => $missingArgument->getArgumentName(...),
                    'getClassName'    => $missingArgument->getClassName(...),
                ],
            ],
            'readonly property' => [
                $readonly,
                [
                    'getPath'         => $readonly->getPath(...),
                    'getPropertyName' => $readonly->getPropertyName(...),
                    'getClassName'    => $readonly->getClassName(...),
                ],
            ],
            'missing property' => [
                $missing,
                [
                    'getPath'         => $missing->getPath(...),
                    'getPropertyName' => $missing->getPropertyName(...),
                    'getClassName'    => $missing->getClassName(...),
                ],
            ],
            'unknown property' => [
                $unknown,
                [
                    'getPath'         => $unknown->getPath(...),
                    'getPropertyName' => $unknown->getPropertyName(...),
                    'getClassName'    => $unknown->getClassName(...),
                ],
            ],
            'type mismatch' => [
                $mismatch,
                [
                    'getPath'         => $mismatch->getPath(...),
                    'getExpectedType' => $mismatch->getExpectedType(...),
                    'getActualType'   => $mismatch->getActualType(...),
                ],
            ],
            'collection mapping' => [
                $collection,
                [
                    'getPath'       => $collection->getPath(...),
                    'getActualType' => $collection->getActualType(...),
                ],
            ],
        ];
    }

    /**
     * @param MappingException                  $exception Exception under inspection
     * @param array<string, callable(): string> $accessors Accessors a consumer must be able to use
     */
    #[Test]
    #[DataProvider('structuredAccessorProvider')]
    public function itLetsEveryFailureBeRebuiltFromAccessors(MappingException $exception, array $accessors): void
    {
        foreach ($accessors as $name => $accessor) {
            // Called, not merely checked for existence: an accessor returning an empty string
            // would satisfy method_exists() while leaving the consumer nothing to build from.
            self::assertNotSame(
                '',
                $accessor(),
                $exception::class . '::' . $name . '() must carry a value a client message can use.',
            );
        }
    }

    #[Test]
    public function itCoversEveryMappingExceptionInTheHierarchy(): void
    {
        // The provider above is hand-written, so on its own it asserts only what someone
        // remembered to type: a seventh exception added tomorrow with everything in getMessage()
        // and no accessors would add no row and the suite would stay green, while AGENTS.md claims
        // this test is what catches that. Derived from the directory, the claim becomes true.
        $declared = [];

        // Walked recursively over the whole of src/ rather than one directory: a subdirectory, or
        // an exception placed next to the code that raises it, would otherwise never be
        // enumerated - the exact omission this test exists to prevent, moved one level up.
        // realpath(): the iterator yields resolved pathnames, so a source root still carrying
        // '..' segments would never be a prefix of them and every file would be skipped - leaving
        // the inventory empty and the test vacuously green, which is the failure mode it exists
        // to prevent.
        $source = realpath(__DIR__ . '/../../../src');

        self::assertIsString($source, 'The source directory must be readable.');

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$source, '/', '.php'], ['', '\\', ''], $file->getPathname());
            // 'MagicSunday' alone: src/ already contains the JsonMapper segment, so prefixing
            // the full root namespace produced MagicSunday\JsonMapper\JsonMapper\... - a name
            // that exists nowhere, leaving the inventory empty and the test vacuously green.
            $candidate = 'MagicSunday' . $relative;

            if (is_subclass_of($candidate, MappingException::class)) {
                $declared[] = $candidate;
            }
        }

        $covered = array_map(
            static fn (array $row): string => $row[0]::class,
            self::structuredAccessorProvider(),
        );

        sort($declared);
        $covered = array_values(array_unique($covered));
        sort($covered);

        self::assertSame(
            $declared,
            $covered,
            'Every MappingException subclass needs a row here, or the documented guarantee lapses.',
        );
    }
}
