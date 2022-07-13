<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\HarAdapter;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Message\Request;
use ElGigi\HarParser\Parser;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class HarAdapterTest extends TestCase
{
    public function testConstructWithString()
    {
        $adapter = new HarAdapter(
            har: __DIR__ . '/../example.har',
            strict: false,
        );

        $response = $adapter->sendRequest(new Request('GET', 'https://getberlioz.com/'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testGetName()
    {
        $adapter = new HarAdapter((new Parser())->parse(__DIR__ . '/../example.har', true));

        $this->assertEquals('har', $adapter->getName());
    }

    public function testSendRequest_nonStrictMode()
    {
        $adapter = new HarAdapter(
            har: (new Parser())->parse(__DIR__ . '/../example.har', true),
            strict: false,
        );

        $response = $adapter->sendRequest(new Request('GET', 'https://getberlioz.com/'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendRequest_nonStrictMode_twoTimes()
    {
        $this->expectException(HttpClientException::class);

        $adapter = new HarAdapter(
            har: (new Parser())->parse(__DIR__ . '/../example.har', true),
            strict: false,
        );

        $adapter->sendRequest(new Request('GET', 'https://getberlioz.com/'));
        $adapter->sendRequest(new Request('GET', 'https://getberlioz.com/'));
    }

    public function testSendRequest_nonStrictMode_unknownUri()
    {
        $this->expectException(HttpClientException::class);

        $adapter = new HarAdapter(
            har: (new Parser())->parse(__DIR__ . '/../example.har', true),
            strict: false,
        );
        $adapter->sendRequest(new Request('GET', 'https://gethectororm.com/'));
    }

    public function testSendRequest_strictMode()
    {
        $adapter = new HarAdapter(
            har: (new Parser())->parse(__DIR__ . '/../example.har', true),
            strict: true,
        );

        $response = $adapter->sendRequest(
            new Request(
                'GET',
                // First URI in stack
                'https://ogs.google.com/u/0/widget/app?bc=1&origin=chrome-untrusted%3A%2F%2Fnew-tab-page&origin=chrome%3A%2F%2Fnew-tab-page&cn=app&pid=1&spid=243&hl=fr&dm='
            )
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendRequest_strictMode_badStack()
    {
        $this->expectException(HttpClientException::class);

        $adapter = new HarAdapter(
            har: (new Parser())->parse(__DIR__ . '/../example.har', true),
            strict: true,
        );
        $adapter->sendRequest(new Request('GET', 'https://getberlioz.com/'));
    }
}
