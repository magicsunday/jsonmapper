<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Resolver;

use DomainException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\VipPerson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_string;
use function preg_quote;

/**
 * A discriminator closure decides which class gets instantiated, and its input is the payload. A
 * consumer who writes the naive version - returning the payload's own type field - hands an
 * attacker the choice of class, with constructor arguments that also come from the payload. That
 * is the classic object-injection surface, and class_exists() is no defence against it.
 *
 * The library cannot stop a consumer writing that closure, but it can offer the guard rail the
 * consumer would otherwise have to remember to build: a per-entry list of classes the resolver is
 * allowed to return.
 *
 * @internal
 */
final class ClassResolverAllowlistTest extends TestCase
{
    #[Test]
    public function itResolvesAClassOnTheAllowlist(): void
    {
        $resolver = new ClassResolver();
        $resolver->add(
            Person::class,
            static fn (): string => VipPerson::class,
            [VipPerson::class, Person::class],
        );

        self::assertSame(
            VipPerson::class,
            $resolver->resolve(Person::class, [], new MappingContext([])),
        );
    }

    #[Test]
    public function itRefusesAClassTheAllowlistDoesNotName(): void
    {
        // The attack in miniature: the closure returns whatever the payload asked for, and the
        // payload asked for something the consumer never intended to expose.
        $resolver = new ClassResolver();
        $resolver->add(
            Person::class,
            static function (mixed $json): string {
                // The naive discriminator, written out as a consumer would: whatever the payload
                // says. Its return type is a promise the payload has no obligation to keep.
                if (is_array($json) && is_string($json['__type'] ?? null)) {
                    /** @var class-string $type */
                    $type = $json['__type'];

                    return $type;
                }

                return Person::class;
            },
            [VipPerson::class],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote(Base::class, '/') . '/');

        $resolver->resolve(Person::class, ['__type' => Base::class], new MappingContext([]));
    }

    #[Test]
    public function itRefusesEvenAnExistingClassThatIsNotListed(): void
    {
        // The point of the allowlist: existing and instantiable is exactly what class_exists()
        // already checked, and exactly what an object-injection gadget also satisfies. Being named
        // is a different question, and the only one that helps.
        $resolver = new ClassResolver();
        $resolver->add(Person::class, static fn (): string => Base::class, [VipPerson::class]);

        $this->expectException(DomainException::class);

        $resolver->resolve(Person::class, [], new MappingContext([]));
    }

    #[Test]
    public function itLeavesAnEntryWithoutAnAllowlistUnrestricted(): void
    {
        // Opt-in, because the class map is documented for class REPLACEMENT as well as
        // polymorphism - SdkFoo::class => Foo::class maps between unrelated types on purpose - so
        // a default restriction would break the recipe the library itself publishes.
        $resolver = new ClassResolver();
        $resolver->add(Person::class, static fn (): string => Base::class);

        self::assertSame(
            Base::class,
            $resolver->resolve(Person::class, [], new MappingContext([])),
        );
    }

    #[Test]
    public function itRejectsAnAllowlistNamingAClassThatDoesNotExist(): void
    {
        // Caught when the entry is registered rather than when a payload arrives: a typo in the
        // allowlist would otherwise silently narrow it, and the resolver would start refusing a
        // class the consumer believes it permitted.
        $resolver = new ClassResolver();

        $this->expectException(DomainException::class);

        $resolver->add(Person::class, static fn (): string => VipPerson::class, ['NotAClass']);
    }
}
