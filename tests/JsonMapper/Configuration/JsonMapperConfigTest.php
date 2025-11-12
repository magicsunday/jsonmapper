<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Configuration;

use DateTimeInterface;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JsonMapperConfigTest extends TestCase
{
    #[Test]
    public function itProvidesExpectedDefaults(): void
    {
        $config = new JsonMapperConfig();

        self::assertFalse($config->isStrictMode());
        self::assertFalse($config->shouldIgnoreUnknownProperties());
        self::assertFalse($config->shouldTreatNullAsEmptyCollection());
        self::assertSame(DateTimeInterface::ATOM, $config->getDefaultDateFormat());
        self::assertFalse($config->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itSupportsImmutableUpdates(): void
    {
        $config = new JsonMapperConfig();

        $modified = $config
            ->withStrictMode(true)
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y H:i:s')
            ->withScalarToObjectCasting(true);

        self::assertFalse($config->isStrictMode());
        self::assertFalse($config->shouldIgnoreUnknownProperties());
        self::assertFalse($config->shouldTreatNullAsEmptyCollection());
        self::assertSame(DateTimeInterface::ATOM, $config->getDefaultDateFormat());
        self::assertFalse($config->shouldAllowScalarToObjectCasting());

        self::assertTrue($modified->isStrictMode());
        self::assertTrue($modified->shouldIgnoreUnknownProperties());
        self::assertTrue($modified->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y H:i:s', $modified->getDefaultDateFormat());
        self::assertTrue($modified->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itSerializesAndRestoresItself(): void
    {
        $config = (new JsonMapperConfig())
            ->withStrictMode(true)
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y')
            ->withScalarToObjectCasting(true);

        $restored = JsonMapperConfig::fromArray($config->toArray());

        self::assertTrue($restored->isStrictMode());
        self::assertTrue($restored->shouldIgnoreUnknownProperties());
        self::assertTrue($restored->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $restored->getDefaultDateFormat());
        self::assertTrue($restored->shouldAllowScalarToObjectCasting());
    }
}
