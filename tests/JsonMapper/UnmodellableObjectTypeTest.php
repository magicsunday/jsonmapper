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
use MagicSunday\Test\Classes\FixedObjectTypeExtractor;
use MagicSunday\Test\Classes\VirtualPropertyHolder;
use MagicSunday\Test\Fixtures\EntryPoint\Shape;
use MagicSunday\Test\Fixtures\EntryPoint\ShapeHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use function chr;
use function preg_quote;
use function sprintf;

/**
 * A type whose metadata names no class the mapper can instantiate is a failure of the payload to
 * fit it and is reported, while a class name that resolves to nothing is a mistake of the
 * developer's configuration and escapes past the report.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class UnmodellableObjectTypeTest extends TestCase
{
    /**
     * Names that are not shaped like a class name, one row per way of failing the shape.
     *
     * @return array<string, array{string}>
     */
    public static function malformedNameProvider(): array
    {
        return [
            'source text of an intersection'                      => ['(\MagicSunday\Test\Fixtures\PropertyWrite\MarkerA&\MagicSunday\Test\Fixtures\PropertyWrite\MarkerB)'],
            'source text of a union'                              => ['A|B'],
            'trailing separator'                                  => ['Some\Namespace\\'],
            'doubled separator'                                   => ['Some\\\\Name'],
            'starts with a digit'                                 => ['1Some\Name'],
            'contains a space'                                    => ['Some Name'],
            'later segment starts with a digit'                   => ['Some\\1Name'],
            'anonymous marker without the NUL byte'               => ['Foo@anonymous/a.php'],
            'anonymous marker directly followed by a name'        => ['Foo@anonymousfoo'],
            'character after the lowercase range starts the name' => ['{Foo'],
            'character after the lowercase range ends the name'   => ['Foo{'],
            'character after the uppercase range starts the name' => ['[Foo'],
            'character after the uppercase range ends the name'   => ['Foo['],
            'character after the digit range ends the name'       => ['Foo:'],
            'hyphen inside the name'                              => ['Foo-Bar'],
            'character before the high bytes starts the name'     => ["\x7fFoo"],
            'character before the high bytes ends the name'       => ["Foo\x7f"],
            'two leading separators'                              => ['\\\\Foo'],
            'anonymous marker followed by another byte than NUL'  => ["Foo@anonymous\x01x"],
            'ends in a line break'                                => ["Some\\Name\n"],
        ];
    }

    /**
     * Names that are shaped like a class name but resolve to nothing, one row per way of being
     * well formed.
     *
     * @return array<string, array{string}>
     */
    public static function wellFormedNameProvider(): array
    {
        return [
            'plain name'                                     => ['No\Such\Thing'],
            'leading separator'                              => ['\No\Such\Thing'],
            'name without a namespace'                       => ['NoSuchThing'],
            'bytes above the ascii set'                      => ["No\xC3\xB6\\Such"],
            'single character segments'                      => ['A\\B'],
            'digit and underscore after the first character' => ['Foo_Bar2'],
            'underscore starts the name'                     => ['_Foo'],
            'lowercase and underscore segments'              => ['app\\foo\\_Bar9'],
            'letter and digit range ends in both positions'  => ['Zeta\\Az0\\zZ9'],
            'highest byte starts the name'                   => ["\xffFoo"],
            'lowest high byte starts the name'               => ["\x80Foo"],
            'highest byte ends the name'                     => ["Foo\xff"],
            'lowest high byte ends the name'                 => ["Foo\x80"],
            'anonymous class suffix'                         => ["class@anonymous\0/no/such/file.php:1\$0"],
            'line break in the declaration place'            => ["class@anonymous\0/a\nb.php:1\$0"],
            'high byte starts the name'                      => ["\xC3\x96\\Such\\Thing"],
            'high byte starts a segment'                     => ["No\\\xC3\x96Thing"],
        ];
    }

    #[Test]
    public function itReportsAnIntersectionPropertyAtItsPath(): void
    {
        // Smoke test with the real extractor. The installed type metadata models the intersection
        // itself, so this holds with or without the guard. The table below reproduces the old shape.
        $result = $this->getJsonMapper()->mapWithReport(
            ['virtual' => ['a' => 1]],
            VirtualPropertyHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One property, one record.');
        self::assertSame('$.virtual', $errors[0]->getPath());
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertInstanceOf(VirtualPropertyHolder::class, $result->getValue());
    }

    /**
     * @param string $name Name the type metadata reports for every property
     */
    #[Test]
    #[DataProvider('malformedNameProvider')]
    public function itReportsAnObjectTypeWhoseNameIsNotShapedLikeAClassName(string $name): void
    {
        $result = $this->getMapperReporting($name)
            ->mapWithReport(['virtual' => ['a' => 1]], VirtualPropertyHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One property, one record.');
        self::assertSame('$.virtual', $errors[0]->getPath());

        $exception = $errors[0]->getException();

        // The accessors are what a consumer builds client-facing text from, so each argument of the
        // exception is pinned on the exception itself, not only the path the property catch records.
        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('$.virtual', $exception->getPath());
        self::assertSame($name, $exception->getExpectedType());
        self::assertSame('array', $exception->getActualType());
    }

    /**
     * @param int|string|float|bool $payload      Scalar payload for the property
     * @param string                $expectedType Type the exception reports for that payload
     */
    #[Test]
    #[DataProvider('scalarPayloadProvider')]
    public function itReportsThePayloadTypeOfAScalarAgainstAnObjectTypeWithoutAClassName(
        int|string|float|bool $payload,
        string $expectedType,
    ): void {
        $result = $this->getMapperReporting('(A&B)')
            ->mapWithReport(['virtual' => $payload], VirtualPropertyHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One property, one record.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame($expectedType, $exception->getActualType());
    }

    /**
     * @return array<string, array{int|string|float|bool, string}>
     */
    public static function scalarPayloadProvider(): array
    {
        return [
            'integer' => [5, 'int'],
            'string'  => ['text', 'string'],
            'float'   => [1.5, 'float'],
            'boolean' => [true, 'bool'],
        ];
    }

    /**
     * @param string $name Name the type metadata reports for every property
     */
    #[Test]
    #[DataProvider('wellFormedNameProvider')]
    public function itStillRaisesForAWellFormedNameThatNamesNothing(string $name): void
    {
        // A valid class name that resolves to nothing is a declaration mistake. It must reach the
        // developer instead of being buried in a report.
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/does not exist\.$/');

        $this->getMapperReporting($name)
            ->mapWithReport(['virtual' => ['a' => 1]], VirtualPropertyHolder::class);
    }

    #[Test]
    public function itClassifiesEveryByteAtEveryPositionOfAName(): void
    {
        // The oracle is spelled out independently of the pattern: letters, the underscore and every
        // byte above the ASCII set may start a segment, digits may follow the first character, and
        // a separator may lead the name.
        $templates = [
            'first character of the name'       => [static fn (string $byte): string => $byte . 'oo', true],
            'later character of the first part' => [static fn (string $byte): string => 'Fo' . $byte, false],
            'first character of a later part'   => [static fn (string $byte): string => 'Foo\\' . $byte . 'oo', true],
            'later character of a later part'   => [static fn (string $byte): string => 'Foo\\Fo' . $byte, false],
        ];

        foreach ($templates as $position => [$build, $startsSegment]) {
            for ($code = 0; $code <= 255; ++$code) {
                $isLetter = (($code >= 65) && ($code <= 90)) || (($code >= 97) && ($code <= 122));
                $isDigit  = ($code >= 48) && ($code <= 57);
                $accepted = $isLetter || ($code === 95) || ($code >= 128) || (!$startsSegment && $isDigit)
                    || (($code === 92) && ($position === 'first character of the name'));

                $raised = false;

                try {
                    $this->getMapperReporting($build(chr($code)))
                        ->mapWithReport(['virtual' => ['a' => 1]], VirtualPropertyHolder::class);
                } catch (DomainException) {
                    $raised = true;
                }

                self::assertSame($accepted, $raised, sprintf('Byte 0x%02x as %s.', $code, $position));
            }
        }
    }

    #[Test]
    public function itStillMapsAnAnonymousClass(): void
    {
        // The runtime name of an anonymous class is accepted, and the class can be built.
        $dto = new class {
            public int $id = 0;
        };

        $result = $this->getJsonMapper()->mapWithReport([['id' => 1]], $dto::class);

        $value = $result->getValue();

        self::assertCount(0, $result->getReport()->getErrors());
        self::assertIsArray($value);
        self::assertCount(1, $value);
        self::assertInstanceOf($dto::class, $value[0]);
        self::assertSame(1, $value[0]->id);
    }

    #[Test]
    public function itStillRaisesForAResolverThatReturnsAClassThatDoesNotExist(): void
    {
        /** @var class-string $missing */
        $missing = (new ReflectionClass($this))->getName() . 'Missing';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/^' . preg_quote('The class a resolver returned does not exist.', '/') . '$/');

        $this->getJsonMapper([Shape::class => static fn (): string => $missing])
            ->mapWithReport(['shape' => ['name' => 'round']], ShapeHolder::class);
    }

    /**
     * @param string $className Name the type metadata reports for every property
     */
    private function getMapperReporting(string $className): JsonMapper
    {
        return new JsonMapper(
            new PropertyInfoExtractor(
                [new ReflectionExtractor()],
                [new FixedObjectTypeExtractor($className), new PhpDocExtractor()],
            ),
            PropertyAccess::createPropertyAccessor(),
            new CamelCasePropertyNameConverter(),
            [],
            null,
            new JsonMapperConfiguration(),
        );
    }
}
