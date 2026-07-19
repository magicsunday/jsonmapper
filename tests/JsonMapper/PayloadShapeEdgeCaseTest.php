<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\BaseCollection;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the entry point returns must depend on the SHAPE the caller asked for, not on how much data
 * happened to arrive.
 *
 * Two edges got that wrong. A list of one object mapped to a list while the same list emptied
 * mapped to a single object, so the return type changed with the row count - a caller iterating the
 * result crashed only once its data ran dry. And a payload that is not a collection at all silently
 * ignored the collection class it was handed, returning a bare element instead.
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
        // asks for a DTO built from defaults, and six existing tests rely on exactly that.
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
        // a single element - see itStillReadsAnObjectAsASingleElement below.
        return [
            'string' => ['"oops"'],
            'number' => ['42'],
            'bool'   => ['true'],
        ];
    }

    /**
     * @param string $json Payload that is not a collection
     */
    #[Test]
    #[DataProvider('nonCollectionPayloadProvider')]
    public function itReportsAPayloadThatIsNotTheRequestedCollection(string $json): void
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
        self::assertInstanceOf(CollectionMappingException::class, $errors[0]->getException());
        self::assertNull($result->getValue(), 'And no half-answer of the wrong type.');
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

    #[Test]
    public function itStillMapsAListIntoTheRequestedCollection(): void
    {
        // The control: the rejection above must not swallow the case the collection class exists
        // for.
        $result = $this->getJsonMapper()->map(
            $this->getJsonAsObject('[{"name": "a"}, {"name": "b"}]'),
            Base::class,
            BaseCollection::class,
        );

        self::assertInstanceOf(BaseCollection::class, $result);
        self::assertCount(2, $result);
    }
}
