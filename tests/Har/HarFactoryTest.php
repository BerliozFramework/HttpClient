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

namespace Berlioz\Http\Client\Tests\Har;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Har\HarFactory;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use DateTimeImmutable;
use ElGigi\HarParser\Parser;
use Exception;
use PHPUnit\Framework\TestCase;

class HarFactoryTest extends TestCase
{
    public function testCreateHarFromSession()
    {
        $session = new Session();
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com'),
            new Response('HOME', headers: ['Content-Type' => 'text/html']),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send: 2,
                wait: .5,
                receive: 10,
                total: 12.5
            ),
        );
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com/docs/'),
            new Response(null, statusCode: 301, headers: ['Location' => ['/docs/current/']]),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send: 1.2,
                wait: .5,
                receive: 10,
                total: 11.7
            ),
        );
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com/docs/current/'),
            new Response('DOCUMENTATION', headers: ['Content-Type' => 'text/html']),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send: 2,
                wait: .7,
                receive: 9,
                total: 11.7
            ),
        );

        $this->assertEquals(
            '{"log":{"version":"1.2","creator":{"name":"Berlioz HTTP Client","version":"2","comment":"https:\/\/getberlioz.com"},"entries":[{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":12.5,"request":{"method":"GET","url":"https:\/\/getberlioz.com","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":200,"statusText":"OK","httpVersion":"1.1","cookies":[],"headers":[{"name":"Content-Type","value":"text\/html"}],"content":{"size":4,"mimeType":"text\/html","text":"SE9NRQ==","encoding":"base64"},"redirectURL":"","headersSize":-1,"bodySize":4},"cache":[],"timings":{"send":2,"wait":0.5,"receive":10}},{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":11.7,"request":{"method":"GET","url":"https:\/\/getberlioz.com\/docs\/","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":301,"statusText":"Moved Permanently","httpVersion":"1.1","cookies":[],"headers":[{"name":"Location","value":"\/docs\/current\/"}],"content":{"size":0,"mimeType":"text\/plain"},"redirectURL":"\/docs\/current\/","headersSize":-1,"bodySize":0},"cache":[],"timings":{"send":1.2,"wait":0.5,"receive":10}},{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":11.7,"request":{"method":"GET","url":"https:\/\/getberlioz.com\/docs\/current\/","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":200,"statusText":"OK","httpVersion":"1.1","cookies":[],"headers":[{"name":"Content-Type","value":"text\/html"}],"content":{"size":13,"mimeType":"text\/html","text":"RE9DVU1FTlRBVElPTg==","encoding":"base64"},"redirectURL":"","headersSize":-1,"bodySize":13},"cache":[],"timings":{"send":2,"wait":0.7,"receive":9}}]}}',
            json_encode(HarFactory::createHarFromSession($session)),
        );

        return $session;
    }

    /**
     * @depends testCreateHarFromSession
     */
    public function testCreateSession(Session $session)
    {
        $har = HarFactory::createHarFromSession($session);

        $this->assertEquals(
            (string)$session->getHistory()->getFirst()->getResponse()->getBody(),
            (string)HarFactory::createSession($har)->getHistory()->getFirst()->getResponse()->getBody(),
        );
        $this->assertEquals(
            (string)$session->getHistory()->getLast()->getResponse()->getBody(),
            (string)HarFactory::createSession($har)->getHistory()->getLast()->getResponse()->getBody(),
        );
        $this->assertEquals(count($session->getCookies()), count(HarFactory::createSession($har)->getCookies()));
        $this->assertEquals(count($session->getHistory()), count(HarFactory::createSession($har)->getHistory()));
    }

    public function testCreateSession_2()
    {
        $harParser = new Parser();

        $harFile = __DIR__ . '/../../vendor/elgigi/har-parser/tests/example.har';
        $session = HarFactory::createSession($harParser->parse($harFile, true));

        $this->assertCount(15, $session->getCookies());
        $this->assertCount(61, $session->getHistory());
    }

    public function testCreateSessionFromFile()
    {
        $harFile = __DIR__ . '/../../vendor/elgigi/har-parser/tests/example.har';
        $session = HarFactory::createSessionFromFile($harFile);

        $this->assertCount(15, $session->getCookies());
        $this->assertCount(61, $session->getHistory());
    }

    public function testCreateSessionFromFile_notFound()
    {
        $this->expectException(Exception::class);

        $harFile = __DIR__ . '/../../vendor/elgigi/har-parser/tests/fake.har';
        HarFactory::createSessionFromFile($harFile);
    }
}