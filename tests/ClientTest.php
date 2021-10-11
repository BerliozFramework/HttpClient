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

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Client;
use Berlioz\Http\Client\Exception\HttpException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionObject;

class ClientTest extends TestCase
{
    use PhpServerTrait;

    public function testGet()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->get($uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testGet_redirection()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php?redirect=2');
        $client = new Client();
        $response = $client->get($uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testGet_tooManyRedirection()
    {
        $this->expectException(RequestException::class);

        $uri = new Uri('http', 'localhost', 8080, '/request.php?redirect=10');
        $client = new Client();
        $client->get($uri);
    }

    public function testGet_tooManyRedirection_withDefinedNumber()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php?redirect=10');
        $client = new Client();
        $response = $client->get($uri, options: ['followLocationLimit' => 10]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGet_noRedirection()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php?redirect=10');
        $client = new Client();
        $response = $client->get($uri, options: ['followLocation' => false]);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals(
            ['http://localhost:8080/request.php?encoding=&redirect=9'],
            $response->getHeader('Location')
        );
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
        $client->sendRequest($request);
    }

    public function testSessionHistory()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $client->get($uri);
        $client->post($uri);

        $history1 = $client->getSession()->getHistory()->get(0);
        $history2 = $client->getSession()->getHistory()->get(1);

        $this->assertCount(2, $client->getSession()->getHistory());
        $this->assertInstanceOf(RequestInterface::class, $history1->getRequest());
        $this->assertInstanceOf(ResponseInterface::class, $history1->getResponse());
        $this->assertEquals('GET', $history1->getRequest()->getMethod());
        $this->assertEquals('POST', $history2->getRequest()->getMethod());

        $client->getSession()->getHistory()->clear();
        $this->assertCount(0, $client->getSession()->getHistory());
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

        $class = new ReflectionObject($client);
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
                $defaultHeaders,
                [
                    'Header1' => [
                        'Value2',
                        'Value1',
                    ],
                ]
            ),
            $property->getValue($client)
        );

        // Test request headers
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client->get($uri);
        $history = $client->getSession()->getHistory()->get(0);
        $request = $history->getRequest();

        $this->assertEquals(array_merge($defaultHeaders, ['Header1' => ['Value2', 'Value1']]), $request->getHeaders());
    }

    public function testRequest()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $response = $client->request('get', $uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testRequestWithDefaultBaseUri()
    {
        $uri = Uri::createFromString('/request.php');
        $client = new Client(['baseUri' => 'http://localhost:8080']);
        $response = $client->request('get', $uri);

        $this->assertEquals(200, $response->getStatusCode());

        $bodyExploded = preg_split('/\r?\n/', (string)$response->getBody());
        $this->assertEquals('GET', $bodyExploded[0]);
    }

    public function testRequestWithPreviousUriRequest()
    {
        $client = new Client();
        $response1 = $client->get(Uri::createFromString('http://localhost:8080/request.php'));
        $response2 = $client->get(Uri::createFromString('/request.php'));

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testRequestWithCallback()
    {
        $nbCallback = 0;
        $callback = function () use (&$nbCallback) {
            $nbCallback++;
        };
        $client = new Client();
        $response = $client->get('http://localhost:8080/request.php?redirect=2', options: ['callback' => $callback]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(3, $nbCallback);
    }

    public function testRequestWithCallbackAndException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('My exception of callback');

        $callback = fn() => throw new Exception('My exception of callback');
        $client = new Client();
        $client->request('get', 'http://localhost:8080/request.php?redirect=2', options: ['callback' => $callback]);
    }

    public function testSessionCookies()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $client->request('get', $uri);

        $this->assertEquals(
            'test=value',
            implode('; ', $client->getSession()->getCookies()->getCookiesForUri($uri))
        );
    }

    public function testSerialization()
    {
        $uri = new Uri('http', 'localhost', 8080, '/request.php');
        $client = new Client();
        $client->request('get', $uri);
        $client->request('get', $uri);

        $clientSerialized = serialize($client);
        $clientUnserialized = unserialize($clientSerialized);

        $this->assertEquals(
            $client->getSession()->getHistory()->get(0)->getResponse()->getBody()->getContents(),
            $clientUnserialized->getSession()->getHistory()->get(0)->getResponse()->getBody()->getContents()
        );
        $this->assertEquals(
            $client->getSession()->getHistory()->get(1)->getResponse()->getBody()->getContents(),
            $clientUnserialized->getSession()->getHistory()->get(1)->getResponse()->getBody()->getContents()
        );
    }
}
