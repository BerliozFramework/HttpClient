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

declare(strict_types=1);

namespace Berlioz\Http\Client\Har;

use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\History\HistoryEntry;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\Session;
use ElGigi\HarParser\Entities\Entry;
use ElGigi\HarParser\Entities\Header;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Entities\Request;
use ElGigi\HarParser\Entities\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class HarParser.
 */
class HarParser
{
    /**
     * @throws HttpClientException
     */
    public function handle(Log $har): Session
    {
        $session = new Session();

        /** @var Entry|null $entry */
        if ($entry = $har->getEntries()->current()) {
            foreach ($entry->getRequest()->getCookies() as $cookie) {
                $session->getCookies()->addCookie(Cookie::createFromHar($cookie));
            }
        }

        /** @var Entry $entry */
        foreach ($har->getEntries() as $entry) {
            $this->addEntryToSession($session, $entry);
        }

        return $session;
    }

    /**
     * Add entry to session.
     *
     * @param Session $session
     * @param Entry $entry
     *
     * @throws HttpClientException
     */
    public function addEntryToSession(Session $session, Entry $entry): void
    {
        $request = $this->getHttpRequest($entry->getRequest());
        $response = $this->getHttpResponse($entry->getResponse());
        $timings = $this->getTimings($entry);

        $session->getCookies()->addCookiesFromResponse($request->getUri(), $response);
        $session->getHistory()->addEntry(new HistoryEntry($session->getCookies(), $request, $response, $timings));
    }

    /**
     * Get HTTP request.
     *
     * @param Request $request
     *
     * @return RequestInterface
     */
    public function getHttpRequest(Request $request): RequestInterface
    {
        $httpRequest = new \Berlioz\Http\Message\Request(
            method: $request->getMethod(),
            uri: $request->getUrl(),
            body: $request->getPostData()?->getText(),
            headers: $this->getHeaders($request->getHeaders()),
        );

        return $httpRequest->withProtocolVersion($request->getHttpVersion());
    }

    /**
     * Get HTTP response.
     *
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function getHttpResponse(Response $response): ResponseInterface
    {
        $httpResponse = new \Berlioz\Http\Message\Response(
            body: $response->getContent()->getText(),
            statusCode: $response->getStatus(),
            headers: $this->getHeaders($response->getHeaders()),
            reasonPhrase: $response->getStatusText(),
        );

        return $httpResponse->withProtocolVersion($response->getHttpVersion());
    }

    /**
     * Get timings.
     *
     * @param Entry $entry
     *
     * @return Timings
     */
    public function getTimings(Entry $entry): Timings
    {
        return new Timings(
            dateTime: $entry->getStartedDateTime(),
            send: $entry->getTimings()->getSend(),
            wait: $entry->getTimings()->getWait(),
            receive: $entry->getTimings()->getReceive(),
            total: $entry->getTime(),
            blocked: $entry->getTimings()->getBlocked(),
            dns: $entry->getTimings()->getDns(),
            connect: $entry->getTimings()->getConnect(),
            ssl: $entry->getTimings()->getSsl(),
        );
    }

    protected function getHeaders(array $headers): array
    {
        $final = [];

        /** @var Header $header */
        foreach ($headers as $header) {
            $final[$header->getName()][] = $header->getValue();
        }

        return $final;
    }
}