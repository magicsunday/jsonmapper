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
use MagicSunday\Test\Classes\AbstractCustomDateTime;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\VipPerson;
use MagicSunday\Test\Fixtures\Enum\SampleColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

use function is_array;
use function is_string;

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

        try {
            $resolver->resolve(Person::class, ['__type' => Base::class], new MappingContext([]));

            self::fail('A class the allowlist does not name must be refused.');
        } catch (DomainException $exception) {
            // The refused name must NOT appear. A resolver's return value can be a raw payload
            // string - that is the hazard the allowlist addresses - and this exception escapes past
            // the mapping report into whatever generic handler the consumer wrote, so echoing it
            // would put an attacker-chosen string into a response body. Verified with an XSS-shaped
            // value rather than a class name, because that is what the reflection would carry.
            self::assertStringNotContainsString(Base::class, $exception->getMessage());
            // The scrub must not go too far: the BASE class is configuration the consumer wrote,
            // and a message hiding it leaves them hunting for the entry with no clue which one.
            self::assertStringContainsString(Person::class, $exception->getMessage(), 'The entry is still identifiable.');
        }

        try {
            $resolver->resolve(Person::class, ['__type' => '<img src=x onerror=alert(1)>'], new MappingContext([]));

            self::fail('A payload-supplied name must be refused too.');
        } catch (DomainException $exception) {
            self::assertStringNotContainsString('<img', $exception->getMessage(), 'No payload string is reflected.');
        }
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
    public function itLeavesNothingRegisteredWhenTheAllowlistIsRejected(): void
    {
        // The guard failing OPEN, which is the worst way for a guard to fail. Registering the
        // resolver before validating the list meant a typo threw AND left the entry live with no
        // restriction at all - the exact surface the allowlist closes, reached by getting the
        // allowlist slightly wrong. A caller that logs and continues past configuration errors
        // would never learn of it.
        $resolver = new ClassResolver();

        try {
            $resolver->add(
                Person::class,
                static fn (): string => Base::class,
                [VipPerson::class, 'Typo\Nope'],
            );

            self::fail('An allowlist naming a missing class must be rejected.');
        } catch (DomainException) {
            // Expected - what matters is the state left behind.
        }

        // Nothing was registered, so the base class resolves to itself rather than through a
        // resolver nobody managed to restrict.
        self::assertSame(
            Person::class,
            $resolver->resolve(Person::class, [], new MappingContext([])),
        );
    }

    #[Test]
    public function itDoesNotReflectAPayloadNameWhenNoAllowlistRestrictsTheEntry(): void
    {
        // The path the first scrub missed, and the one that matters most: the allowlist is opt-in,
        // so an entry WITHOUT one is the default configuration. Whatever the payload asked for
        // reaches assertClassString(), whose message escapes past the mapping report into a
        // generic handler exactly as the allowlist refusal does.
        //
        // Denying the echo also denies a class-existence oracle: a name that exists produces no
        // exception and one that does not produces this, which is enough to enumerate loadable
        // classes for gadget discovery.
        $resolver = new ClassResolver();
        $resolver->add(Person::class, static function (mixed $json): string {
            if (is_array($json) && is_string($json['__type'] ?? null)) {
                /** @var class-string $type */
                $type = $json['__type'];

                return $type;
            }

            return Person::class;
        });

        try {
            $resolver->resolve(
                Person::class,
                ['__type' => '<img src=x onerror=alert(1)>'],
                new MappingContext([]),
            );

            self::fail('A class that does not exist must be refused.');
        } catch (DomainException $exception) {
            self::assertStringNotContainsString('<img', $exception->getMessage());
        }
    }

    #[Test]
    public function itRejectsAnEmptyAllowlist(): void
    {
        // An empty list would make every resolution fail, which no caller intends. It arrives from
        // a config lookup that found nothing or a filter that removed everything, and left alone it
        // is the extreme case of the silent narrowing registration-time validation exists to catch.
        $resolver = new ClassResolver();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/empty/');

        $resolver->add(Person::class, static fn (): string => VipPerson::class, []);
    }

    #[Test]
    public function itAcceptsAnyValidSpellingOfAnAllowedClass(): void
    {
        // PHP resolves '\Circle', 'circle' and Circle::class to one class, so a strict string
        // comparison is narrower than instantiation itself. That direction is safe but wrong: it
        // refuses payloads that are in fact permitted, at request time, and a resolver composing a
        // name from parts produces the leading-backslash form as a matter of course.
        $resolver = new ClassResolver();
        $resolver->add(Person::class, static fn (): string => '\\' . VipPerson::class, [VipPerson::class]);

        self::assertSame(
            '\\' . VipPerson::class,
            $resolver->resolve(Person::class, [], new MappingContext([])),
        );
    }

    #[Test]
    public function itDropsAnAllowlistWhenTheEntryIsReplacedWithoutOne(): void
    {
        // An entry is replaced wholesale, so a list written for the previous closure must not
        // outlive it - a closure paired with somebody else's allowlist is a state neither party
        // asked for. The consequence is that re-registering widens, so registration order matters;
        // pinned here so that is a decision rather than a surprise.
        $resolver = new ClassResolver();
        $resolver->add(Person::class, static fn (): string => VipPerson::class, [VipPerson::class]);
        $resolver->add(Person::class, static fn (): string => Base::class);

        self::assertSame(
            Base::class,
            $resolver->resolve(Person::class, [], new MappingContext([])),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uninstantiableTargetProvider(): array
    {
        // Three kinds, one reason: none of them can be what a resolver returns for instantiation.
        // assertClassString() accepts the interface and class_exists() accepts the other two, so
        // each needed its own rejection - left to fail later they become a native Error from
        // makeInstance(), outside the error-collection contract, on a payload that looks fine.
        return [
            'interface'      => [Stringable::class],
            'abstract class' => [AbstractCustomDateTime::class],
            'enum'           => [SampleColor::class],
        ];
    }

    /**
     * @param string $target Class name that cannot be instantiated
     */
    #[Test]
    #[DataProvider('uninstantiableTargetProvider')]
    public function itRejectsAnAllowlistNamingSomethingUninstantiable(string $target): void
    {
        $resolver = new ClassResolver();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/not an instantiable class/');

        $resolver->add(Person::class, static fn (): string => VipPerson::class, [$target]);
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
