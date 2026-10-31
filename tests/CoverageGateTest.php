<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function dirname;
use function file_put_contents;
use function is_file;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * Tests that the coverage gate script cannot be disabled by a missing or malformed threshold, and
 * that it still compares the measured ratio with the threshold it is given.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
#[CoversNothing]
final class CoverageGateTest extends TestCase
{
    private string $cloverPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(sys_get_temp_dir(), 'clover');

        self::assertNotFalse($path);

        $this->cloverPath = $path;

        file_put_contents(
            $this->cloverPath,
            '<?xml version="1.0"?><coverage><project><metrics statements="100" coveredstatements="96"/></project></coverage>',
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->cloverPath)) {
            unlink($this->cloverPath);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: list<string>, 1: int}>
     */
    public static function thresholdArgumentProvider(): array
    {
        return [
            'threshold missing'           => [[], 2],
            'threshold empty'             => [[''], 2],
            'threshold not numeric'       => [['abc'], 2],
            'threshold above one hundred' => [['100.5'], 2],
            'threshold below zero'        => [['-1'], 2],
            'ratio below threshold'       => [['97'], 1],
            'ratio at threshold'          => [['96'], 0],
            'ratio above threshold'       => [['90'], 0],
        ];
    }

    /**
     * A missing, empty, non-numeric or out-of-range threshold is a usage error and never an
     * always-pass, while a valid one is compared with the ratio of the report (96 percent).
     *
     * @param list<string> $thresholdArguments
     */
    #[Test]
    #[DataProvider('thresholdArgumentProvider')]
    public function gateExitCodeFollowsThreshold(array $thresholdArguments, int $expectedExitCode): void
    {
        self::assertSame($expectedExitCode, $this->runGate([$this->cloverPath, ...$thresholdArguments]));
    }

    /**
     * A missing report file is a usage error too, so a broken report path cannot pass the gate.
     */
    #[Test]
    public function gateRejectsMissingReport(): void
    {
        self::assertSame(2, $this->runGate([$this->cloverPath . '.missing', '90']));
    }

    /**
     * @param list<string> $arguments
     */
    private function runGate(array $arguments): int
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/.github/scripts/coverage-gate.php', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        self::assertTrue(is_resource($process));

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process);
    }
}
