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
use Berlioz\Http\Client\History\History;
use Berlioz\Http\Client\History\HistoryEntry;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use PHPUnit\Framework\TestCase;

class HistoryTest extends TestCase
{
    public function testCount()
    {
        $history = new History();

        $this->assertCount(0, $history);

        $history->add(new CookiesManager(), new Request('GET', 'fake'));
        $history->addEntry(new HistoryEntry(new CookiesManager(), new Request('GET', 'fake'), new Response()));

        $this->assertCount(2, $history);
    }

    public function testClear()
    {
        $history = new History();
        $history->addEntry(new HistoryEntry(new CookiesManager(), new Request('GET', 'fake'), new Response()));
        $history->addEntry(new HistoryEntry(new CookiesManager(), new Request('GET', 'fake'), new Response()));

        $this->assertCount(2, $history);

        $history->clear();

        $this->assertCount(0, $history);
    }

    public function testAdd()
    {
        $history = new History();
        $history->add(new CookiesManager(), $request = new Request('GET', 'fake'), $response = new Response());

        $this->assertSame($request, $history->get()->getRequest());
        $this->assertSame($response, $history->get()->getResponse());
    }

    public function testAddEntry()
    {
        $history = new History();
        $history->addEntry(
            $entry = new HistoryEntry(
                new CookiesManager(),
                new Request('GET', 'fake'), new Response()
            )
        );

        $this->assertSame($entry, $history->get());
    }

    public function testGetAll()
    {
        $entries = [];
        $history = new History();
        $history->addEntry(
            $entries[] = new HistoryEntry(
                new CookiesManager(),
                new Request('GET', 'fake'), new Response()
            )
        );
        $history->addEntry(
            $entries[] = new HistoryEntry(
                new CookiesManager(),
                new Request('GET', 'fake'), new Response()
            )
        );
        $history->addEntry(
            $entries[] = new HistoryEntry(
                new CookiesManager(),
                new Request('GET', 'fake'), new Response()
            )
        );

        $this->assertIsIterable($history->getAll());
        $this->assertSame($entries, iterator_to_array($history->getAll(), false));
    }

    public function testGet()
    {
        $history = new History();
        $history->add(new CookiesManager(), $request1 = new Request('GET', 'fake'));
        $history->add(new CookiesManager(), $request2 = new Request('GET', 'fake'));
        $history->add(new CookiesManager(), $request3 = new Request('GET', 'fake'));

        $this->assertSame($request3, $history->get()->getRequest());
        $this->assertSame($request3, $history->get(-1)->getRequest());
        $this->assertSame($request1, $history->get(0)->getRequest());
        $this->assertSame($request2, $history->get(1)->getRequest());
        $this->assertSame($request3, $history->get(2)->getRequest());
    }

    public function testGetLast()
    {
        $history = new History();
        $history->add(new CookiesManager(), new Request('GET', 'fake'));
        $history->add(new CookiesManager(), new Request('GET', 'fake'));
        $history->add(new CookiesManager(), $request = new Request('GET', 'fake'));

        $this->assertSame($request, $history->getLast()->getRequest());
    }

    public function testGetFirst()
    {
        $history = new History();
        $history->add(new CookiesManager(), $request = new Request('GET', 'fake'));
        $history->add(new CookiesManager(), new Request('GET', 'fake'));
        $history->add(new CookiesManager(), new Request('GET', 'fake'));

        $this->assertSame($request, $history->getFirst()->getRequest());
    }
}
