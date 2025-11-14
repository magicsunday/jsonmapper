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
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\Test\Classes\CamelCasePerson;
use MagicSunday\Test\Classes\Simple;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MagicSunday\JsonMapper::createWithDefaults
 */
final class JsonMapperFactoryTest extends TestCase
{
    public function testCreateWithDefaultsReturnsConfiguredMapper(): void
    {
        $mapper = JsonMapper::createWithDefaults();

        $payload = (object) [
            'id'   => 42,
            'name' => 'Example',
        ];

        $result = $mapper->map($payload, Simple::class);

        self::assertInstanceOf(Simple::class, $result);
        self::assertSame(42, $result->id);
        self::assertSame('Example', $result->name);
    }

    public function testCreateWithDefaultsUsesProvidedNameConverter(): void
    {
        $mapper = JsonMapper::createWithDefaults(new CamelCasePropertyNameConverter());

        $payload = (object) [
            'first_name' => 'Ada',
        ];

        $result = $mapper->map($payload, CamelCasePerson::class);

        self::assertInstanceOf(CamelCasePerson::class, $result);
        self::assertSame('Ada', $result->firstName);
    }
}
