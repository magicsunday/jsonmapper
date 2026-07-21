<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\EntryPoint\AbstractShape;
use MagicSunday\Test\Fixtures\EntryPoint\Circle;
use MagicSunday\Test\Fixtures\EntryPoint\ShapeCollection;
use MagicSunday\Test\Fixtures\EntryPoint\Square;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Mapping a list onto an ABSTRACT element class is the ordinary way to consume a polymorphic API:
 * the class map is handed the whole element and decides which concrete subclass each one becomes.
 * The abstract base is never instantiated - it is only the declared element type - so a guard that
 * refuses it at the entry point breaks exactly the case the class map exists for.
 *
 * @internal
 */
final class PolymorphicCollectionTest extends TestCase
{
    /**
     * Resolves each ELEMENT to its concrete subclass, and leaves the whole-list resolution the
     * mapper does at the entry point on the abstract base - which is what the per-element lane
     * below then re-resolves. A list carries no "kind" of its own, so it falls through to the base.
     *
     * @param mixed $value Whole list at the entry point, or one element per iteration
     *
     * @return class-string Concrete subclass for an element, the abstract base for the list
     */
    private static function discriminator(mixed $value): string
    {
        if (!is_array($value)) {
            return AbstractShape::class;
        }

        return match ($value['kind'] ?? null) {
            'circle' => Circle::class,
            'square' => Square::class,
            default  => AbstractShape::class,
        };
    }

    #[Test]
    public function itMapsAListOntoAnAbstractElementClassViaTheClassMap(): void
    {
        $result = $this->getJsonMapper([AbstractShape::class => self::discriminator(...)])
            ->map(
                [
                    ['kind' => 'circle', 'name' => 'a', 'radius' => 3],
                    ['kind' => 'square', 'name' => 'b', 'edge' => 4],
                ],
                AbstractShape::class,
                ShapeCollection::class,
            );

        self::assertInstanceOf(ShapeCollection::class, $result);
        self::assertCount(2, $result);
        self::assertInstanceOf(Circle::class, $result[0]);
        self::assertSame(3, $result[0]->radius);
        self::assertInstanceOf(Square::class, $result[1]);
        self::assertSame(4, $result[1]->edge);
    }

    #[Test]
    public function itMapsAListOntoAnAbstractElementClassWithoutACollectionClass(): void
    {
        // The same lane, one argument fewer: a bare array of the abstract element type. The base
        // is still only the element type, still never instantiated.
        $result = $this->getJsonMapper([AbstractShape::class => self::discriminator(...)])
            ->map(
                [
                    ['kind' => 'circle', 'name' => 'a', 'radius' => 3],
                    ['kind' => 'square', 'name' => 'b', 'edge' => 4],
                ],
                AbstractShape::class,
            );

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(AbstractShape::class, $result);
        self::assertInstanceOf(Square::class, $result[1]);
    }
}
