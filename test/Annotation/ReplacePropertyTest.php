<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Annotation;

use MagicSunday\Test\Classes\ReplacePropertyTestClass;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class ReplacePropertyTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class ReplacePropertyTest extends TestCase
{
    /**
     * Tests replacing a property.
     *
     * @test
     */
    public function replaceProperty()
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
