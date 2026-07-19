<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\VipPerson;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The constructor $classMap accepts a static SdkFoo => Foo mapping; addCustomClassMapEntry() now
 * accepts one too, so a static mapping can be registered at runtime without wrapping it in a
 * trivial closure. This drives that through the PUBLIC entry point - the widened ClassResolver::add()
 * would otherwise be reachable only from a unit test.
 *
 * @internal
 */
final class StaticClassMapEntryTest extends TestCase
{
    #[Test]
    public function itMapsThroughAStaticClassStringEntry(): void
    {
        $result = $this->getJsonMapper()
            ->addCustomClassMapEntry(Person::class, VipPerson::class)
            ->map($this->getJsonAsObject('{"name": "a", "oscars": 3}'), Person::class);

        self::assertInstanceOf(VipPerson::class, $result, 'The static target class is instantiated.');
        self::assertSame('a', $result->name);
        self::assertSame(3, $result->oscars);
    }
}
