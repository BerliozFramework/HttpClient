<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Discovery;

use Berlioz\Http\Client\Client;
use Http\Discovery\Strategy\DiscoveryStrategy;
use Psr\Http\Client\ClientInterface;

class BerliozHttpClientStrategy implements DiscoveryStrategy
{
    /**
     * @inheritDoc
     */
    public static function getCandidates($type): array
    {
        if ($type === ClientInterface::class) {
            return [
                [
                    'class' => Client::class,
                    'condition' => [Client::class],
                ],
            ];
        }

        return [];
    }
}
