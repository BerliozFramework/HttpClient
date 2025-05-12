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

use Berlioz\Http\Client\Har\HarFactory;
use Berlioz\Http\Client\Session;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SessionTest extends TestCase
{
    public function testGetLastRequest()
    {
        $session = HarFactory::createSessionFromFile(__DIR__ . '/../vendor/elgigi/har-parser/tests/example.har');

        $this->assertInstanceOf(RequestInterface::class, $session->getLastRequest());
        $this->assertSame($session->getHistory()->getLast()->getRequest(), $session->getLastRequest());
    }

    public function testGetLastRequest_none()
    {
        $session = new Session();

        $this->assertNull($session->getLastRequest());
    }

    public function testGetLastResponse()
    {
        $session = HarFactory::createSessionFromFile(__DIR__ . '/../vendor/elgigi/har-parser/tests/example.har');

        $this->assertInstanceOf(ResponseInterface::class, $session->getLastResponse());
        $this->assertSame($session->getHistory()->getLast()->getResponse(), $session->getLastResponse());
    }

    public function testGetLastResponse_none()
    {
        $session = new Session();

        $this->assertNull($session->getLastResponse());
    }
}
