<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use MagicSunday\Test\Fixtures\ReplaceNull\NullProducingStatusHandler;
use MagicSunday\Test\Fixtures\ReplaceNull\StatusHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * ReplaceNullWithDefaultValue is checked twice per property, and the two checks answer different
 * questions. Before conversion it catches a null PAYLOAD, which the pipeline would otherwise refuse
 * against a non-nullable target. After conversion it catches a null RESULT - a value the payload
 * supplied that the conversion turned into nothing.
 *
 * Only a registered type handler can produce the second: the built-in strategies report a value
 * they cannot convert rather than answering null for it. Without the second check the property
 * would be written with the null the handler returned, contradicting both its declared type and
 * the attribute that says it keeps its default instead.
 *
 * @internal
 */
final class ReplaceNullResultWithDefaultTest extends TestCase
{
    #[Test]
    public function itKeepsTheDefaultWhenAHandlerConvertsThePayloadToNothing(): void
    {
        $mapper = $this->getJsonMapper();
        $mapper->addTypeHandler(new NullProducingStatusHandler());

        $result = $mapper->mapWithReport(['status' => 'not-a-known-case'], StatusHolder::class);

        self::assertFalse($result->getReport()->hasErrors(), 'The handler answered; nothing failed.');

        $mapped = $result->getValue();

        self::assertInstanceOf(StatusHolder::class, $mapped);
        self::assertSame(SampleStatus::Inactive, $mapped->status);
    }

    #[Test]
    public function itKeepsTheDefaultWhenThePayloadItselfIsNull(): void
    {
        // The first of the two checks, for contrast: here the payload is the null, and conversion
        // never runs at all - the pipeline would refuse it against the non-nullable target.
        $mapper = $this->getJsonMapper();
        $mapper->addTypeHandler(new NullProducingStatusHandler());

        $result = $mapper->mapWithReport(['status' => null], StatusHolder::class);

        self::assertFalse($result->getReport()->hasErrors());

        $mapped = $result->getValue();

        self::assertInstanceOf(StatusHolder::class, $mapped);
        self::assertSame(SampleStatus::Inactive, $mapped->status);
    }

    #[Test]
    public function itStillTakesTheValueTheHandlerDoesRecognise(): void
    {
        // The control: the default must not swallow a conversion that succeeded.
        $mapper = $this->getJsonMapper();
        $mapper->addTypeHandler(new NullProducingStatusHandler());

        $result = $mapper->map(['status' => 'active'], StatusHolder::class);

        self::assertInstanceOf(StatusHolder::class, $result);
        self::assertSame(SampleStatus::Active, $result->status);
    }
}
