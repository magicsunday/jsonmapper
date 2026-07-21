<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use ArrayIterator;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Fixtures\Shapes\AccessorOnlyHolder;
use MagicSunday\Test\Fixtures\Shapes\NullTypedHolder;
use MagicSunday\Test\Fixtures\Shapes\VariadicConstructorHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Shapes a payload or a target can have that the common path never produces: a payload that is
 * traversable rather than an array, keys that are integers rather than names, a property reachable
 * only through its setter, a constructor tail that consumes "the rest", and a property declaring
 * the null type itself. Each is legal, and each takes its own branch through the mapper.
 *
 * @internal
 */
final class UncommonTargetShapeTest extends TestCase
{
    #[Test]
    public function itMapsAPayloadThatIsTraversableRatherThanAnArray(): void
    {
        // Callers do not only hand over json_decode() output. A Traversable is materialised once
        // and mapped like any object payload, rather than being read as a scalar the target cannot
        // be built from.
        $result = $this->getJsonMapper()->map(
            new ArrayIterator(['name' => 'from an iterator']),
            Base::class,
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('from an iterator', $result->name);
    }

    #[Test]
    public function itSkipsPayloadKeysThatAreNotPropertyNames(): void
    {
        // A list handed to a single-object mapping has integer keys, and an integer names no
        // property. Skipped rather than passed on: a name converter and a property lookup both
        // expect a string, and there is nothing to look up anyway.
        $result = $this->getJsonMapper()->map(['first', 'second'], Base::class);

        self::assertInstanceOf(Base::class, $result);
        self::assertNull($result->name, 'Nothing was mapped, and nothing crashed.');
    }

    #[Test]
    public function itWritesAPropertyThatExistsOnlyAsAnAccessorPair(): void
    {
        // PropertyInfo reports the property because the accessors describe one; reflection finds
        // no property of that name. The write therefore has to go through the setter, and the
        // readonly check that precedes it has to tolerate having no property to inspect.
        $result = $this->getJsonMapper()->map(['label' => 'written'], AccessorOnlyHolder::class);

        self::assertInstanceOf(AccessorOnlyHolder::class, $result);
        self::assertSame('written', $result->getLabel());
    }

    #[Test]
    public function itBuildsAnObjectWhoseConstructorEndsInAVariadic(): void
    {
        // A variadic parameter consumes "the rest", and a JSON object has no key that means that.
        // It is skipped, so the object is built from the parameters that do have names - rather
        // than the mapper inventing an argument for it.
        $result = $this->getJsonMapper()->map(['id' => 7], VariadicConstructorHolder::class);

        self::assertInstanceOf(VariadicConstructorHolder::class, $result);
        self::assertSame(7, $result->id);
        self::assertSame([], $result->tags);
    }

    #[Test]
    public function itAnswersAPropertyDeclaredAsTheNullTypeWithNothing(): void
    {
        // The null type has exactly one value, so there is nothing to convert towards. Answered
        // with null rather than run through the strategy chain, which would report every payload
        // as a mismatch against a type nothing can satisfy.
        $result = $this->getJsonMapper()->mapWithReport(['nothing' => 'anything'], NullTypedHolder::class);

        self::assertFalse($result->getReport()->hasErrors());

        $mapped = $result->getValue();

        self::assertInstanceOf(NullTypedHolder::class, $mapped);
        self::assertNull($mapped->nothing);
    }
}
