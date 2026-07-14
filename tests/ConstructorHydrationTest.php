<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\Test\Classes\CamelCaseReadonly;
use MagicSunday\Test\Classes\MixedConstructorEntity;
use MagicSunday\Test\Classes\NullableReadonly;
use MagicSunday\Test\Classes\ReadonlyCollectionHolder;
use MagicSunday\Test\Classes\ReadonlyEntity;
use MagicSunday\Test\Classes\ReadonlyHolder;
use MagicSunday\Test\Classes\ReadonlyValueObject;
use MagicSunday\Test\Classes\ReplaceNullReadonly;
use MagicSunday\Test\Classes\VariadicConstructor;
use PHPUnit\Framework\Attributes\Test;

use function preg_quote;

/**
 * Tests hydration of final readonly / promoted-constructor classes, which cannot be populated by
 * property assignment and must be built through their constructor.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ConstructorHydrationTest extends TestCase
{
    /**
     * A final readonly class with required promoted arguments is built via its constructor, with
     * each argument converted to its declared type.
     */
    #[Test]
    public function hydratesAReadonlyClassWithRequiredConstructorArguments(): void
    {
        $result = $this->getJsonMapper()->map(
            ['name' => 'Ada', 'age' => '36'],
            ReadonlyValueObject::class
        );

        self::assertInstanceOf(ReadonlyValueObject::class, $result);
        self::assertSame('Ada', $result->name);
        self::assertSame(36, $result->age, 'the string age is coerced to its declared int type');
    }

    /**
     * A mapped value overrides a readonly constructor default rather than being silently dropped.
     */
    #[Test]
    public function overridesAReadonlyConstructorDefault(): void
    {
        $result = $this->getJsonMapper()->map(['id' => 'mapped'], ReadonlyEntity::class);

        self::assertInstanceOf(ReadonlyEntity::class, $result);
        self::assertSame('mapped', $result->id);
    }

    /**
     * A missing value falls back to the constructor default.
     */
    #[Test]
    public function usesTheConstructorDefaultWhenAValueIsMissing(): void
    {
        $result = $this->getJsonMapper()->map([], ReadonlyEntity::class);

        self::assertInstanceOf(ReadonlyEntity::class, $result);
        self::assertSame('initial', $result->id);
    }

    /**
     * A nested readonly value object is hydrated recursively through the same constructor path.
     */
    #[Test]
    public function hydratesNestedReadonlyObjectsRecursively(): void
    {
        $result = $this->getJsonMapper()->map(
            [
                'label' => 'holder',
                'value' => ['name' => 'Ada', 'age' => 36],
            ],
            ReadonlyHolder::class
        );

        self::assertInstanceOf(ReadonlyHolder::class, $result);
        self::assertSame('holder', $result->label);
        self::assertSame('Ada', $result->value->name);
        self::assertSame(36, $result->value->age);
    }

    /**
     * A missing nullable argument without a default falls back to null rather than failing.
     */
    #[Test]
    public function fallsBackToNullForAMissingNullableArgument(): void
    {
        $result = $this->getJsonMapper()->map([], NullableReadonly::class);

        self::assertInstanceOf(NullableReadonly::class, $result);
        self::assertNull($result->note);
    }

    /**
     * A missing required, non-nullable argument fails loudly instead of constructing an invalid
     * object.
     */
    #[Test]
    public function throwsWhenARequiredArgumentIsMissing(): void
    {
        $this->expectException(MissingConstructorArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote('Missing required constructor argument ' . ReadonlyValueObject::class . '::$age', '/') . '/'
        );

        $this->getJsonMapper()->map(['name' => 'Ada'], ReadonlyValueObject::class);
    }

    /**
     * A class combining a promoted constructor parameter with an additional settable property
     * has both populated: the parameter through the constructor, the extra property afterwards —
     * so a mixed class loses nothing.
     */
    #[Test]
    public function populatesBothConstructorArgumentsAndRemainingProperties(): void
    {
        $result = $this->getJsonMapper()->map(
            ['id' => 'abc', 'note' => 'hello'],
            MixedConstructorEntity::class
        );

        self::assertInstanceOf(MixedConstructorEntity::class, $result);
        self::assertSame('abc', $result->id, 'the promoted argument comes from the constructor');
        self::assertSame('hello', $result->note, 'the extra property is assigned after construction');
    }

    /**
     * A snake_case payload key is normalised before it is matched against a camelCase constructor
     * argument, so name conversion applies on the constructor path too.
     */
    #[Test]
    public function normalisesPayloadKeysToConstructorArgumentNames(): void
    {
        $result = $this->getJsonMapper()->map(['full_name' => 'Ada Lovelace'], CamelCaseReadonly::class);

        self::assertInstanceOf(CamelCaseReadonly::class, $result);
        self::assertSame('Ada Lovelace', $result->fullName);
    }

    /**
     * An explicit null payload for a constructor argument marked ReplaceNullWithDefaultValue
     * falls back to the parameter default rather than being passed through as null.
     */
    #[Test]
    public function appliesReplaceNullWithDefaultValueOnTheConstructorPath(): void
    {
        $result = $this->getJsonMapper()->map(['limit' => null], ReplaceNullReadonly::class);

        self::assertInstanceOf(ReplaceNullReadonly::class, $result);
        self::assertSame(10, $result->limit, 'the null limit falls back to the constructor default');
    }

    /**
     * A payload list is spread across a variadic constructor parameter.
     */
    #[Test]
    public function spreadsAListIntoAVariadicConstructorArgument(): void
    {
        $result = $this->getJsonMapper()->map(['tags' => ['a', 'b', 'c']], VariadicConstructor::class);

        self::assertInstanceOf(VariadicConstructor::class, $result);
        self::assertSame(['a', 'b', 'c'], $result->tags);
    }

    /**
     * A collection-typed constructor argument is converted element by element into the declared
     * value objects, so nested collections work in constructor position.
     */
    #[Test]
    public function convertsACollectionTypedConstructorArgument(): void
    {
        $result = $this->getJsonMapper()->map(
            [
                'items' => [
                    ['name' => 'Ada', 'age' => 36],
                    ['name' => 'Alan', 'age' => 41],
                ],
            ],
            ReadonlyCollectionHolder::class
        );

        self::assertInstanceOf(ReadonlyCollectionHolder::class, $result);
        self::assertCount(2, $result->items);
        self::assertContainsOnlyInstancesOf(ReadonlyValueObject::class, $result->items);
        self::assertSame('Ada', $result->items[0]->name);
        self::assertSame(41, $result->items[1]->age);
    }

    /**
     * In strict mode an omitted argument whose default lives on the constructor parameter (a
     * promoted property) is not reported as missing, since construction supplies the default.
     */
    #[Test]
    public function doesNotReportAPromotedDefaultAsMissingInStrictMode(): void
    {
        $result = $this->getJsonMapper([], JsonMapperConfiguration::strict())->map([], ReadonlyEntity::class);

        self::assertInstanceOf(ReadonlyEntity::class, $result);
        self::assertSame('initial', $result->id);
    }
}
