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
 * A class with a property typed by the keyword that names its own class.
 *
 * The constructor docblock names the keyword, which the docblock reader reports as the keyword on every
 * supported PHP version, and the mapper has to resolve it to the class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class SelfTypedHolder
{
    /**
     * Creates the holder.
     *
     * @param self|null $other Holder of the same class
     * @param string    $name  Name of the holder
     */
    public function __construct(
        public ?self $other = null,
        public string $name = '',
    ) {
    }
}
