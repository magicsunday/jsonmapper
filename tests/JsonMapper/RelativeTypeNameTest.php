<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\Test\Classes\ParentTypedHolder;
use MagicSunday\Test\Classes\SelfTypedCollectionHolder;
use MagicSunday\Test\Classes\SelfTypedHolder;
use MagicSunday\Test\Classes\SelfTypedUnionHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

/**
 * A property typed by the keyword for its own or its parent class maps like one typed by the name.
 *
 * Reflection reports the keyword itself on some supported PHP versions and the class name on others,
 * so a mapper that took the keyword for a class name fails on one and not on the other.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class RelativeTypeNameTest extends TestCase
{
    #[Test]
    public function itMapsAPropertyTypedByTheKeywordForItsOwnClass(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(['name' => 'outer', 'other' => ['name' => 'inner']], SelfTypedHolder::class);

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(SelfTypedHolder::class, $value);
        self::assertInstanceOf(SelfTypedHolder::class, $value->other);
        self::assertSame('inner', $value->other->name);
    }

    #[Test]
    public function itMapsAPropertyTypedByTheKeywordForItsParentClass(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(['name' => 'child', 'ancestor' => ['name' => 'ancestor']], ParentTypedHolder::class);

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(ParentTypedHolder::class, $value);
        self::assertInstanceOf(SelfTypedHolder::class, $value->ancestor);
        self::assertSame(SelfTypedHolder::class, $value->ancestor::class, 'The keyword names the parent, not the class itself.');
        self::assertSame('ancestor', $value->ancestor->name);
    }

    #[Test]
    public function itResolvesTheKeywordOfAnInheritedPropertyAgainstTheClassThatDeclaresIt(): void
    {
        // "other" is declared by the parent, so the keyword stands for the parent even when the
        // property is read through the subclass.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(['name' => 'child', 'other' => ['name' => 'inner']], ParentTypedHolder::class);

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(ParentTypedHolder::class, $value);
        self::assertNotNull($value->other);
        self::assertSame(SelfTypedHolder::class, $value->other::class);
    }

    #[Test]
    public function itMapsAUnionThatNamesItsOwnClassByTheKeyword(): void
    {
        $holder = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(['either' => ['name' => 'inner']], SelfTypedUnionHolder::class);

        self::assertSame(0, $holder->getReport()->getErrorCount());

        $holderValue = $holder->getValue();

        self::assertInstanceOf(SelfTypedUnionHolder::class, $holderValue);
        self::assertInstanceOf(SelfTypedUnionHolder::class, $holderValue->either);

        $text = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(['either' => 'plain'], SelfTypedUnionHolder::class);

        self::assertSame(0, $text->getReport()->getErrorCount());

        $textValue = $text->getValue();

        self::assertInstanceOf(SelfTypedUnionHolder::class, $textValue);
        self::assertSame('plain', $textValue->either);
    }

    #[Test]
    public function itMapsACollectionOfTheKeywordForItsOwnClass(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient())
            ->mapWithReport(
                ['name' => 'outer', 'others' => [['name' => 'first'], ['name' => 'second']]],
                SelfTypedCollectionHolder::class,
            );

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(SelfTypedCollectionHolder::class, $value);
        self::assertCount(2, $value->others);
        self::assertInstanceOf(SelfTypedCollectionHolder::class, $value->others[1]);
        self::assertSame('second', $value->others[1]->name);
    }
}
