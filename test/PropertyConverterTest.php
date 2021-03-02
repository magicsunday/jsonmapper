<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use MagicSunday\Test\Classes\Base;

/**
 * Class PropertyConverterTest
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class PropertyConverterTest extends TestCase
{
    /**
     * @return string[][]
     */
    public function propertyNameConverterDataProvider(): array
    {
        return [
            'case1' => [
                <<<JSON
{
    "privateProperty": "Private property value"
}
JSON
            ],
            'case2' => [
                <<<JSON
{
    "private_property": "Private property value"
}
JSON
            ],
            'case3' => [
                <<<JSON
{
    "private-property": "Private property value"
}
JSON
            ],
            'case4' => [
                <<<JSON
{
    "private property": "Private property value"
}
JSON
            ],
        ];
    }

    /**
     * Tests mapping json properties to camel case.
     *
     * @dataProvider propertyNameConverterDataProvider
     * @test
     *
     * @param string $jsonString
     */
    public function checkCamelCasePropertyNameConverter(string $jsonString): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('Private property value', $result->getPrivateProperty());
    }
}
