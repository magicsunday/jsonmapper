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
use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\BaseCollection;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the entry point does with a payload whose shape does not match the classes it was given.
 *
 * One edge changed: a SCALAR handed over with a collection class silently dropped that class and
 * returned a bare element built from nothing, so a caller who type-hinted the collection got a
 * TypeError from its own code, far from the cause. It is now reported.
 *
 * Two edges deliberately did NOT change, and are pinned here so they do not read as oversights -
 * both were implemented the other way and reverted:
 *   - an empty array without a collection class yields an instance, not an empty list
 *   - an object with a collection class is read as a single element
 *
 * A third case is fixed alongside: a null with a collection class now honours
 * treatNullAsEmptyCollection on this lane as it already did on the generic one.
 *
 * @internal
 */
final class PayloadShapeEdgeCaseTest extends TestCase
{
    #[Test]
    public function itMapsAnEmptyArrayToAnInstanceWithoutACollectionClass(): void
    {
        // Deliberate, and pinned because it looks like the inconsistency next to it: an empty array
        // yields an instance while a one-entry list yields a list. Resolving that the other way was
        // implemented and reverted - it is genuinely ambiguous, since json_decode(associative:true)
        // renders both [] and {} as an empty array, and a caller passing associative arrays
        // directly means an empty OBJECT by it far more often. map([], Dto::class) is how a caller
        // asks for a DTO built from defaults, and a good number of existing tests rely on exactly
        // that - stated without a count, which would only rot.
        //
        // A caller who does mean an empty list names the collection class - see below.
        $result = $this->getJsonMapper()->map([], Base::class);

        self::assertInstanceOf(Base::class, $result);
    }

    #[Test]
    public function itMapsAnEmptyListToAnEmptyCollectionWhenOneIsRequested(): void
    {
        $result = $this->getJsonMapper()->map($this->getJsonAsObject('[]'), Base::class, BaseCollection::class);

        self::assertInstanceOf(BaseCollection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonCollectionPayloadProvider(): array
    {
        // Scalars only. An OBJECT against a requested collection is deliberately still accepted as
        // a single element (see itStillReadsAnObjectAsASingleElement), and a NULL means "no
        // collection" rather than a wrong shape (see itHonoursTheEmptyCollectionOptionForANull).
        //
        // The expected type travels with each row: without it every row asserts the same thing and
        // an exception naming the wrong type would satisfy all three.
        return [
            'string' => ['"oops"', 'string'],
            'number' => ['42', 'int'],
            'bool'   => ['true', 'bool'],
        ];
    }

    /**
     * @param string $json         Payload that is not a collection
     * @param string $expectedType Type the record must name
     */
    #[Test]
    #[DataProvider('nonCollectionPayloadProvider')]
    public function itReportsAPayloadThatIsNotTheRequestedCollection(string $json, string $expectedType): void
    {
        // Asking for a collection and receiving something that is not one used to yield a bare
        // element, with the collection class silently dropped. A caller who type-hinted the
        // collection got a TypeError from its own code, at a point far from the cause.
        $result = $this->getJsonMapper()->mapWithReport(
            $this->getJsonAsObject($json),
            Base::class,
            BaseCollection::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One payload that is not a collection, one record.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(CollectionMappingException::class, $exception);

        // The record's CONTENTS, not just its type. getActualType() is public API a caller may
        // surface, and without these a record naming the wrong path and the wrong type would
        // satisfy every row identically.
        self::assertSame($expectedType, $exception->getActualType());
        self::assertSame('$', $exception->getPath(), 'The failure is at the root.');
        self::assertNull($result->getValue(), 'And no half-answer of the wrong type.');
    }

    #[Test]
    public function itHonoursTheEmptyCollectionOptionForANull(): void
    {
        // A null is not a wrong shape, it is an absent one, and the generic lane already answered
        // it by honouring this option. The first version of the scalar guard swallowed null too -
        // is_array/is_object are both false for it - which left the option silently inert here, so
        // the same payload and configuration yielded an empty collection or a hard failure
        // depending only on whether an element class was also passed.
        $result = $this->getJsonMapper(
            config: JsonMapperConfiguration::lenient()->withTreatNullAsEmptyCollection(true),
        )->map(null, Base::class, BaseCollection::class);

        self::assertInstanceOf(BaseCollection::class, $result);
        self::assertCount(0, $result);
    }

    #[Test]
    public function itLeavesANullWithoutTheEmptyCollectionOptionAsItWas(): void
    {
        // Unchanged, and pinned only so the option's effect above is measured against something.
        // With the option off the collection lane declines and map() falls through to the
        // single-object lane, which builds an instance from a null payload. That is odd on its
        // own terms, but it is the behaviour before this change and outside what #68 asks about,
        // so it is recorded rather than quietly altered while nearby code moved.
        $result = $this->getJsonMapper()->map(null, Base::class, BaseCollection::class);

        self::assertInstanceOf(Base::class, $result);
    }

    #[Test]
    public function itStillReadsAnObjectAsASingleElement(): void
    {
        // The boundary of the rejection above, and the reason it is drawn at scalars rather than at
        // "not a list". Handing over both class names and letting the shape decide is how a caller
        // consumes an API that returns one object for a single hit and a list for several; that
        // has been pinned by mapSingleObjectWithGivenCollection() since long before this change.
        $result = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"name": "a"}'),
            Base::class,
            BaseCollection::class,
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('a', $result->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unmappableListProvider(): array
    {
        return [
            'all scalars' => ['[1, 2, 3]'],
            'mixed'       => ['[{"name": "a"}, "oops"]'],
        ];
    }

    /**
     * @param string $json List whose entries cannot be the requested element type
     */
    #[Test]
    #[DataProvider('unmappableListProvider')]
    public function itReportsAListWhoseEntriesCannotBeTheElementType(string $json): void
    {
        // The same defect one level down, and the scalar guard did not catch it because the
        // payload IS an array. A list whose entries are not objects cannot be a collection of
        // them, so it fell through to the single-object lane and was read as one Base built from
        // the list itself - silently, and in the mixed case losing the entry that WAS mappable.
        //
        // An object payload stays exempt: its values being scalars is what an object looks like.
        // The discriminator is whether the payload is a LIST, which is a claim about its shape
        // rather than about its contents.
        $result = $this->getJsonMapper()->mapWithReport(
            $this->getJsonAsObject($json),
            Base::class,
            BaseCollection::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One unusable list, one record.');
        self::assertInstanceOf(CollectionMappingException::class, $errors[0]->getException());
        self::assertNull($result->getValue(), 'And no single object built from a list.');
    }

    #[Test]
    public function itStillMapsAListIntoTheRequestedCollection(): void
    {
        // The control: the rejection above must not swallow the case the collection class exists
        // for. The ELEMENTS are asserted, not just the container - a collection filled with raw
        // unmapped payload satisfies an instance-and-count check while mapping nothing at all.
        $result = $this->getJsonMapper()->map(
            $this->getJsonAsObject('[{"name": "a"}, {"name": "b"}]'),
            Base::class,
            BaseCollection::class,
        );

        self::assertInstanceOf(BaseCollection::class, $result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);

        $first = $result[0];

        self::assertInstanceOf(Base::class, $first);
        self::assertSame('a', $first->name);
    }
}
