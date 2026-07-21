<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Collection;

use InvalidArgumentException;
use MagicSunday\JsonMapper\Collection\CollectionDocBlockTypeResolver;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\TagCollection;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\UnannotatedCollection;
use MagicSunday\Test\Fixtures\Docs\NestedCollections\UndocumentedCollection;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Location;
use phpDocumentor\Reflection\Types\Context;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type\CollectionType;

use function preg_quote;

/**
 * A collection class states what it holds in a docblock, and the ways that statement can be
 * missing are not one case but several - each of which the resolver has to answer with the same
 * actionable guidance rather than with a container of some invented element type.
 *
 * @internal
 */
final class CollectionDocBlockTypeResolverTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function containerWithoutElementTypeProvider(): array
    {
        return [
            // Two distinct paths through the reader, not one case twice: the first class has a
            // docblock the resolver parses and finds no element tag in, the second makes
            // getDocComment() answer false so there is nothing to parse at all.
            'docblock without an element tag' => [UnannotatedCollection::class],
            'no docblock at all'              => [UndocumentedCollection::class],
        ];
    }

    /**
     * @param class-string $collectionClassName Container that never says what it holds
     */
    #[Test]
    #[DataProvider('containerWithoutElementTypeProvider')]
    public function itResolvesNothingForAContainerThatNeverSaysWhatItHolds(string $collectionClassName): void
    {
        self::assertNull((new CollectionDocBlockTypeResolver())->resolve($collectionClassName));
    }

    /**
     * @param class-string $collectionClassName Container that never says what it holds
     */
    #[Test]
    #[DataProvider('containerWithoutElementTypeProvider')]
    public function itExplainsWhatAContainerWithoutAnElementTypeIsMissing(string $collectionClassName): void
    {
        // The guidance names the annotation and shows it filled in for this very class, because a
        // bare "cannot resolve" leaves the caller to guess which of the two spellings is meant.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('Define an "@extends" annotation such as "@extends ' . $collectionClassName . '<YourClass>"', '/') . '/'
        );

        (new CollectionDocBlockTypeResolver())->resolveOrFail($collectionClassName);
    }

    #[Test]
    public function itAnswersTheSameClassFromTheMemoRatherThanReadingItAgain(): void
    {
        // Reading a docblock tokenises the class file, with no cache anywhere in that path, so the
        // memo is what keeps a large collection from paying it per element. Asserted by identity:
        // an equal-but-rebuilt type would satisfy assertEquals while the reading still happened.
        $resolver = new CollectionDocBlockTypeResolver();

        $first  = $resolver->resolve(TagCollection::class);
        $second = $resolver->resolve(TagCollection::class);

        self::assertInstanceOf(CollectionType::class, $first);
        self::assertSame($first, $second);
    }

    #[Test]
    public function itAnswersTheMemoForAContainerThatResolvedToNothingToo(): void
    {
        // The null result is memoised as much as a resolved one - the arm that distinguishes
        // array_key_exists() from isset(). Observed by counting reads, not by the returned value:
        // a second read that ignored the memo would also return null, so asserting the value alone
        // could not tell a cached miss from a re-read. UnannotatedCollection is used rather than
        // UndocumentedCollection because it HAS a docblock the factory parses - so a re-read
        // actually calls create() - while still resolving to nothing.
        $factory  = new CountingDocBlockFactory();
        $resolver = new CollectionDocBlockTypeResolver($factory);

        self::assertNull($resolver->resolve(UnannotatedCollection::class));
        self::assertNull($resolver->resolve(UnannotatedCollection::class));
        self::assertSame(1, $factory->creates, 'The second call came from the memo, not a re-read.');
    }

    /**
     * @return array<string, array{Tag}>
     */
    public static function unusableElementTagProvider(): array
    {
        return [
            // A tag that is not typed at all, and one that is typed but carries no type. Both are
            // unreachable through phpDocumentor's own factory: it names a malformed "@extends"
            // with the at-sign included, so getTagsByName('extends') never returns one, and a
            // well-formed tag is always an Extends_ carrying a type.
            'a tag with no type at all' => [new Generic('extends')],
            'a typed tag without one'   => [
                new class extends TagWithType {
                    protected string $name = 'extends';
                },
            ],
        ];
    }

    /**
     * @param Tag $tag Element tag the injected factory hands back
     */
    #[Test]
    #[DataProvider('unusableElementTagProvider')]
    public function itSkipsAnElementTagItCannotReadAType(Tag $tag): void
    {
        // The docblock factory is a constructor dependency, so a consumer supplying their own
        // decides what comes back. Skipping is what leaves the class looking like a container
        // without an element type, which is a reported failure with actionable guidance;
        // dereferencing the missing type would be a native error instead.
        $resolver = new CollectionDocBlockTypeResolver(
            new class($tag) implements DocBlockFactoryInterface {
                public function __construct(private readonly Tag $tag)
                {
                }

                /**
                 * @param array<string, class-string<Tag>> $additionalTags Passed on to the real factory.
                 */
                public static function createInstance(array $additionalTags = []): DocBlockFactoryInterface
                {
                    return DocBlockFactory::createInstance($additionalTags);
                }

                /**
                 * @param object|string $docblock Ignored - the tag under test is fixed.
                 */
                public function create($docblock, ?Context $context = null, ?Location $location = null): DocBlock
                {
                    return new DocBlock('', null, [$this->tag]);
                }
            },
        );

        self::assertNull($resolver->resolve(TagCollection::class));
    }
}

/**
 * A docblock factory that parses for real but counts how often it was asked to, so a test can tell
 * a memo hit from a re-read.
 *
 * @internal
 */
final class CountingDocBlockFactory implements DocBlockFactoryInterface
{
    public int $creates = 0;

    private readonly DocBlockFactoryInterface $delegate;

    public function __construct()
    {
        $this->delegate = DocBlockFactory::createInstance();
    }

    /**
     * @param array<string, class-string<Tag>> $additionalTags Passed on to the real factory.
     */
    public static function createInstance(array $additionalTags = []): DocBlockFactoryInterface
    {
        return DocBlockFactory::createInstance($additionalTags);
    }

    /**
     * @param object|string $docblock Docblock text or reflector to parse.
     */
    public function create($docblock, ?Context $context = null, ?Location $location = null): DocBlock
    {
        ++$this->creates;

        return $this->delegate->create($docblock, $context, $location);
    }
}
