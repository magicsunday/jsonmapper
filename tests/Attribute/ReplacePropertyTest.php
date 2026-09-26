<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Attribute;

use MagicSunday\JsonMapper;
use MagicSunday\Test\Classes\ReplacePropertyTestClass;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class ReplacePropertyTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
class ReplacePropertyTest extends TestCase
{
    /**
     * Tests replacing a property.
     */
    #[Test]
    public function replaceProperty(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
  "ftype": 123,
  "super-cryptic-name": "This is my name",
  "untouchedProperty": "Default value"
}
JSON
                ),
                ReplacePropertyTestClass::class
            );

        self::assertInstanceOf(ReplacePropertyTestClass::class, $result);
        self::assertSame(123, $result->getType());
        self::assertSame('This is my name', $result->name);
        self::assertSame('Default value', $result->untouchedProperty);
    }
}
