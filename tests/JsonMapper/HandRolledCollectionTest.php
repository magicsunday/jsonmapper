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
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\BodyErrorCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\ConstructorFilledCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\HandRolledCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\HandRolledTagCollection;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\RequiredCtorCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\RequiredLabelCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\RequiredSecondArgumentCollectionHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Tag;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\ZeroArgumentConstructorCollectionHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

use function array_keys;
use function iterator_to_array;

/**
 * A container that keeps its elements in a declared storage property is still a collection. It is
 * recognised by implementing ArrayAccess and Traversable, and filled through offsetSet, so a
 * visible storage array no longer excludes it the way the old "declares no properties" rule did.
 * The Traversable-without-ArrayAccess control - a data object that must NOT be recognised as a
 * container - lives in SinglyNestedCollectionTest, which exercises it through the nested-property
 * path recognition actually runs on.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class HandRolledCollectionTest extends TestCase
{
    #[Test]
    public function itFillsAHandRolledCollectionThroughOffsetSet(): void
    {
        // The storage property is private and there is no constructor taking the elements, so the
        // only way the two tags can arrive is offsetSet. The element type comes from the class's
        // own `@implements` annotation, not from the holder property.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            HandRolledCollectionHolder::class,
        );

        self::assertInstanceOf(HandRolledCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        self::assertCount(2, $tags, 'Both list entries reached the container.');
        self::assertSame('php', $tags[0]->name);
        self::assertSame('json', $tags[1]->name);
    }

    #[Test]
    public function itPreservesStringKeysWhenFillingThroughOffsetSet(): void
    {
        // A keyed JSON object source reaches the offsetSet loop with its string keys, so offsetSet
        // must receive and preserve them. The list-based tests only exercise integer keys, so this
        // pins the key branch of `foreach ($elements as $key => $element)` separately.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": {"first": {"name": "php"}, "second": {"name": "json"}}}'),
            HandRolledCollectionHolder::class,
        );

        self::assertInstanceOf(HandRolledCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertSame(['first', 'second'], array_keys($tags), 'The string keys reached offsetSet and were preserved.');
        self::assertSame('php', $tags['first']->name);
        self::assertSame('json', $tags['second']->name);
    }

    #[Test]
    public function itReportsANullElementOfAHandRolledCollection(): void
    {
        // The element goes through the one value-conversion entry point, so a null element of a
        // non-nullable element type is reported and dropped, exactly as it is for any other
        // collection - the hand-rolled shape gains no exemption from the null guard.
        $result = $this->getJsonMapper()->mapWithReport(
            ['tags' => [['name' => 'php'], null, ['name' => 'json']]],
            HandRolledCollectionHolder::class,
        );

        self::assertCount(1, $result->getReport()->getErrors(), 'The null element is the only fault.');

        $holder = $result->getValue();

        self::assertInstanceOf(HandRolledCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertCount(2, $tags, 'The null element is dropped, its siblings survive.');
    }

    #[Test]
    public function itFillsAHandRolledCollectionThroughTheExplicitCollectionApi(): void
    {
        // The explicit-collection-class entry point (third map() argument) must fill the wrapper the
        // same way the property path does. It once passed the whole list to the constructor, which a
        // hand-rolled container has none for, so the elements were silently lost - now it routes
        // through the same offsetSet fill.
        $collection = $this->getJsonMapper()->map(
            $this->getJsonAsObject('[{"name": "php"}, {"name": "json"}]'),
            Tag::class,
            HandRolledTagCollection::class,
        );

        self::assertInstanceOf(HandRolledTagCollection::class, $collection);

        $tags = iterator_to_array($collection);

        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        self::assertCount(2, $tags, 'Both list entries reached the container through the explicit API.');
        self::assertSame('php', $tags[0]->name);
    }

    #[Test]
    public function itFillsACollectionWhoseConstructorRequiresItsElements(): void
    {
        // The other fill lane: a collection that cannot be constructed empty takes its elements
        // through the constructor, as every collection did before offsetSet population existed, so a
        // required-elements constructor does not regress into an ArgumentCountError.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            RequiredCtorCollectionHolder::class,
        );

        self::assertInstanceOf(RequiredCtorCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        self::assertCount(2, $tags, 'Both entries reached the constructor-filled collection.');
    }

    #[Test]
    public function itUsesTheConstructorWheneverItDeclaresAParameterNotOnlyWhenItRequiresOne(): void
    {
        // The fill decision keys on whether the constructor takes arguments at all, not on whether
        // it requires them. An ArrayObject subclass with an OPTIONAL array constructor accepts the
        // list that way and rejects offsetSet; routing it to the offsetSet lane - which a
        // "no required arguments" rule would - raises its guard. It must take the constructor.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            ConstructorFilledCollectionHolder::class,
        );

        self::assertInstanceOf(ConstructorFilledCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertCount(2, $tags, 'The collection was filled through its constructor, not offsetSet.');
    }

    #[Test]
    public function itFillsACollectionWhoseExplicitConstructorTakesNoArguments(): void
    {
        // The second half of the fill gate. A declared constructor that takes no arguments must route
        // to the offsetSet lane just as the no-constructor case does, so a container declaring an
        // explicit zero-parameter constructor is still filled element by element. Were the whole list
        // handed to that constructor, it would be ignored and the collection would arrive empty.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            ZeroArgumentConstructorCollectionHolder::class,
        );

        self::assertInstanceOf(ZeroArgumentConstructorCollectionHolder::class, $holder);

        $tags = iterator_to_array($holder->tags);

        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        self::assertCount(2, $tags, 'Both entries reached the collection through offsetSet.');
    }

    #[Test]
    public function itReportsACollectionWhoseConstructorCannotReceiveTheElements(): void
    {
        // A collection recognised by its interfaces (ArrayAccess + Traversable) whose constructor
        // requires an argument that is not the element list cannot be filled by the constructor lane.
        // Handing it the element array there would raise a native TypeError that escapes error
        // collection; the mapper reports the failure through the MappingException hierarchy instead.
        $result = $this->getJsonMapper()->mapWithReport(
            ['tags' => [['name' => 'php'], ['name' => 'json']]],
            RequiredLabelCollectionHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(
            1,
            $errors,
            'The unfillable collection is reported once, not crashed with a native error.',
        );

        // Pin the reported shape, not just the count, so the test cannot pass on a different error
        // recorded at count 1: it is a type mismatch at the collection property.
        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertStringContainsString('tags', $exception->getPath());
    }

    #[Test]
    public function itReportsACollectionWhoseConstructorRequiresASecondArgument(): void
    {
        // The other unfillable shape: the constructor takes the element list in its first parameter
        // but declares a second required parameter the single element-list argument cannot satisfy.
        // The mapper reports a missing constructor argument before calling the constructor, rather
        // than letting a native ArgumentCountError escape error collection.
        $result = $this->getJsonMapper()->mapWithReport(
            ['tags' => [['name' => 'php'], ['name' => 'json']]],
            RequiredSecondArgumentCollectionHolder::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'The unsatisfiable constructor is reported once, not crashed.');

        $exception = $errors[0]->getException();

        self::assertInstanceOf(MissingConstructorArgumentException::class, $exception);
        self::assertStringContainsString('tags', $exception->getPath());
    }

    #[Test]
    public function itLetsAConstructorBodyTypeErrorPropagate(): void
    {
        // A TypeError raised inside a recognised collection's constructor BODY is a bug in that
        // class, not a payload mismatch. The constructor parameter accepts the element list, so the
        // pre-check lets the call through and the body runs. Its own TypeError must propagate as
        // itself rather than be caught and re-labelled as a collection type mismatch, which would
        // bury the real defect.
        $this->expectException(TypeError::class);

        $this->getJsonMapper()->mapWithReport(
            ['tags' => [['name' => 'php'], ['name' => 'json']]],
            BodyErrorCollectionHolder::class,
        );
    }
}
