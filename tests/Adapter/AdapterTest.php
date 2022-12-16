<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\AdapterInterface;
use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Client\Adapter\StreamAdapter;
use Berlioz\Http\Client\Tests\PhpServerTrait;
use Berlioz\Http\Message\Request;
use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    use PhpServerTrait;

    public function adapterProvider(): array
    {
        return [
            [
                new CurlAdapter(),
                'curl'
            ],
            [
                new StreamAdapter(),
                'stream'
            ],
        ];
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetName(AdapterInterface $adapter, $exceptedName)
    {
        $this->assertEquals($exceptedName, $adapter->getName());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSendRequest(AdapterInterface $adapter)
    {
        foreach ([null, 'gzip', 'deflate'] as $encoding) {
            $response = $adapter->sendRequest(
                new Request('GET', 'http://localhost:8080/request.php?encoding=' . $encoding)
            );

            $this->assertEquals(200, $response->getStatusCode());

            if (null !== $encoding) {
                $this->assertEquals([$encoding], $response->getHeader('Content-Encoding'));
            }

            // Try first
            $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
            $this->assertEquals('GET', $bodyExploded[0]);

            // Try another time
            $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
            $this->assertEquals('GET', $bodyExploded[0]);
        }
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSendRequest_redirection(AdapterInterface $adapter)
    {
        $response = $adapter->sendRequest(new Request('GET', 'http://localhost:8080/request.php?redirect=1'));

        $this->assertEquals(301, $response->getStatusCode());
    }
}
