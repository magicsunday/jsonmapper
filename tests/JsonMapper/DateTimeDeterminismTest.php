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
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
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
    private string $originalTimezone;

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

    #[Test]
    public function itLetsAZoneInThePayloadWinOverTheConfiguredOne(): void
    {
        // The reason the timezone can be passed unconditionally: when the format carries a zone,
        // PHP ignores the argument. Reading the payload as UTC would be worse than the
        // host-dependent behaviour this replaces - it would discard what the payload stated.
        //
        // The configured zone is deliberately NOT UTC, and the host is a third zone again. With
        // the configured zone left at its UTC default, an argument that DID override the payload
        // would still be caught only by accident, and only for payloads that happen not to be UTC.
        date_default_timezone_set('America/New_York');

        $result = $this->getJsonMapper(
            config: JsonMapperConfiguration::lenient()->withDefaultTimezone('Europe/Berlin'),
        )->map(['createdAt' => '2024-01-01T12:00:00+09:00'], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('2024-01-01T12:00:00+09:00', $result->createdAt->format('c'));
    }

    /**
     * @param string $hostTimezone Timezone the process runs in while mapping
     */
    #[Test]
    #[DataProvider('hostTimezoneProvider')]
    public function itParsesAStringThatMissesTheFormatIdenticallyOnEveryHost(string $hostTimezone): void
    {
        date_default_timezone_set($hostTimezone);

        // The OTHER host-dependent route, and the more common one: a string that does not match
        // the configured format falls through to the constructor, which reads the process default
        // unless told otherwise. Under the default ATOM format that is every zoneless string, so
        // fixing only createFromFormat() left the likelier path broken.
        $result = $this->getJsonMapper()->map(
            ['createdAt' => '2024-01-01 12:00:00'],
            DateTimeHolder::class,
        );

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('2024-01-01T12:00:00+00:00', $result->createdAt->format('c'));
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

    /**
     * @return array<string, array{float}>
     */
    public static function unusableTimestampProvider(): array
    {
        return [
            'infinite'     => [INF],
            'not a number' => [NAN],
            'out of range' => [1.0e20],
        ];
    }

    /**
     * @param float $timestamp Value the date constructor cannot represent
     */
    #[Test]
    #[DataProvider('unusableTimestampProvider')]
    public function itReportsAFloatNoDateCanRepresent(float $timestamp): void
    {
        // These reach the constructor and fail there, which the catch turns into a mapping error
        // rather than letting a native one escape. Pinned as a group because they share exactly
        // that route - an earlier guard rejecting them separately would be a branch producing the
        // identical record, and so unobservable.
        $result = $this->getJsonMapper()->mapWithReport(['createdAt' => $timestamp], DateTimeHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One unusable value, one record.');
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertSame('$.createdAt', $errors[0]->getPath());
    }

    #[Test]
    public function itKeepsAnInstantFromAFractionalTimestampHostIndependent(): void
    {
        date_default_timezone_set('Asia/Tokyo');

        $result = $this->getJsonMapper()->map(['createdAt' => 1700000000.5], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);

        // Asserted on the OFFSET, not on format('U'): the latter is timezone-invariant by
        // definition, so it returns the same string whatever zone the instance carries and could
        // not detect a host dependency if one were reintroduced.
        //
        // The offset rather than the zone NAME, because a leading @ produces a fixed-offset zone
        // reported as "+00:00" rather than "UTC" - the same instant, a different label, and
        // pinning the label would make this test about PHP's naming instead of about the host.
        self::assertSame(0, $result->createdAt->getOffset(), 'A timestamp is an absolute instant.');
        self::assertSame('2023-11-14T22:13:20+00:00', $result->createdAt->format('c'));
    }
}
