<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\ErrorHandling;

/**
 * The immutable DTO from the report-contract section of the error-handling recipe. Its required
 * constructor argument is what makes an empty payload unbuildable.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ImmutableArticle
{
    public function __construct(public string $title)
    {
    }
}
