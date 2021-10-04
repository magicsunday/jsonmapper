<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

/**
 * Class Initialized.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class Initialized
{
    /**
     * @var int
     *
     * @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
     */
    public int $integer = 10;

    /**
     * @var bool
     *
     * @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
     */
    public bool $bool = false;

    /**
     * @var array<string>
     *
     * @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
     */
    public array $array = [];
}
