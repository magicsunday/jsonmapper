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
 * Class PlainArrayClass.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class PlainArrayClass
{
    /**
     * @var array<int>
     */
    public array $values;

    /**
     * @return array<int>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
