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

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Client;
use Berlioz\Http\Client\Discovery\BerliozDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use PHPUnit\Framework\TestCase;

class PhpHttpDiscoveryTest extends TestCase
{
    public function testImplementation()
    {
        BerliozDiscovery::register();
        $found = Psr18ClientDiscovery::find();

        $this->assertInstanceOf(Client::class, $found);
    }
}
