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
use MagicSunday\Test\Classes\SelfTypedHolder;
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
        self::assertSame('ancestor', $value->ancestor->name);
    }
}
