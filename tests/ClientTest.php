<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Client;
use Berlioz\Http\Client\Exception\HttpException;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

class ClientTest extends TestCase
{
    /** @var \Symfony\Component\Process\Process */
    private static $process;

    public static function setUpBeforeClass(): void
    {
        self::$process = new Process("php -S localhost:8080 -t " . realpath(__DIR__ . '/server'));
        self::$process->start();
        usleep(100000);
    }

    public static function tearDownAfterClass(): void
    {
        self::$process->stop();
    }

    public function testGet()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->get($uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testPost()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->post($uri, '');

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('POST', $bodyExploded[0]);
    }

    public function testPut()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->put($uri, '');

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('PUT', $bodyExploded[0]);
    }

    public function testPatch()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->patch($uri, '');

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('PATCH', $bodyExploded[0]);
    }

    public function testTrace()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->trace($uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('TRACE', $bodyExploded[0]);
    }

    public function testOptions()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->options($uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('OPTIONS', $bodyExploded[0]);
    }

    public function testHead()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->head($uri);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDelete()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->delete($uri);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSendRequest()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $request = new Request('get', $uri);

        $client = new Client();
        $response = $client->sendRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSendRequestError()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('404 - Not Found');

        $uri = new Uri('http', 'localhost', 8080, '/404');
        $request = new Request('get', $uri);
        $client = new Client();
        $response = $client->sendRequest($request);
    }

    public function testHistory()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $client->get($uri);
        $client->post($uri);

        $history1 = $client->getHistory(0);
        $history2 = $client->getHistory(1);

        $this->assertCount(2, $client->getHistory());
        $this->assertInstanceOf(RequestInterface::class, $history1['request']);
        $this->assertInstanceOf(ResponseInterface::class, $history1['response']);
        $this->assertEquals('GET', $history1['request']->getMethod());
        $this->assertEquals('POST', $history2['request']->getMethod());

        $client->clearHistory();
        $this->assertCount(0, $client->getHistory());
    }

    public function testSetDefaultHeaders()
    {
        $headers = ['Header1' => ['Value']];
        $headers2 = ['Header2' => ['Value']];

        $client = new Client();
        $client->setDefaultHeaders(['Header1' => ['Value']]);

        $this->assertEquals($headers, $client->getDefaultHeaders());
        $this->assertEquals($headers['Header1'], $client->getDefaultHeader('Header1'));

        $client->setDefaultHeaders($headers2);
        $this->assertEquals($headers2, $client->getDefaultHeaders());

        $client->setDefaultHeaders($headers, false);
        $this->assertEquals(array_merge($headers, $headers2), $client->getDefaultHeaders());
    }

    public function testSetDefaultHeader()
    {
        $client = new Client();

        $class = new \ReflectionObject($client);
        $property = $class->getProperty('defaultHeaders');
        $property->setAccessible(true);
        $defaultHeaders = $property->getValue($client);

        // Tests
        $client->setDefaultHeader('Header1', 'Value');
        $this->assertEquals(array_merge($defaultHeaders, ['Header1' => ['Value']]), $property->getValue($client));
        $client->setDefaultHeader('Header1', 'Value2');
        $this->assertEquals(array_merge($defaultHeaders, ['Header1' => ['Value2']]), $property->getValue($client));
        $client->setDefaultHeader('Header1', 'Value1', false);
        $this->assertEquals(
            array_merge(
                $defaultHeaders, [
                'Header1' => [
                    'Value2',
                    'Value1',
                ],
            ]
            ), $property->getValue($client)
        );

        // Test request headers
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client->get($uri);
        $history = $client->getHistory(0);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history['request'];

        $this->assertEquals(array_merge($defaultHeaders, ['Header1' => ['Value2', 'Value1']]), $request->getHeaders());
    }

    public function testRequest()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client;
        $response = $client->request('get', $uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testCurlOptions()
    {
        $options = [CURL_HTTP_VERSION_1_0, CURL_IPRESOLVE_V6];
        $client = new Client();

        $client->setCurlOptions($options);
        $this->assertEquals($options, $client->getCurlOptions());

        $client->setCurlOptions([CURL_IPRESOLVE_V6], true);
        $this->assertEquals([CURL_IPRESOLVE_V6], $client->getCurlOptions());

        $client->setCurlOptions(array_merge($options, [CURLINFO_HEADER_OUT]));
        $this->assertEquals($options, $client->getCurlOptions());
    }

    public function testGetCookies()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client;
        $client->request('get', $uri);

        $this->assertEquals('test=value', $client->getCookies()->getCookiesForUri($uri));
    }

    public function testSerialization()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client;
        $client->request('get', $uri);
        $client->request('get', $uri);

        $clientSerialized = serialize($client);
        $clientUnserialized = unserialize($clientSerialized);

        $this->assertEquals(
            $client->getHistory(0)['response']->getBody()->getContents(),
            $clientUnserialized->getHistory(0)['response']->getBody()->getContents()
        );
        $this->assertEquals(
            $client->getHistory(1)['response']->getBody()->getContents(),
            $clientUnserialized->getHistory(1)['response']->getBody()->getContents()
        );
    }
}
