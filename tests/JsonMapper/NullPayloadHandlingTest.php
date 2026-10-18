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
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use MagicSunday\Test\Fixtures\NullPayload\NullableTargetsHolder;
use MagicSunday\Test\Fixtures\NullPayload\RecordingTypeHandler;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

/**
 * What a null payload means is decided before the strategy chain runs. A null the target accepts is
 * returned as it is and a null the target forbids is rejected, so no strategy and no registered
 * type handler is ever asked about one.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class NullPayloadHandlingTest extends TestCase
{
    #[Test]
    public function itNeverAsksATypeHandlerAboutANullForANullableTarget(): void
    {
        $handler = new RecordingTypeHandler();
        $mapper  = $this->getJsonMapper();
        $mapper->addTypeHandler($handler);

        $holder = $mapper->map(['status' => null, 'anything' => null], NullableTargetsHolder::class);

        self::assertInstanceOf(NullableTargetsHolder::class, $holder);
        self::assertNull($holder->status, 'A null for a nullable class type is assigned as it is.');
        self::assertNull($holder->anything, 'A null for a mixed property is assigned as it is.');
        self::assertSame(0, $handler->getAskedCount(), 'The handler is never asked about a null.');
    }

    #[Test]
    public function itAsksTheSameTypeHandlerAboutValuesThatAreNotNull(): void
    {
        // The control for the test above. Without it a count of zero would also be what a handler
        // that is never reached for any value reports, and the test could not tell the two apart.
        $handler = new RecordingTypeHandler();
        $mapper  = $this->getJsonMapper();
        $mapper->addTypeHandler($handler);

        $holder = $mapper->map(['status' => 'active', 'anything' => 'inactive'], NullableTargetsHolder::class);

        self::assertInstanceOf(NullableTargetsHolder::class, $holder);
        self::assertSame(SampleStatus::Active, $holder->status);
        self::assertSame(SampleStatus::Inactive, $holder->anything);
        self::assertGreaterThanOrEqual(2, $handler->getAskedCount(), 'The handler is asked about both values that are not null.');
    }

    #[Test]
    public function itRejectsANullForANonNullableObjectInStrictMode(): void
    {
        // Without the rejection the null would be accepted and assigned without a word, so a strict
        // run would return normally for a payload that its own declared type forbids.
        $this->expectException(TypeMismatchException::class);

        $this->getJsonMapper()->map(
            ['name' => 'John Doe', 'simple' => null],
            Base::class,
            null,
            null,
            JsonMapperConfiguration::strict(),
        );
    }
}
