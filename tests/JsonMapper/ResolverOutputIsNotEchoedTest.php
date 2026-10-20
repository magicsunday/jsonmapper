<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use ArrayObject;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\Test\Classes\CustomConstructor;
use MagicSunday\Test\Fixtures\EntryPoint\AbstractShapeCollection;
use MagicSunday\Test\Fixtures\EntryPoint\Circle;
use MagicSunday\Test\Fixtures\EntryPoint\RequiredShapeHolder;
use MagicSunday\Test\Fixtures\EntryPoint\Shape;
use MagicSunday\Test\Fixtures\EntryPoint\ShapeCollection;
use MagicSunday\Test\Fixtures\EntryPoint\ShapeHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

/**
 * A class-map resolver may derive its answer from the payload, and a consumer may place the
 * expected type of a recorded mismatch in a response. A mismatch therefore names the class the
 * property declares, which is metadata, and never the class the resolver produced.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class ResolverOutputIsNotEchoedTest extends TestCase
{
    /**
     * A scalar cannot populate an object. The resolver answers with a class other than the declared
     * one, as a discriminator without an allowlist can do for a payload it chooses, and the recorded
     * mismatch must still name the declared class.
     */
    #[Test]
    public function aScalarAgainstAnObjectPropertyNamesTheDeclaredClassNotTheResolvedOne(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => ArrayObject::class,
        ])->mapWithReport(['shape' => 'payload chosen text'], ShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(Shape::class, $exception->getExpectedType());
    }

    /**
     * With scalar-to-object casting on, the scalar passes the strategy's own guard and reaches the
     * mapper, which refuses a class whose constructor needs arguments. That refusal names the
     * declared class too, not the one the resolver produced.
     */
    #[Test]
    public function aCastScalarForAClassWithRequiredArgumentsNamesTheDeclaredClass(): void
    {
        $result = $this->getJsonMapper(
            [Shape::class => static fn (): string => CustomConstructor::class],
            JsonMapperConfiguration::lenient()->withScalarToObjectCasting(true),
        )->mapWithReport(['shape' => 'payload chosen text'], ShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(Shape::class, $exception->getExpectedType());
    }

    /**
     * At the entry point the requested class is the caller's own, so it is the class to name, and
     * the class the resolver produced for the payload is not.
     */
    #[Test]
    public function aTopLevelScalarNamesTheRequestedClassNotTheResolvedOne(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => CustomConstructor::class,
        ])->mapWithReport('payload chosen text', Shape::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(Shape::class, $exception->getExpectedType());
    }

    /**
     * A null payload for a collection class yields no collection and is recorded. The record names
     * the collection class the caller asked for, not the one the resolver produced for the payload.
     */
    #[Test]
    public function aNullPayloadForACollectionNamesTheRequestedCollectionClass(): void
    {
        $result = $this->getJsonMapper([
            AbstractShapeCollection::class => static fn (): string => ShapeCollection::class,
        ])->mapWithReport(null, null, AbstractShapeCollection::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(AbstractShapeCollection::class, $exception->getExpectedType());
    }

    /**
     * A failure raised while the resolved object is built or filled is about that object, and an
     * unknown key can only be reported against the class that lacks it. The name is therefore the
     * resolved class here, which is why the guidance is to keep every exception below a class-mapped
     * property out of a response.
     */
    #[Test]
    public function aFailureInsideTheResolvedObjectNamesTheClassThatWasBuilt(): void
    {
        $result = $this->getJsonMapper(
            [Shape::class => static fn (): string => Circle::class],
            JsonMapperConfiguration::strict(),
        )->mapWithReport(['shape' => ['radius' => 1, 'extra' => 2]], RequiredShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(UnknownPropertyException::class, $exception);
        self::assertSame(Circle::class, $exception->getClassName());
    }

    /**
     * The same holds for a constructor argument the resolved class needs and the payload lacks.
     */
    #[Test]
    public function aMissingConstructorArgumentNamesTheResolvedClass(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => CustomConstructor::class,
        ])->mapWithReport(['shape' => ['other' => 1]], RequiredShapeHolder::class);

        $missing = [];

        foreach ($result->getReport()->getErrors() as $error) {
            $exception = $error->getException();

            if ($exception instanceof MissingConstructorArgumentException) {
                $missing[] = $exception;
            }
        }

        self::assertCount(1, $missing);
        self::assertSame(CustomConstructor::class, $missing[0]->getClassName());
    }

    /**
     * A value refused by a property of the resolved class is typed by that property, so the expected
     * type is the property's own declared type and not a class the resolver produced.
     */
    #[Test]
    public function aValueRefusedByAPropertyOfTheResolvedClassNamesThePropertyType(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => Circle::class,
        ])->mapWithReport(['shape' => ['radius' => 'not a number']], RequiredShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame('int', $exception->getExpectedType());
    }

    /**
     * Behind a nullable property a refused value surfaces as a mismatch of the declared class, so
     * what the resolver chose is not exposed by that failure.
     */
    #[Test]
    public function aFailureBehindANullablePropertyNamesTheDeclaredClass(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => Circle::class,
        ])->mapWithReport(['shape' => ['radius' => 'not a number']], ShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(Shape::class, $exception->getExpectedType());
    }

    /**
     * An unknown key behind a nullable property is folded into a mismatch of the declared class too.
     */
    #[Test]
    public function anUnknownKeyBehindANullablePropertyNamesTheDeclaredClass(): void
    {
        $result = $this->getJsonMapper(
            [Shape::class => static fn (): string => Circle::class],
            JsonMapperConfiguration::strict(),
        )->mapWithReport(['shape' => ['radius' => 1, 'extra' => 2]], ShapeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(TypeMismatchException::class, $exception);
        self::assertSame(Shape::class, $exception->getExpectedType());
    }

    /**
     * A missing constructor argument is the exception to that. It is raised by the constructor call
     * itself and reaches the report unchanged, so it names the resolved class even behind a
     * nullable property.
     */
    #[Test]
    public function aMissingConstructorArgumentBehindANullablePropertyStillNamesTheResolvedClass(): void
    {
        $result = $this->getJsonMapper([
            Shape::class => static fn (): string => CustomConstructor::class,
        ])->mapWithReport(['shape' => ['other' => 1]], ShapeHolder::class);

        $missing = [];

        foreach ($result->getReport()->getErrors() as $error) {
            $exception = $error->getException();

            if ($exception instanceof MissingConstructorArgumentException) {
                $missing[] = $exception;
            }
        }

        self::assertCount(1, $missing);
        self::assertSame(CustomConstructor::class, $missing[0]->getClassName());
    }
}
