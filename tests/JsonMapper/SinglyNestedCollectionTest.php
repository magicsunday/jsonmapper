<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use InvalidArgumentException;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Author;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\CollectionShapesHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\EncapsulatedIterableHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\IterableDataObjectHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\MoneyBagTypeHandler;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\MoneyHolder;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\SinglyNestedArticle;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\Tag;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\TagInterface;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_keys;
use function array_map;
use function preg_quote;

/**
 * A property typed with a collection class whose element type is advertised by the class's own
 * "extends" annotation - the most common collection shape there is, and the one the suite missed.
 *
 * Two green paths sidestepped it. The recipe test exercises the DOUBLY nested shape, and the
 * repository's own collection fixtures name the element type on the PROPERTY docblock rather than
 * relying on the collection class. Neither reaches the resolution this pins, so the shape both the
 * README and the recipe document went untested.
 *
 * @internal
 */
final class SinglyNestedCollectionTest extends TestCase
{
    #[Test]
    public function itMapsACollectionPropertyWhoseElementTypeComesFromTheClassAnnotation(): void
    {
        $article = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}, {"name": "json"}]}'),
            SinglyNestedArticle::class,
        );

        self::assertInstanceOf(SinglyNestedArticle::class, $article);

        // No assertion that $article->tags is a TagCollection: the native property type already
        // guarantees that, and the defect was never a wrong type landing there. It was the raw
        // array reaching the property accessor, which threw a foreign InvalidTypeException -
        // outside the MappingException hierarchy, so mapWithReport() could not report it either.
        // Reaching these assertions at all is the proof; their content pins the element mapping.
        self::assertCount(2, $article->tags);
        self::assertContainsOnlyInstancesOf(Tag::class, $article->tags);

        // Asserting the whole projection rather than element 0: a factory that mapped the first
        // element twice would satisfy the count, the element type and a single-element check.
        self::assertSame(
            ['php', 'json'],
            array_map(
                static fn (Tag $tag): string => $tag->name,
                $article->tags->getArrayCopy(),
            ),
            'Both payload elements map, in payload order.',
        );
    }

    #[Test]
    public function itLeavesAnIterableDataObjectToTheObjectStrategy(): void
    {
        // The negative control. Recognising a collection by its element annotation alone would
        // claim this too - it implements IteratorAggregate and says what it yields - and the
        // factory would build it from the payload's elements, dropping both properties while
        // still producing an object of the right class. The failure is silent by construction,
        // which is why the positive test alone is not enough.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"payload": {"title": "hello", "count": 7}}'),
            IterableDataObjectHolder::class,
        );

        self::assertInstanceOf(IterableDataObjectHolder::class, $holder);
        self::assertSame('hello', $holder->payload->title);
        self::assertSame(7, $holder->payload->count);
    }

    #[Test]
    public function itLeavesAnEncapsulatedIterableDataObjectToTheObjectStrategy(): void
    {
        // The sibling fixture declares PUBLIC properties, so it only exercises half the container
        // check - narrowing the check to public properties left it green. A DTO keeping its state
        // private behind accessors is the more common shape, and would be hijacked and emptied
        // just as silently.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"payload": {"title": "hello"}}'),
            EncapsulatedIterableHolder::class,
        );

        self::assertInstanceOf(EncapsulatedIterableHolder::class, $holder);
        self::assertSame('hello', $holder->payload->getTitle());
    }

    #[Test]
    public function itKeepsTheKeyTypeDeclaredByTheClassAnnotation(): void
    {
        // What this really pins is the map-shaped (non-list) payload path: a JSON object whose
        // keys must survive into the collection. It does NOT discriminate the key half of the
        // re-wrap - the factory copies keys from the source verbatim and consults only the value
        // type, so dropping the key type from the annotation changes nothing observable. Reversing
        // the type parameters is caught, but by the value half.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"keyed": {"php": {"name": "php"}, "json": {"name": "json"}}}'),
            CollectionShapesHolder::class,
        );

        self::assertInstanceOf(CollectionShapesHolder::class, $holder);
        self::assertSame(['php', 'json'], array_keys($holder->keyed->getArrayCopy()));
        self::assertContainsOnlyInstancesOf(Tag::class, $holder->keyed);
    }

    #[Test]
    public function itNamesTheMissingAnnotationOnAContainerThatDeclaresNoElementType(): void
    {
        // A container that never says what it holds cannot be filled. Falling through handed the
        // raw array to the property accessor, which rejected it with a Symfony exception naming
        // neither the annotation nor what to do about it. This is a defect in the class
        // definition rather than in the payload, so it surfaces as an exception in both modes -
        // the same one, with the same guidance, that the top-level entry point already gives.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('Define an "@extends" annotation', '/') . '/',
        );

        $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"unannotated": [{"name": "php"}]}'),
            CollectionShapesHolder::class,
        );
    }

    #[Test]
    public function itRaisesTheMissingAnnotationEvenUnderMapWithReport(): void
    {
        // Deliberate, and stated here because the sibling test cannot show it: a class that never
        // declares what it holds is a defect in the code rather than in the payload, so it is
        // fatal in both modes rather than collected. Lenient mode absorbs bad DATA; it does not
        // absorb a type definition the mapper cannot act on.
        $this->expectException(InvalidArgumentException::class);

        $this->getJsonMapper()->mapWithReport(
            ['unannotated' => [['name' => 'php']]],
            CollectionShapesHolder::class,
        );
    }

    #[Test]
    public function itNamesTheTemplateParameterOnACollectionThatNeverResolvesIt(): void
    {
        // The annotation parses and yields a collection type, so the "declares nothing" guard
        // does not catch this - but the element type is a template parameter, which no payload
        // can satisfy. Without its own check it reached the factory and died on a message naming
        // neither the annotation nor the fix.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('provide a concrete class in the "@extends" annotation', '/') . '/',
        );

        $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"templated": [{"name": "php"}]}'),
            CollectionShapesHolder::class,
        );
    }

    #[Test]
    public function itLetsARegisteredHandlerWinOverTheContainerHeuristic(): void
    {
        // addType() is the documented escape hatch, so it has to outrank a strategy that
        // recognises a collection by its shape. MoneyBag is traversable and declares no
        // properties, so the heuristic claims it - and used to, ahead of the handler, turning a
        // registered converter into an exception about a missing annotation.
        $mapper = $this->getJsonMapper();
        $mapper->addTypeHandler(new MoneyBagTypeHandler());

        $holder = $mapper->map(
            $this->getJsonAsObject('{"bag": {"amount": 5}}'),
            MoneyHolder::class,
        );

        self::assertInstanceOf(MoneyHolder::class, $holder);
        self::assertSame(5, $holder->bag->amount);
    }

    #[Test]
    public function itResolvesEachCollectionClassToItsOwnElementType(): void
    {
        // The resolution is memoised per class name. Every other fixture holds Tag, so a memo
        // that ignored its key and served the first resolution to every later class would be
        // invisible - two collections with distinct element types in one payload is what makes
        // the key load-bearing.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": [{"name": "php"}], "authors": [{"alias": "rso"}]}'),
            CollectionShapesHolder::class,
        );

        self::assertInstanceOf(CollectionShapesHolder::class, $holder);
        self::assertContainsOnlyInstancesOf(Tag::class, $holder->tags);
        self::assertContainsOnlyInstancesOf(Author::class, $holder->authors);
    }

    #[Test]
    public function itAcceptsAnInterfaceAsTheElementType(): void
    {
        // A polymorphic collection names an interface and lets a class map pick the concrete
        // class at mapping time. Testing the element type with class_exists() alone refused it
        // here, before the map ever got a say.
        $holder = $this->getJsonMapper(classMap: [TagInterface::class => Tag::class])->map(
            $this->getJsonAsObject('{"polymorphic": [{"name": "php"}]}'),
            CollectionShapesHolder::class,
        );

        self::assertInstanceOf(CollectionShapesHolder::class, $holder);
        self::assertContainsOnlyInstancesOf(Tag::class, $holder->polymorphic);
    }

    #[Test]
    public function itMapsAnEmptyPayloadToAnEmptyCollection(): void
    {
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"tags": []}'),
            CollectionShapesHolder::class,
        );

        self::assertInstanceOf(CollectionShapesHolder::class, $holder);
        self::assertCount(0, $holder->tags, 'An empty list yields an empty collection instance.');
    }

    #[Test]
    public function itLeavesANullPayloadToTheNullStrategy(): void
    {
        // The null strategy is registered before this one, so it has to win even though the
        // collection strategy would now claim the type.
        $holder = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"optional": null}'),
            CollectionShapesHolder::class,
        );

        self::assertInstanceOf(CollectionShapesHolder::class, $holder);
        self::assertNull($holder->optional);
    }

    #[Test]
    public function itReportsAnElementThatCannotBeMapped(): void
    {
        // The other half of the defect. The foreign InvalidTypeException was not a MappingException,
        // so mapWithReport() could not collect it. A failure inside this shape must now surface as
        // a report entry rather than escaping.
        $result = $this->getJsonMapper()->mapWithReport(
            ['tags' => [['name' => ['nested' => true]]]],
            CollectionShapesHolder::class,
        );

        self::assertTrue(
            $result->getReport()->hasErrors(),
            'A failing element is reported rather than raising a foreign exception.',
        );
    }
}
