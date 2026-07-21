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
use InvalidArgumentException;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A configuration arrives from two directions, and they are held to different standards.
 *
 * A wither is a call the developer wrote, so a value it cannot use is a defect to raise on. An
 * array handed to fromArray() is restored state - a session, a cache entry, a config file - and
 * refusing to restore it would turn one stale key into a run that cannot start at all. It is
 * therefore sanitised to the same defaults an absent key would produce.
 *
 * @internal
 */
final class RestoredConfigurationTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableDateFormatProvider(): array
    {
        return [
            'not a string' => [42],
            'empty'        => [''],
            'null'         => [null],
        ];
    }

    /**
     * @param mixed $persisted Value found under the defaultDateFormat key
     */
    #[Test]
    #[DataProvider('unusableDateFormatProvider')]
    public function itRestoresTheDefaultDateFormatFromAnUnusableStoredValue(mixed $persisted): void
    {
        $configuration = JsonMapperConfiguration::fromArray(['defaultDateFormat' => $persisted]);

        self::assertSame(DateTimeInterface::ATOM, $configuration->getDefaultDateFormat());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableTimezoneProvider(): array
    {
        return [
            'not a string' => [42],
            'empty'        => [''],
            'null'         => [null],
            'a typo'       => ['Europe/Berlim'],
        ];
    }

    /**
     * @param mixed $persisted Value found under the defaultTimezone key
     */
    #[Test]
    #[DataProvider('unusableTimezoneProvider')]
    public function itRestoresTheDefaultTimezoneFromAnUnusableStoredValue(mixed $persisted): void
    {
        $configuration = JsonMapperConfiguration::fromArray(['defaultTimezone' => $persisted]);

        self::assertSame('UTC', $configuration->getDefaultTimezone());
    }

    #[Test]
    public function itKeepsAStoredOffsetThatIsNotAnIdentifier(): void
    {
        // The discriminator for the sanitising above: a plain offset is a legitimate way to state a
        // fixed zone and DateTimeZone accepts it, even though it appears in no identifier list.
        // Validating against listIdentifiers() would silently replace it with UTC.
        $configuration = JsonMapperConfiguration::fromArray(['defaultTimezone' => '+09:00']);

        self::assertSame('+09:00', $configuration->getDefaultTimezone());
    }

    #[Test]
    public function itRefusesATimezoneTheDeveloperNamedInACall(): void
    {
        // The other direction: a wither is code, and code naming a zone that does not exist is a
        // defect. Raised here rather than when a date is parsed, where DateTimeZone would raise a
        // DateInvalidTimeZoneException - no MappingException, escaping mid-run after partial state
        // had already been written, past a report the caller was promised.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown timezone identifier "Europe\/Berlim"/');

        (new JsonMapperConfiguration())->withDefaultTimezone('Europe/Berlim');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableOptionProvider(): array
    {
        return [
            'not a string' => [42],
            'empty'        => [''],
        ];
    }

    /**
     * @param mixed $written Value written straight into the option bag
     */
    #[Test]
    #[DataProvider('unusableOptionProvider')]
    public function itFallsBackWhenTheOptionBagCarriesNoUsableDateFormat(mixed $written): void
    {
        // The option bag is an extension point a handler can write to directly, so it never went
        // through the configuration's own validation. The context answers with the same default an
        // absent key would produce rather than handing the value on to createFromFormat().
        $context = new MappingContext([], [MappingContext::OPTION_DEFAULT_DATE_FORMAT => $written]);

        self::assertSame(DateTimeInterface::ATOM, $context->getDefaultDateFormat());
    }

    /**
     * @param mixed $written Value written straight into the option bag
     */
    #[Test]
    #[DataProvider('unusableOptionProvider')]
    public function itFallsBackWhenTheOptionBagCarriesNoUsableTimezone(mixed $written): void
    {
        $context = new MappingContext([], [MappingContext::OPTION_DEFAULT_TIMEZONE => $written]);

        self::assertSame('UTC', $context->getDefaultTimezone());
    }

    #[Test]
    public function itKeepsTheRootPayloadAvailableToEveryNestedConversion(): void
    {
        // A handler is called with the context of the value it converts, which by then names a
        // nested path. The root input is how it can still reach the document it came from - a
        // sibling discriminator field, say - without the mapper threading one through.
        $payload = ['level2' => ['value' => 'deep']];
        $context = new MappingContext($payload);

        $seenPath = null;
        $seenRoot = null;

        $context->withPathSegment(
            'level2',
            static function (MappingContext $nested) use (&$seenPath, &$seenRoot): void {
                $seenPath = $nested->getPath();
                $seenRoot = $nested->getRootInput();
            },
        );

        self::assertSame('$.level2', $seenPath, 'The nested context has moved on from the root...');
        self::assertSame($payload, $seenRoot, '...and the root input has not moved with it.');
    }
}
