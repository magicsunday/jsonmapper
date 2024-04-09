<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test\Converter;

use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class CamelCasePropertyNameConverterTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class CamelCasePropertyNameConverterTest extends TestCase
{
    /**
     * Tests mapping json properties to camel case.
     *
     * @test
     */
    public function checkCamelCasePropertyNameConverter()
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('camelCaseProperty', $converter->convert('camelCaseProperty'));
        self::assertSame('camelCaseProperty', $converter->convert('camel_case_property'));
        self::assertSame('camelCaseProperty', $converter->convert('camel-case-property'));
        self::assertSame('camelCaseProperty', $converter->convert('camel case property'));
    }
}
