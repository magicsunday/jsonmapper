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

    #[Test]
    public function itLetsTheLastSpellingInThePayloadWin(): void
    {
        // Two spellings of one property in a single payload is a caller mistake rather than a
        // shape to merge, and the mapper resolves it the way PHP resolves a repeated array key:
        // the later one wins. Pinned so the order is a decision rather than an accident.
        $result = $this->getJsonMapper()->map(
            ['legacy_label' => 'first', 'ancient-label' => 'second'],
            TwoAliasHolder::class,
        );

        self::assertInstanceOf(TwoAliasHolder::class, $result);
        self::assertSame('second', $result->label);
    }
}
