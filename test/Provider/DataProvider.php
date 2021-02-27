<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Provider;

/**
 * Class DataProvider.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DataProvider
{
    /**
     * @return string
     */
    public static function mapArrayJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapArray.json');
    }

    /**
     * @return string
     */
    public static function mapCollectionJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapCollection.json');
    }

    /**
     * @return string
     */
    public static function mapCustomTypeJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapCustomType.json');
    }

    /**
     * @return string
     */
    public static function mapSimpleArrayJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapSimpleArray.json');
    }

    /**
     * @return string
     */
    public static function mapSimpleCollectionJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapSimpleCollection.json');
    }

    /**
     * @return string
     */
    public static function mapSimpleTypesJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapSimpleTypes.json');
    }

    /**
     * @return string
     */
    public static function mapCustomClassNameJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/MapCustomClassName.json');
    }
}
