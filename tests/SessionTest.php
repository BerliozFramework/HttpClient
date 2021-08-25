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

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use DateTimeImmutable;
use ElGigi\HarParser\Parser;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    /**
     * @depends testGetHar
     */
    public function testCreateFromHar(Session $session)
    {
        $har = $session->getHar();

        $this->assertEquals(
            (string)$session->getHistory()->getFirst()->getResponse()->getBody(),
            (string)Session::createFromHar($har)->getHistory()->getFirst()->getResponse()->getBody(),
        );
        $this->assertEquals(
            (string)$session->getHistory()->getLast()->getResponse()->getBody(),
            (string)Session::createFromHar($har)->getHistory()->getLast()->getResponse()->getBody(),
        );
        $this->assertEquals(count($session->getCookies()), count(Session::createFromHar($har)->getCookies()));
        $this->assertEquals(count($session->getHistory()), count(Session::createFromHar($har)->getHistory()));
    }

    public function testCreateFromHar_2()
    {
        $harParser = new Parser();

        $harFile = __DIR__ . '/../vendor/elgigi/har-parser/tests/example.har';
        $session = Session::createFromHar($harParser->parse($harFile, true));

        $this->assertCount(15, $session->getCookies());
        $this->assertCount(61, $session->getHistory());
    }

    public function testGetHar()
    {
        $session = new Session();
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com'),
            new Response('HOME', headers: ['Content-Type' => 'text/html']),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send:     2,
                wait:     .5,
                receive:  10,
                total:    12.5
            ),
        );
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com/docs/'),
            new Response(null, statusCode: 301, headers: ['Location' => ['/docs/current/']]),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send:     1.2,
                wait:     .5,
                receive:  10,
                total:    11.7
            ),
        );
        $session->getHistory()->add(
            new CookiesManager(),
            new Request('GET', 'https://getberlioz.com/docs/current/'),
            new Response('DOCUMENTATION', headers: ['Content-Type' => 'text/html']),
            new Timings(
                dateTime: new DateTimeImmutable('2021-07-22T22:30:00.000+02:00'),
                send:     2,
                wait:     .7,
                receive:  9,
                total:    11.7
            ),
        );

        $this->assertEquals(
            '{"log":{"version":"1.2","creator":{"name":"Berlioz HTTP Client","version":"2","comment":"https:\/\/getberlioz.com"},"entries":[{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":12.5,"request":{"method":"GET","url":"https:\/\/getberlioz.com\/","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":200,"statusText":"OK","httpVersion":"1.1","cookies":[],"headers":[{"name":"Content-Type","value":"text\/html"}],"content":{"size":4,"mimeType":"text\/html","text":"HOME"},"redirectURL":"","headersSize":-1,"bodySize":4},"cache":[],"timings":{"send":2,"wait":0.5,"receive":10}},{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":11.7,"request":{"method":"GET","url":"https:\/\/getberlioz.com\/docs\/","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":301,"statusText":"Moved Permanently","httpVersion":"1.1","cookies":[],"headers":[{"name":"Location","value":"\/docs\/current\/"}],"content":{"size":0,"mimeType":"text\/plain","text":""},"redirectURL":"\/docs\/current\/","headersSize":-1,"bodySize":0},"cache":[],"timings":{"send":1.2,"wait":0.5,"receive":10}},{"startedDateTime":"2021-07-22T22:30:00.000+02:00","time":11.7,"request":{"method":"GET","url":"https:\/\/getberlioz.com\/docs\/current\/","httpVersion":"1.1","cookies":[],"headers":[],"queryString":[{"name":"","value":""}],"headersSize":-1,"bodySize":0},"response":{"status":200,"statusText":"OK","httpVersion":"1.1","cookies":[],"headers":[{"name":"Content-Type","value":"text\/html"}],"content":{"size":13,"mimeType":"text\/html","text":"DOCUMENTATION"},"redirectURL":"","headersSize":-1,"bodySize":13},"cache":[],"timings":{"send":2,"wait":0.7,"receive":9}}]}}',
            json_encode($session->getHar())
        );

        return $session;
    }
}
