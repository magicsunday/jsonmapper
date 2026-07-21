<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Attribute;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\Test\Fixtures\Shapes\TwoAliasHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * ReplaceProperty is repeatable, so one property can be reached by several legacy names - which is
 * what an API that renamed a field twice leaves its consumers with. Each alias has to arrive at the
 * same property, and an alias has to survive the name converter that runs beside it.
 *
 * @internal
 */
final class ReplacePropertyAliasTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function spellingProvider(): array
    {
        return [
            'the first alias'   => ['legacy_label'],
            'the second alias'  => ['ancient-label'],
            'the property name' => ['label'],
        ];
    }

    /**
     * @param string $key Spelling the payload uses
     */
    #[Test]
    #[DataProvider('spellingProvider')]
    public function itAcceptsEverySpellingThatNamesTheSameProperty(string $key): void
    {
        $result = $this->getJsonMapper()->map([$key => 'written'], TwoAliasHolder::class);

        self::assertInstanceOf(TwoAliasHolder::class, $result);
        self::assertSame('written', $result->label);
    }

    /**
     * @param string $key Spelling the payload uses
     */
    #[Test]
    #[DataProvider('spellingProvider')]
    public function itDoesNotReportAnAliasAsAnUnknownProperty(string $key): void
    {
        // An alias names no declared property, so without the rename being applied first it looks
        // exactly like a key the class does not have. Strict mode is where that would surface.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())
            ->mapWithReport([$key => 'written'], TwoAliasHolder::class);

        self::assertFalse($result->getReport()->hasErrors());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function collidingSpellingProvider(): array
    {
        return [
            // Both orders, each expecting the value that came LAST in the PAYLOAD. The two aliases
            // are declared on TwoAliasHolder in a fixed order, so testing only one payload order
            // would pass equally for a mapper driven by declaration order - which is a different
            // rule. Feeding both orders makes the payload-order rule the only one that satisfies
            // both rows.
            'legacy then ancient' => [['legacy_label' => 'first', 'ancient-label' => 'second'], 'second'],
            'ancient then legacy' => [['ancient-label' => 'first', 'legacy_label' => 'second'], 'second'],
        ];
    }

    /**
     * @param array<string, string> $payload  Two spellings of the one property, in a given order
     * @param string                $expected The value that came last in the payload
     */
    #[Test]
    #[DataProvider('collidingSpellingProvider')]
    public function itLetsTheLastSpellingInThePayloadWin(array $payload, string $expected): void
    {
        // Two spellings of one property in a single payload is a caller mistake rather than a
        // shape to merge, and the mapper resolves it the way PHP resolves a repeated array key:
        // the later one in the PAYLOAD wins, whatever order the aliases were declared in.
        $result = $this->getJsonMapper()->map($payload, TwoAliasHolder::class);

        self::assertInstanceOf(TwoAliasHolder::class, $result);
        self::assertSame($expected, $result->label);
    }
}
