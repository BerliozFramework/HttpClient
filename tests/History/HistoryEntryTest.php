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

namespace Berlioz\Http\Client\Tests\History;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\History\HistoryEntry;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use PHPUnit\Framework\TestCase;

class HistoryEntryTest extends TestCase
{
    public function testSerialize()
    {
        $entry = new HistoryEntry(
            new CookiesManager(),
            new Request('GET', 'fake', 'FOO'), new Response('BAR'),
        );

        $serialized = serialize($entry);
        /** @var HistoryEntry $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertEquals(
            (string)$unserialized->getRequest()->getBody(),
            (string)$entry->getRequest()->getBody()
        );
        $this->assertEquals(
            (string)$unserialized->getResponse()->getBody(),
            (string)$entry->getResponse()->getBody()
        );
    }

    public function testGetRequest()
    {
        $entry = new HistoryEntry(
            new CookiesManager(),
            $request = new Request('GET', 'fake', 'FOO'),
            new Response('BAR')
        );

        $this->assertSame($request, $entry->getRequest());
    }

    public function testGetResponse()
    {
        $entry = new HistoryEntry(
            new CookiesManager(),
            new Request('GET', 'fake', 'FOO'),
            $response = new Response('BAR'),
        );

        $this->assertSame($response, $entry->getResponse());
    }
}
