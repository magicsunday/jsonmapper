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
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\BaseCollection;
use MagicSunday\Test\JsonMapper\Stub\CountingPropertyListExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use function array_fill;

/**
 * Everything a class's shape determines - its property list, its replace map, its collector, its
 * constructor - is fixed by the declaration, yet it was re-derived through fresh reflection on
 * every mapSingleObject() call. For a collection that is once per ELEMENT: a hundred rows of one
 * class asked the extractor a hundred times for the same answer.
 *
 * @internal
 */
final class ClassMetadataReuseTest extends TestCase
{
    #[Test]
    public function itDerivesAClassShapeOnceRegardlessOfElementCount(): void
    {
        $counter = new CountingPropertyListExtractor();
        $mapper  = new JsonMapper(
            new PropertyInfoExtractor([$counter], [new PhpDocExtractor()]),
            PropertyAccess::createPropertyAccessor(),
        );

        $mapper->map(array_fill(0, 50, ['name' => 'x']), Base::class, BaseCollection::class);

        // One class, one derivation - not one per row. Asserted as an absolute rather than as
        // "fewer than before", because a count that merely shrank would still grow with the
        // payload and the point is that it must not.
        self::assertSame(1, $counter->calls, 'The property list is derived once for the class.');
    }

    #[Test]
    public function itDerivesEachDistinctClassOnce(): void
    {
        // The counterpart: caching per class must not collapse into caching one class. Without
        // this, a memo keyed on nothing at all would satisfy the test above.
        $counter = new CountingPropertyListExtractor();
        $mapper  = new JsonMapper(
            new PropertyInfoExtractor([$counter], [new PhpDocExtractor()]),
            PropertyAccess::createPropertyAccessor(),
        );

        $mapper->map(array_fill(0, 5, ['name' => 'x']), Base::class, BaseCollection::class);
        $mapper->map(['name' => 'y'], Base::class);

        self::assertSame(1, $counter->calls, 'The same class is still derived only once.');
    }
}
