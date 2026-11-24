<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Exception;

/**
 * Class InvalidOriginException.
 */
class InvalidOriginException extends HttpClientException
{
    public static function invalid(string $origin, string $reason): self
    {
        return new self(sprintf('Invalid credentials origin "%s": %s', $origin, $reason));
    }
}
