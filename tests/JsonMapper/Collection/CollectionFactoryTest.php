<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Collection;

use DomainException;
use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\JsonMapper\Value\Strategy\PassthroughValueConversionStrategy;
use MagicSunday\JsonMapper\Value\ValueConverter;
use MagicSunday\Test\Classes\Simple;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Two answers the factory owns that the mapper never lets it give: every call site guards a null
 * payload before handing it over, and every collection type the resolvers produce names a class.
 * Both are part of the factory's own contract - it is reached through CollectionFactoryInterface -
 * so they are driven here rather than left to a caller to discover.
 *
 * @internal
 */
final class CollectionFactoryTest extends TestCase
{
    #[Test]
    public function itAnswersANullPayloadWithNoCollectionAtAll(): void
    {
        // Null means "no collection", which is a different answer from "an empty one" - the
        // property accessor rejects null for an array-typed property, so the distinction is
        // observable. treatNullAsEmptyCollection is the option that turns one into the other.
        $context = new MappingContext([]);

        self::assertNull(
            $this->createFactory()->mapIterable(null, Type::object(Simple::class), $context),
        );
    }

    #[Test]
    public function itAnswersANullPayloadWithAnEmptyCollectionWhenAskedTo(): void
    {
        // The discriminator for the assertion above: the same payload, the same factory, and only
        // the option differs.
        $context = new MappingContext([], [MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true]);

        self::assertSame(
            [],
            $this->createFactory()->mapIterable(null, Type::object(Simple::class), $context),
        );
    }

    #[Test]
    public function itRefusesACollectionTypeWhoseWrapperNamesNoClass(): void
    {
        // The wrapper is what gets instantiated to hold the elements, so a type that names no
        // class for it describes nothing buildable. Raised rather than passed to the resolver,
        // which would report it as a class that does not exist - a different problem.
        $type = new CollectionType(
            new GenericType(new ObjectType(''), Type::int(), Type::object(Simple::class)),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/must define a class-string/');

        $this->createFactory()->fromCollectionType($type, [], new MappingContext([]));
    }

    /**
     * Builds a factory whose collaborators do the least that still exercises the factory itself.
     *
     * @return CollectionFactory Factory under test
     */
    private function createFactory(): CollectionFactory
    {
        $valueConverter = new ValueConverter();
        $valueConverter->addStrategy(new PassthroughValueConversionStrategy());

        return new CollectionFactory(
            $valueConverter,
            new ClassResolver(),
            static fn (string $className, ?array $arguments): object => new $className(
                ...($arguments ?? []),
            ),
        );
    }
}
