<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Converter;

use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class CamelCasePropertyNameConverterTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class CamelCasePropertyNameConverterTest extends TestCase
{
    /**
     * Tests mapping json properties to camel case.
     */
    #[Test]
    public function checkCamelCasePropertyNameConverter(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('camelCaseProperty', $converter->convert('camelCaseProperty'));
        self::assertSame('camelCaseProperty', $converter->convert('camel_case_property'));
        self::assertSame('camelCaseProperty', $converter->convert('camel-case-property'));
        self::assertSame('camelCaseProperty', $converter->convert('camel case property'));
    }

    /**
     * Tests conversion of single word properties.
     */
    #[Test]
    public function convertsSingleWord(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('name', $converter->convert('name'));
        self::assertSame('id', $converter->convert('id'));
        self::assertSame('value', $converter->convert('value'));
    }

    /**
     * Tests conversion of uppercase properties.
     */
    #[Test]
    public function convertsUppercaseProperties(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('name', $converter->convert('NAME'));
        self::assertSame('userId', $converter->convert('USER_ID'));
        self::assertSame('firstName', $converter->convert('FIRST_NAME'));
    }

    /**
     * Tests conversion with numbers in property names.
     */
    #[Test]
    public function convertsPropertiesWithNumbers(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('property1', $converter->convert('property_1'));
        self::assertSame('item2Name', $converter->convert('item_2_name'));
        self::assertSame('value123Test', $converter->convert('value_123_test'));
    }

    /**
     * Tests conversion of empty string.
     */
    #[Test]
    public function convertsEmptyString(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('', $converter->convert(''));
    }

    /**
     * Tests conversion with mixed delimiters.
     */
    #[Test]
    public function convertsMixedDelimiters(): void
    {
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame('mixedDelimiterProperty', $converter->convert('mixed_delimiter-property'));
        self::assertSame('complexProperty Name', $converter->convert('complex-property_name'));
    }
}
