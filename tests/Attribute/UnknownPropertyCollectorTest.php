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
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\SnakeCaseKnownPropertyCollectorEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorDuplicateEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorHiddenEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorInvalidEntity;
use MagicSunday\Test\Classes\UnknownPropertyCollectorParent;
use MagicSunday\Test\Classes\UnknownPropertyCollectorStaticEntity;
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
     * Unknown source keys are collected, by their original payload key and raw value, into the
     * marked property while the known property is mapped normally.
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
     * The collector preserves the ORIGINAL payload key, not the converted one. The whole suite runs
     * with the camelCase converter active, so a snake_case unknown key is the case that
     * distinguishes the two: `favourite_colour` must be kept as it arrived, because the collector's
     * promise is a faithful copy of the unmapped part of the payload and a property-name converter
     * has no business rewriting a key that matches no property. The value is kept raw either way.
     */
    #[Test]
    public function preservesTheOriginalSnakeCaseKeyRatherThanTheConvertedOne(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'favourite_colour' => 'green', 'city' => 'London'],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result);
        self::assertSame('Ada', $result->name);
        self::assertSame(
            ['favourite_colour' => 'green', 'city' => 'London'],
            $result->extra,
            'The snake_case key is kept verbatim, not camelised to favouriteColour.',
        );
    }

    /**
     * The counterpart: only the STORED key changed, not which keys count as unknown. `full_name`
     * still camelises onto the declared `fullName` and is mapped, while `home_city`, which converts
     * onto no property, is collected under its original spelling. Both keys are snake_case, so the
     * pair fails against the pre-change code rather than passing by conversion being a no-op.
     */
    #[Test]
    public function stillMapsASnakeCaseKeyThatConvertsOntoADeclaredProperty(): void
    {
        $result = $this->getJsonMapper()->map(
            ['full_name' => 'Ada Lovelace', 'home_city' => 'London'],
            SnakeCaseKnownPropertyCollectorEntity::class,
        );

        self::assertInstanceOf(SnakeCaseKnownPropertyCollectorEntity::class, $result);
        self::assertSame('Ada Lovelace', $result->fullName, 'full_name mapped onto fullName.');
        self::assertSame(
            ['home_city' => 'London'],
            $result->extra,
            'The unknown snake_case key is collected verbatim, not as homeCity.',
        );
    }

    /**
     * Storing under the original spelling removes a collision that used to lose data: two payload
     * keys that normalise to the same unknown name are now two entries, where the converted key
     * previously merged them and the last one won. Ordinary mapping still collides - only the
     * collector, whose promise is a verbatim copy, does not.
     */
    #[Test]
    public function keepsBothSpellingsWhenTwoUnknownKeysNormaliseToTheSameName(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'favourite_colour' => 'green', 'favouriteColour' => 'blue'],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $result);
        self::assertSame(
            ['favourite_colour' => 'green', 'favouriteColour' => 'blue'],
            $result->extra,
            'Both spellings survive; neither is lost to the other.',
        );
    }

    /**
     * Strict mode and the collector answer the same question in opposite directions, and the
     * collector wins: a key it has taken is no longer unknown, so reporting it would ask the
     * caller to fix a payload the class explicitly asked to receive.
     */
    #[Test]
    public function doesNotReportKeysTheCollectorTookEvenInStrictMode(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['name' => 'Ada', 'age' => '36', 'city' => 'London'],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertFalse($result->getReport()->hasErrors(), 'Collected is not unknown.');

        $mapped = $result->getValue();

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $mapped);
        self::assertSame(['age' => '36', 'city' => 'London'], $mapped->extra);
    }

    /**
     * The other direction: the collector property is one the payload is not expected to supply, so
     * strict mode must not report it as missing either. It has a default, and that default is what
     * an absent collector means.
     */
    #[Test]
    public function doesNotReportTheCollectorItselfAsMissingInStrictMode(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['name' => 'Ada'],
            UnknownPropertyCollectorEntity::class,
        );

        self::assertFalse($result->getReport()->hasErrors());

        $mapped = $result->getValue();

        self::assertInstanceOf(UnknownPropertyCollectorEntity::class, $mapped);
        self::assertSame(['_default' => true], $mapped->extra, 'And it keeps its default.');
    }

    /**
     * The discriminator for both: without a collector, the very same unknown key IS reported - so
     * the two assertions above pin the collector rather than strict mode being inert.
     */
    #[Test]
    public function stillReportsAnUnknownKeyOnAClassWithoutACollector(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['name' => 'Ada', 'city' => 'London'],
            Simple::class,
        );

        self::assertTrue($result->getReport()->hasErrors());
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
     * Even when the collector property is invisible to the extractor (a private promoted property),
     * a source key matching its name is not diverted into the collector — the explicit guard, not
     * the membership check, prevents the self-nesting here. The other unknown key is still collected.
     */
    #[Test]
    public function doesNotDivertTheCollectorsOwnKeyEvenWhenItIsNotAnExposedProperty(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'extra' => ['self' => 'x'], 'unknownKey' => 'y'],
            UnknownPropertyCollectorHiddenEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorHiddenEntity::class, $result);
        // Without the explicit guard, 'extra' would nest into itself: ['extra' => ['self' => 'x'], ...].
        self::assertSame(['unknownKey' => 'y'], $result->extra());
    }

    /**
     * A collector declared with a union that includes a non-array, non-null member (`array|int`) is
     * rejected: it could hold a scalar, which would be silently dropped when merging an explicit
     * value with the collected keys. A valid `array|null` reduces to `?array` and is accepted.
     */
    #[Test]
    public function rejectsAUnionCollectorWithANonArrayMember(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be array-typed/');

        $this->getJsonMapper()->map(
            ['name' => 'Ada', 'age' => '36'],
            UnknownPropertyCollectorUnionEntity::class,
        );
    }

    /**
     * When the payload carries both an explicit value for the collector property and other unknown
     * keys, the two are merged rather than the explicit value being overwritten and lost. The
     * explicit value carries an integer key, which is preserved (not re-indexed) — proving the merge
     * uses `array_replace` rather than `array_merge`.
     */
    #[Test]
    public function mergesAnExplicitCollectorValueWithCollectedUnknownKeys(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'extra' => ['5' => 'kept'], 'unknown_key' => 'collected'],
            UnknownPropertyCollectorTypedEntity::class,
        );

        self::assertInstanceOf(UnknownPropertyCollectorTypedEntity::class, $result);
        self::assertSame([5 => 'kept', 'unknown_key' => 'collected'], $result->extra);
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

    /**
     * A collector marked on a static property is rejected: a static property is shared, not a
     * per-instance sink, so it cannot be hydrated as one.
     */
    #[Test]
    public function rejectsAStaticCollectorProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not be static/');

        $this->getJsonMapper()->map(
            ['name' => 'Ada', 'unknownKey' => 'value'],
            UnknownPropertyCollectorStaticEntity::class,
        );
    }
}
