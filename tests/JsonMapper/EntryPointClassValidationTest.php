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
use InvalidArgumentException;
use MagicSunday\Test\Fixtures\EntryPoint\AbstractShape;
use MagicSunday\Test\Fixtures\EntryPoint\Circle;
use MagicSunday\Test\Fixtures\EntryPoint\Shape;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

use function preg_quote;

/**
 * The class names handed to map() are the call's own, not the payload's, so a bad one is a defect
 * in the caller rather than in the data - it escapes as a configuration exception instead of being
 * collected into a report. What the entry point has to guarantee is that it names the problem: the
 * alternative is a native Error from `new $className`, which is invisible to error collection and
 * says nothing about which argument was wrong.
 *
 * @internal
 */
final class EntryPointClassValidationTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function uninstantiableClassProvider(): array
    {
        return [
            // class_exists() answers false for an interface and true for the other two, so a guard
            // written around existence catches only the first - and the remaining two reach
            // `new $className` and raise a native Error.
            'interface'      => [Shape::class],
            'abstract class' => [AbstractShape::class],
            'enum'           => [SampleStatus::class],
        ];
    }

    /**
     * @param class-string $className Class the caller named for the mapped elements
     */
    #[Test]
    #[DataProvider('uninstantiableClassProvider')]
    public function itRefusesAnElementClassItCannotInstantiate(string $className): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^' . preg_quote('Class [' . $className . '] cannot be instantiated', '/') . '/'
        );

        $this->getJsonMapper()->map(['name' => 'round'], $className);
    }

    /**
     * @param class-string $collectionClassName Class the caller named for the wrapping collection
     */
    #[Test]
    #[DataProvider('uninstantiableClassProvider')]
    public function itRefusesACollectionClassItCannotInstantiate(string $collectionClassName): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^' . preg_quote('Class [' . $collectionClassName . '] cannot be instantiated', '/') . '/'
        );

        $this->getJsonMapper()->map([['name' => 'round']], Circle::class, $collectionClassName);
    }

    #[Test]
    public function itPointsAtTheClassMapAsTheWayToUseAnInterface(): void
    {
        // The message has to name the way out, because refusing an interface is otherwise
        // indistinguishable from the mapper simply not supporting one.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/addCustomClassMapEntry/');

        $this->getJsonMapper()->map(['name' => 'round'], Shape::class);
    }

    #[Test]
    public function itStillMapsAnInterfaceThatTheClassMapResolves(): void
    {
        // The control for all of the above: the guard must not close the supported way to hand an
        // interface to map(). By the time the guard runs, the class map has already replaced the
        // interface with the concrete class, so there is nothing left for it to refuse.
        $result = $this->getJsonMapper([Shape::class => Circle::class])
            ->map(['name' => 'round', 'radius' => 3], Shape::class);

        self::assertInstanceOf(Circle::class, $result);
        self::assertSame('round', $result->name);
        self::assertSame(3, $result->radius);
    }

    #[Test]
    public function itDoesNotEchoAClassNameAResolverProduced(): void
    {
        // A resolver's input is the payload, and this exception escapes past the report into
        // whatever generic handler the consumer wrote - so echoing what came back would put a
        // payload-chosen string into a response body. The name the CALL passed is the caller's
        // own, and is enough to find the entry that produced the wrong target.
        $mapper = $this->getJsonMapper([Shape::class => static fn (): string => AbstractShape::class]);

        try {
            $mapper->map(['name' => 'round'], Shape::class);

            self::fail('A resolver returning an abstract class must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(Shape::class, $exception->getMessage(), 'The requested name.');
            self::assertStringNotContainsString(
                AbstractShape::class,
                $exception->getMessage(),
                'And not the one the resolver chose.',
            );
        }
    }

    #[Test]
    #[TestWith(['No\\Such\\ElementClass', null])]
    #[TestWith([null, 'No\\Such\\CollectionClass'])]
    public function itRefusesAClassNameThatNamesNothing(?string $className, ?string $collectionClassName): void
    {
        // A different failure from the one above, and deliberately a different exception: a name
        // that resolves to nothing never reaches the instantiability question, and the resolver
        // that owns class-string validation is what reports it.
        $name = $className ?? $collectionClassName;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches(
            '/^' . preg_quote('Resolved class ' . $name . ' does not exist.', '/') . '$/'
        );

        /** @var class-string|null $className */
        /** @var class-string|null $collectionClassName */
        $this->getJsonMapper()->map([['name' => 'round']], $className, $collectionClassName);
    }
}
