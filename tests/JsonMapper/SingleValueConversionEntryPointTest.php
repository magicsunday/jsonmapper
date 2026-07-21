<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\Test\Classes\NestedCollectionHolder;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Value conversion has one policy-holding entry point, and a collection element goes through the
 * same one a top-level property does. The null policy, the null guard and union dispatch that
 * decide a property's value must decide an element's value identically - a null collection element
 * cannot be answered differently from a null collection property just because it was reached one
 * layer down.
 *
 * @internal
 */
final class SingleValueConversionEntryPointTest extends TestCase
{
    #[Test]
    public function itAppliesTheTreatNullAsEmptyCollectionPolicyToANestedCollectionElement(): void
    {
        // A property whose element type is itself a collection: the middle element is a null
        // COLLECTION. The treat-null-as-empty-collection policy answers a null collection with an
        // empty one on a top-level property; an element reached through the collection factory must
        // get the same answer, not be dropped as an unconvertible value.
        $configuration = (new JsonMapperConfiguration())->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper()->mapWithReport(
            ['rows' => [[['name' => 'a']], null, [['name' => 'b']]]],
            NestedCollectionHolder::class,
            null,
            $configuration,
        );

        self::assertFalse(
            $result->getReport()->hasErrors(),
            'The null element is answered by the policy, not recorded as a fault.',
        );

        $holder = $result->getValue();

        self::assertInstanceOf(NestedCollectionHolder::class, $holder);
        self::assertCount(3, $holder->rows, 'The null element becomes an empty collection, not a gap.');
        self::assertSame([], $holder->rows[1], 'The policy answered the null element with an empty collection.');
        self::assertContainsOnlyInstancesOf(Simple::class, $holder->rows[0]);
        self::assertSame('a', $holder->rows[0][0]->name);
        self::assertSame('b', $holder->rows[2][0]->name);
    }

    #[Test]
    public function itRejectsANullNestedCollectionElementWithoutThePolicy(): void
    {
        // The control: with the policy off, a null collection element is a genuine fault, reported
        // and dropped exactly as a null value of any non-nullable element type is. This is what
        // proves the test above pins the POLICY, not merely that nulls are tolerated.
        $result = $this->getJsonMapper()->mapWithReport(
            ['rows' => [[['name' => 'a']], null, [['name' => 'b']]]],
            NestedCollectionHolder::class,
        );

        self::assertCount(1, $result->getReport()->getErrors(), 'The null element is the only fault.');

        $holder = $result->getValue();

        self::assertInstanceOf(NestedCollectionHolder::class, $holder);
        self::assertCount(2, $holder->rows, 'The rejected element is dropped, its siblings survive.');
    }
}
