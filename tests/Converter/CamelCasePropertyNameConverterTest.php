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
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @return array<string, array{string, string}>
     */
    public static function propertyNameProvider(): array
    {
        return [
            // The separators an API may use, all meaning the same property.
            'already camel case' => ['camelCaseProperty', 'camelCaseProperty'],
            'snake case'         => ['camel_case_property', 'camelCaseProperty'],
            'kebab case'         => ['camel-case-property', 'camelCaseProperty'],
            'spaces'             => ['camel case property', 'camelCaseProperty'],

            // A digit is a segment of its own to the inflector, and does not become a word
            // boundary: address_line_1 is one property, not a property and a number.
            'trailing digit'            => ['address_line_1', 'addressLine1'],
            'digits inside'             => ['iso_3166_1', 'iso31661'],
            'digit without a separator' => ['user2fa', 'user2fa'],

            // Pascal case differs from the target only in the first letter, which is the whole
            // conversion. An embedded acronym stays as it is: where one word ends and the next
            // begins inside HTTPServer is not something a separator states.
            'pascal case'              => ['PascalCase', 'pascalCase'],
            'pascal case with acronym' => ['HTTPServer', 'hTTPServer'],

            // A name that states every boundary and no case is the one shape where lower-casing
            // first is unambiguous - and without it SCREAMING_SNAKE mapped to aDDRESSLINE, which
            // is no PHP property anyone declares. The rule needs the WHOLE name to be caseless in
            // this way, or it would flatten the acronym above too.
            'screaming snake case' => ['ADDRESS_LINE_1', 'addressLine1'],
            'a bare acronym'       => ['ID', 'id'],
            'single upper letter'  => ['A', 'a'],

            // A non-ASCII SCREAMING key is left as it arrived: strtolower() folds ASCII bytes only,
            // so folding it would half-lower it into a name that is neither the original nor the
            // intended target. A full fold would need ext-mbstring, which the library does not
            // require - so the key is passed through rather than mangled.
            'non-ascii screaming case' => ['ÜBER_MICH', 'ÜBERMICH'],

            // Degenerate inputs, which reach the converter because a payload key can be anything.
            'empty'              => ['', ''],
            'a single letter'    => ['a', 'a'],
            'leading separator'  => ['_leading', 'leading'],
            'trailing separator' => ['trailing_', 'trailing'],
            'repeated separator' => ['double__underscore', 'doubleUnderscore'],
            'no ascii letters'   => ['已', '已'],
        ];
    }

    /**
     * @param string $name     Raw property name as a payload may spell it
     * @param string $expected Property name the mapper looks for
     */
    #[Test]
    #[DataProvider('propertyNameProvider')]
    public function itConvertsAPayloadKeyToThePropertyNameItNames(string $name, string $expected): void
    {
        self::assertSame($expected, (new CamelCasePropertyNameConverter())->convert($name));
    }

    /**
     * @param string $name     Raw property name as a payload may spell it
     * @param string $expected Property name the mapper looks for - the authoritative fixed point
     */
    #[Test]
    #[DataProvider('propertyNameProvider')]
    public function itLeavesAnAlreadyConvertedNameAlone(string $name, string $expected): void
    {
        // Conversion happens on the payload key, but a name that has already been through it can
        // arrive again - a ReplaceProperty alias naming the PHP property, a caller normalising
        // before handing over. A second pass has to be a no-op, or the same property resolves to
        // two different names depending on the route it took.
        //
        // The fixed point is taken from the provider ($expected), not from the unit's own output:
        // asserting convert(convert($name)) === convert($name) would hold for any idempotent
        // implementation, including a broken one, because it never names what the result must be.
        $converter = new CamelCasePropertyNameConverter();

        self::assertSame($expected, $converter->convert($expected), 'The converted name is a fixed point.');
        self::assertSame(
            $expected,
            $converter->convert($converter->convert($name)),
            'And a second pass over the raw name reaches it too.',
        );
    }
}
