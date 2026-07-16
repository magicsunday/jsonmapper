<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Attribute;

use InvalidArgumentException;
use MagicSunday\Test\Classes\UnknownPropertyCollectorDuplicateEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorInvalidEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorParent;
use MagicSunday\Test\Classes\UnknownPropertyCollectorTypedEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorUnionEntity;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the {@see \MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector} attribute: a property
 * marked with it receives every source key that matches no declared property, so a caller can
 * preserve unmodelled input instead of losing it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnknownPropertyCollectorTest extends TestCase
{
    /**
     * Unknown source keys are collected, by their normalized name and raw value, into the marked
     * property while the known property is mapped normally.
     */
    #[Test]
    public function collectsUnknownKeysIntoTheMarkedProperty(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'age' => '36', 'city' => 'London'],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result);
        self::assertSame('Ada', $result->name);
        self::assertSame(['age' => '36', 'city' => 'London'], $result->extra);
    }

    /**
     * With no unknown keys the marked property keeps its (distinguishable) constructor default
     * rather than being assigned an empty collection — so the "assign only when something was
     * gathered" guard is genuinely exercised.
     */
    #[Test]
    public function leavesTheMarkedPropertyDefaultWhenNoUnknownKeys(): void
    {
        $result = $this->getJsonMapper()->map(['name' => 'Ada'], UnknownPropertyCollectorEntity::class);

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result);
        self::assertSame('Ada', $result->name);
        self::assertSame(['_default' => true], $result->extra);
    }

    /**
     * A structured (nested-array) unknown value is collected intact, so preservation is not limited
     * to scalar leaves — the whole subtree survives verbatim.
     */
    #[Test]
    public function collectsStructuredUnknownValuesIntact(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'meta' => ['count' => '5', 'deep' => ['flag' => 'on']]],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result);
        self::assertSame(['meta' => ['count' => '5', 'deep' => ['flag' => 'on']]], $result->extra);
    }

    /**
     * The collector fires per level: with a collector on BOTH the parent and the nested child, a
     * parent-level unknown key lands only on the parent's collector and a child-level unknown key
     * lands only on the child's — neither leaks across the boundary.
     */
    #[Test]
    public function collectsUnknownKeysPerLevelAcrossNesting(): void
    {
        $result = $this->getJsonMapper()->map(
            [
                'title'         => 'Root',
                'parentUnknown' => 'top',
                'child'         => ['name' => 'Ada', 'extraKey' => 'kept'],
            ],
            UnknownPropertyCollectorParent::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorParent::class, $result);
        self::assertSame('Root', $result->title);

        // The parent-level unknown lands on the parent's collector, and the child's unknown does not.
        self::assertSame(['parentUnknown' => 'top'], $result->rest);

        // The child-level unknown lands on the child's collector, and the parent's unknown does not.
        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result->child);
        self::assertSame('Ada', $result->child->name);
        self::assertSame(['extraKey' => 'kept'], $result->child->extra);
    }

    /**
     * A source key whose name equals the collector property is a declared property, so it is mapped
     * normally rather than diverted and nested into the collector map — the invariant that lets the
     * diversion rely on the declared-property membership check alone.
     */
    #[Test]
    public function treatsAnExplicitCollectorKeyAsANormalPropertyNotDivertedIntoItself(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'extra' => ['x' => '1']],
            UnknownPropertyCollectorTypedEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorTypedEntity::class, $result);
        // Mapped as the declared property; a self-diversion would instead yield ['extra' => ['x' => '1']].
        self::assertSame(['x' => '1'], $result->extra);
    }

    /**
     * A collector declared with the union type `array|null` (rather than `?array`) is honoured, not
     * rejected as non-array — the union member `array` satisfies the type requirement.
     */
    #[Test]
    public function acceptsAUnionTypedArrayCollector(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'age' => '36'],
            UnknownPropertyCollectorUnionEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorUnionEntity::class, $result);
        self::assertSame(['age' => '36'], $result->extra);
    }

    /**
     * When the payload carries both an explicit value for the collector property and other unknown
     * keys, the two are merged rather than the explicit value being overwritten and lost.
     */
    #[Test]
    public function mergesAnExplicitCollectorValueWithCollectedUnknownKeys(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'extra' => ['explicit' => 'kept'], 'unknownKey' => 'collected'],
            UnknownPropertyCollectorTypedEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorTypedEntity::class, $result);
        self::assertSame(['explicit' => 'kept', 'unknownKey' => 'collected'], $result->extra);
    }

    /**
     * A collector marked on a non-array property is rejected up front with a clear message, rather
     * than deferring to a late native TypeError when the raw map is assigned.
     */
    #[Test]
    public function rejectsANonArrayCollectorProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be array-typed/');

        $this->getJsonMapper()->map(
            ['name' => 'Ada', 'age' => '36'],
            UnknownPropertyCollectorInvalidEntity::class,
        );
    }

    /**
     * A class that marks more than one property as the collector is rejected up front rather than
     * silently honouring only the first.
     */
    #[Test]
    public function rejectsMoreThanOneCollectorPerClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/more than one property/');

        $this->getJsonMapper()->map(
            ['unknownKey' => 'value'],
            UnknownPropertyCollectorDuplicateEntity::class,
        );
    }
}
