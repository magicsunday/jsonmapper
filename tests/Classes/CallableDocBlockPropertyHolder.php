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
 * A callable-typed property. PHP rejects callable as a native property type, so the type can only
 * reach the mapper through the docblock extractor - which makes this the callable counterpart to
 * the natively typed holders. The default is a callable other than the one mapped onto it, so it
 * still works as a sentinel.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class CallableDocBlockPropertyHolder
{
    /**
     * @var callable
     */
    public $handler = 'trim';
}
