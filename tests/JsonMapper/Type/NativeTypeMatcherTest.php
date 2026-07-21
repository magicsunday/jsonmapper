<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Type;

use ArrayAccess;
use Closure;
use Countable;
use MagicSunday\JsonMapper\Type\NativeTypeMatcher;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionType;

use function explode;
use function sort;
use function str_starts_with;
use function trim;

/**
 * The matcher answers whether a value would survive a native parameter declaration, one-sidedly:
 * FALSE only for a violation it can prove, TRUE for anything it fits or cannot judge. Its branches
 * are exercised through the constructor guard end to end in {@see TypePrecedenceTest}; here each
 * type category is pinned directly, including the ones a constructor payload rarely reaches - a
 * union, an intersection, the built-in pseudo-types - so a regression in one category is caught
 * without having to construct a mapping that funnels a value into it.
 *
 * Each declaration under test is carried by a real typed closure whose sole parameter is reflected,
 * rather than a string compiled at runtime - the declarations stay ordinary type-checked source.
 *
 * @internal
 */
final class NativeTypeMatcherTest extends TestCase
{
    /**
     * A closure whose single parameter carries the declaration under test, paired with the value and
     * the expected verdict.
     *
     * @return array<string, array{0: Closure, 1: mixed, 2: bool}>
     */
    public static function declarationProvider(): array
    {
        return [
            'int accepts an int'                 => [static fn (int $p): mixed => $p, 7, true],
            'int refuses a string'               => [static fn (int $p): mixed => $p, '7', false],
            'int refuses a float'                => [static fn (int $p): mixed => $p, 7.0, false],
            'float accepts an int'               => [static fn (float $p): mixed => $p, 7, true],
            'float accepts a float'              => [static fn (float $p): mixed => $p, 7.0, true],
            'float refuses a string'             => [static fn (float $p): mixed => $p, '7', false],
            'string accepts a string'            => [static fn (string $p): mixed => $p, 'x', true],
            'string refuses an int'              => [static fn (string $p): mixed => $p, 7, false],
            'bool accepts a bool'                => [static fn (bool $p): mixed => $p, true, true],
            'bool refuses an int'                => [static fn (bool $p): mixed => $p, 1, false],
            'true accepts true'                  => [static fn (true $p): mixed => $p, true, true],
            'true refuses false'                 => [static fn (true $p): mixed => $p, false, false],
            'false accepts false'                => [static fn (false $p): mixed => $p, false, true],
            'false refuses true'                 => [static fn (false $p): mixed => $p, true, false],
            'array accepts an array'             => [static fn (array $p): mixed => $p, ['x'], true],
            'array refuses a string'             => [static fn (array $p): mixed => $p, 'x', false],
            'object accepts an object'           => [static fn (object $p): mixed => $p, (object) [], true],
            'object refuses a scalar'            => [static fn (object $p): mixed => $p, 'x', false],
            'iterable accepts an array'          => [static fn (iterable $p): mixed => $p, ['x'], true],
            'iterable refuses a string'          => [static fn (iterable $p): mixed => $p, 'x', false],
            'callable accepts a callable string' => [static fn (callable $p): mixed => $p, 'strlen', true],
            'callable refuses a plain string'    => [static fn (callable $p): mixed => $p, 'not a function', false],
            'nullable int accepts null'          => [static fn (?int $p): mixed => $p, null, true],
            'nullable int refuses a string'      => [static fn (?int $p): mixed => $p, 'x', false],
            'non-null int refuses null'          => [static fn (int $p): mixed => $p, null, false],
            'union accepts either member'        => [static fn (int|string $p): mixed => $p, 'x', true],
            'union refuses a non-member'         => [static fn (int|string $p): mixed => $p, 1.5, false],
            'mixed accepts anything'             => [static fn (mixed $p): mixed => $p, 1.5, true],
        ];
    }

    #[Test]
    #[DataProvider('declarationProvider')]
    public function itAcceptsExactlyTheValuesTheDeclarationWouldTake(
        Closure $declaration,
        mixed $value,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            NativeTypeMatcher::accepts($this->typeOf($declaration), $value, self::class),
        );
    }

    #[Test]
    public function itAcceptsAValueThatSatisfiesEveryMemberOfAnIntersection(): void
    {
        // An intersection is satisfied only when the value implements all members, and refused when
        // it implements only some - the two directions the recursive AND has to get right.
        $type = $this->typeOf(static fn (Countable&ArrayAccess $p): mixed => $p);

        $both = new class implements Countable, ArrayAccess {
            public function count(): int
            {
                return 0;
            }

            public function offsetExists(mixed $offset): bool
            {
                return false;
            }

            public function offsetGet(mixed $offset): mixed
            {
                return null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
            }

            public function offsetUnset(mixed $offset): void
            {
            }
        };

        $onlyOne = new class implements Countable {
            public function count(): int
            {
                return 0;
            }
        };

        self::assertTrue(NativeTypeMatcher::accepts($type, $both, self::class), 'Implements both members.');
        self::assertFalse(NativeTypeMatcher::accepts($type, $onlyOne, self::class), 'Implements only one.');
    }

    #[Test]
    public function itTreatsAnAbsentDeclarationAsAcceptingAnything(): void
    {
        // A parameter with no type constrains nothing, so the matcher must not refuse for it.
        self::assertTrue(NativeTypeMatcher::accepts(null, 'anything', self::class));
    }

    #[Test]
    public function itRendersEachDeclarationInSourceNotation(): void
    {
        self::assertSame('int', NativeTypeMatcher::describe($this->typeOf(static fn (int $p): mixed => $p), self::class));
        self::assertSame('?int', NativeTypeMatcher::describe($this->typeOf(static fn (?int $p): mixed => $p), self::class));
        self::assertSame('mixed', NativeTypeMatcher::describe($this->typeOf(static fn (mixed $p): mixed => $p), self::class));

        // A standalone `null` already carries its own nullability, so it must not gain a `?` prefix.
        self::assertSame('null', NativeTypeMatcher::describe($this->typeOf(static fn (null $p): mixed => $p), self::class));

        // Reflection fixes the member order and it differs by engine version, so the members are
        // asserted as a set - the separator and the two names are what the rendering owns.
        $union = NativeTypeMatcher::describe($this->typeOf(static fn (int|string $p): mixed => $p), self::class);

        self::assertSame(['int', 'string'], self::sortedParts($union, '|'));

        self::assertSame(
            ['ArrayAccess', 'Countable'],
            self::sortedParts(
                NativeTypeMatcher::describe($this->typeOf(static fn (Countable&ArrayAccess $p): mixed => $p), self::class),
                '&',
            ),
        );

        // A DNF type nests an intersection inside a union, and the intersection member has to come
        // back parenthesised or the rendering would read as a flat three-way union.
        $dnf = NativeTypeMatcher::describe(
            $this->typeOf(static fn ((Countable&ArrayAccess)|int $p): mixed => $p),
            self::class,
        );

        // The scalar arm renders bare; the intersection arm renders wrapped. Split on the union
        // separator and classify each arm so the assertion does not depend on reflection's order.
        $arms          = explode('|', $dnf);
        $bare          = [];
        $parenthesised = [];

        foreach ($arms as $arm) {
            if (str_starts_with($arm, '(')) {
                $parenthesised[] = $arm;
            } else {
                $bare[] = $arm;
            }
        }

        self::assertSame(['int'], $bare, 'The scalar arm renders bare.');
        self::assertCount(1, $parenthesised, 'The intersection arm is the only wrapped one.');
        self::assertSame(
            ['ArrayAccess', 'Countable'],
            self::sortedParts(trim($parenthesised[0], '()'), '&'),
            'Both intersection members survive inside the parentheses.',
        );
    }

    /**
     * Splits a rendered composite type on its separator and sorts the members, so an assertion can
     * pin the member set without depending on the order reflection happens to report.
     *
     * @param string           $rendered  The rendered type, for example `int|string`.
     * @param non-empty-string $separator The composite separator, `|` or `&`.
     *
     * @return list<string> The member names in a stable order.
     */
    private static function sortedParts(string $rendered, string $separator): array
    {
        $parts = explode($separator, $rendered);
        sort($parts);

        return $parts;
    }

    /**
     * Reflects the type of a closure's single parameter, the declaration the row carries.
     *
     * @param Closure $declaration A closure whose one parameter carries the declaration under test.
     *
     * @return ReflectionType The reflected type of that parameter.
     */
    private function typeOf(Closure $declaration): ReflectionType
    {
        $type = (new ReflectionFunction($declaration))->getParameters()[0]->getType();

        self::assertInstanceOf(ReflectionType::class, $type);

        // Guards a row against a silently untyped parameter that would make its assertion vacuous.
        if ($type instanceof ReflectionNamedType) {
            self::assertNotSame('', $type->getName());
        }

        return $type;
    }
}
