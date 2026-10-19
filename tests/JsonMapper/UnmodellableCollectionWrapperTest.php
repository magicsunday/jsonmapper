<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use DomainException;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\FixedCollectionTypeExtractor;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\VirtualPropertyHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use function get_debug_type;

/**
 * A collection wrapper whose name is not shaped like a class name is a failure of the declaration
 * to fit the payload and is reported, like an object type of that kind. A class name the caller
 * passes to an entry point is a mistake of the developer and escapes past the report.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class UnmodellableCollectionWrapperTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function malformedNameProvider(): array
    {
        return [
            'source text of an intersection' => ['(\A&\B)'],
            'source text of a union'         => ['A|B'],
            'contains a space'               => ['Some Name'],
            'ends in a line break'           => ["Some\\Name\n"],
        ];
    }

    /**
     * @param string $name Name the type metadata reports for the wrapper of every collection
     */
    #[Test]
    #[DataProvider('malformedNameProvider')]
    public function itReportsACollectionWrapperWhoseNameIsNotShapedLikeAClassName(string $name): void
    {
        $result = $this->getMapperReporting($name)
            ->mapWithReport(['virtual' => [1, 2]], VirtualPropertyHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One property, one record.');
        self::assertSame('$.virtual', $errors[0]->getPath());

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame($name, $exception->getExpectedType());
        self::assertSame('array', $exception->getActualType());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function payloadProvider(): array
    {
        return [
            'a string, which is no collection'      => ['text'],
            'an integer, which is no collection'    => [5],
            'a list with an element that is wrong'  => [[1, 'bad']],
            'a list with an element that is a list' => [[1, [2]]],
        ];
    }

    /**
     * @param mixed $payload Value of the collection property
     */
    #[Test]
    #[DataProvider('payloadProvider')]
    public function itRecordsAMalformedWrapperOnceWhateverThePayloadLooksLike(mixed $payload): void
    {
        // A failure of the payload shape or of an element must not be recorded next to the failure
        // of the wrapper: one rejected value is one record.
        $result = $this->getMapperReporting('(\\A&\\B)')
            ->mapWithReport(['virtual' => $payload], VirtualPropertyHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertSame('$.virtual', $errors[0]->getPath());

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(get_debug_type($payload), $exception->getActualType());
    }

    #[Test]
    public function itStillRaisesForAWrapperNameThatIsWellFormedAndNamesNothing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/does not exist\.$/');

        $this->getMapperReporting('No\\Such\\Wrapper')
            ->mapWithReport(['virtual' => [1, 2]], VirtualPropertyHolder::class);
    }

    #[Test]
    public function itRaisesForAMalformedClassNameThePassedToTheEntryPoint(): void
    {
        // The caller supplies this name in code, so a malformed one is a declaration mistake like
        // a well formed name that resolves to nothing, and no payload can choose it.
        $this->expectException(DomainException::class);

        $malformed = self::asClassString('Not A Class');

        $this->getJsonMapper()->mapWithReport(['name' => 'x'], $malformed);
    }

    #[Test]
    public function itRaisesForAMalformedCollectionClassNamePassedToTheEntryPoint(): void
    {
        $this->expectException(DomainException::class);

        $malformed = self::asClassString('Not A Class');

        $this->getJsonMapper()->mapWithReport([['name' => 'x']], Person::class, $malformed);
    }

    /**
     * Hands a string to the entry point as a class-string, which it declares but does not check.
     *
     * @param string $name Name that need not be a class name
     *
     * @return class-string
     */
    private static function asClassString(string $name): string
    {
        /** @var class-string $name */
        return $name;
    }

    /**
     * @param string $className Name the type metadata reports for the wrapper
     */
    private function getMapperReporting(string $className): JsonMapper
    {
        return new JsonMapper(
            new PropertyInfoExtractor(
                [new ReflectionExtractor()],
                [new FixedCollectionTypeExtractor($className), new PhpDocExtractor()],
            ),
            PropertyAccess::createPropertyAccessor(),
            new CamelCasePropertyNameConverter(),
            [],
            null,
            new JsonMapperConfiguration(),
        );
    }
}
