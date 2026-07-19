<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\Test\Classes\DateTimeHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function date_default_timezone_get;
use function date_default_timezone_set;

/**
 * The same payload has to produce the same instant regardless of the host.
 *
 * createFromFormat() was called without a timezone, so a format carrying no zone of its own fell
 * back to the server default. The identical JSON then decoded to three different instants across
 * three deployments - up to fourteen hours apart - with nothing in the payload or the report
 * indicating that anything host-specific had happened.
 *
 * @internal
 */
final class DateTimeDeterminismTest extends TestCase
{
    private string $originalTimezone = 'UTC';

    protected function setUp(): void
    {
        parent::setUp();

        // Restored in tearDown: the setting is process-global, so leaving it changed would make
        // every later test depend on the order this one ran in.
        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostTimezoneProvider(): array
    {
        // Three zones with different offsets, one of them ahead of UTC and one behind, so a result
        // that silently followed the host cannot coincide with the expected instant by luck.
        return [
            'UTC'          => ['UTC'],
            'behind UTC'   => ['America/New_York'],
            'ahead of UTC' => ['Asia/Tokyo'],
        ];
    }

    /**
     * @param string $hostTimezone Timezone the process runs in while mapping
     */
    #[Test]
    #[DataProvider('hostTimezoneProvider')]
    public function itParsesAZonelessFormatIdenticallyOnEveryHost(string $hostTimezone): void
    {
        date_default_timezone_set($hostTimezone);

        $result = $this->getJsonMapper(
            config: JsonMapperConfiguration::lenient()->withDefaultDateFormat('Y-m-d H:i:s'),
        )->map(['createdAt' => '2024-01-01 12:00:00'], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame(
            '2024-01-01T12:00:00+00:00',
            $result->createdAt->format('c'),
            'A zoneless format is read as UTC, not as whatever the host happens to be set to.',
        );
    }

    /**
     * @param string $hostTimezone Timezone the process runs in while mapping
     */
    #[Test]
    #[DataProvider('hostTimezoneProvider')]
    public function itHonoursAZoneCarriedByThePayloadOnEveryHost(string $hostTimezone): void
    {
        date_default_timezone_set($hostTimezone);

        // The counterpart, and the reason the timezone can be passed unconditionally: when the
        // format carries a zone, PHP ignores the argument. Reading this as UTC would be worse than
        // the host-dependent behaviour it replaces - it would discard information the payload
        // actually supplied.
        $result = $this->getJsonMapper()->map(
            ['createdAt' => '2024-01-01T12:00:00+09:00'],
            DateTimeHolder::class,
        );

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('2024-01-01T12:00:00+09:00', $result->createdAt->format('c'));
    }

    #[Test]
    public function itAppliesTheConfiguredTimezoneToAZonelessFormat(): void
    {
        date_default_timezone_set('UTC');

        // UTC is the default, not the only choice: a caller whose zoneless payloads are known to be
        // wall-clock times in one region can say so, and still get the same instant everywhere.
        $result = $this->getJsonMapper(
            config: JsonMapperConfiguration::lenient()
                ->withDefaultDateFormat('Y-m-d H:i:s')
                ->withDefaultTimezone('Asia/Tokyo'),
        )->map(['createdAt' => '2024-01-01 12:00:00'], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('2024-01-01T12:00:00+09:00', $result->createdAt->format('c'));
    }

    #[Test]
    public function itAcceptsAFractionalTimestamp(): void
    {
        // Rejected outright before, which left the property uninitialised - reading it back raised
        // an Error rather than reporting a mapping failure. A JSON number with a fraction is an
        // ordinary way to express sub-second precision, and DateTime can hold it.
        $result = $this->getJsonMapper()->map(['createdAt' => 1700000000.5], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('1700000000', $result->createdAt->format('U'));
        self::assertSame('500000', $result->createdAt->format('u'), 'The fraction survives as microseconds.');
    }

    #[Test]
    public function itAcceptsAnIntegerValuedFloatTimestamp(): void
    {
        $result = $this->getJsonMapper()->map(['createdAt' => 1700000000.0], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('1700000000', $result->createdAt->format('U'));
        self::assertSame('000000', $result->createdAt->format('u'));
    }

    #[Test]
    public function itRejectsANonFiniteTimestamp(): void
    {
        // INF and NAN format as literal "inf"/"nan", which no date constructor understands. They
        // have to be refused as a mapping failure rather than reaching one.
        $result = $this->getJsonMapper()->mapWithReport(['createdAt' => INF], DateTimeHolder::class);

        self::assertTrue($result->getReport()->hasErrors(), 'A non-finite timestamp is reported.');
    }

    #[Test]
    public function itKeepsAnInstantFromAFractionalTimestampHostIndependent(): void
    {
        date_default_timezone_set('Asia/Tokyo');

        $result = $this->getJsonMapper()->map(['createdAt' => 1700000000.5], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);

        // A leading @ makes the value an absolute instant, so it is UTC by definition and the host
        // cannot shift it. Pinned so that a future rewrite of the timestamp path cannot quietly
        // reintroduce the host dependency this class exists to remove.
        self::assertSame('1700000000', $result->createdAt->format('U'));
    }
}
