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
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use ElGigi\HarParser\Entities as Har;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HarHandler
{
    /**
     * @throws HttpClientException
     */
    public function handle(Har\Log $har): Session
    {
        $session = new Session();

        /** @var Har\Entry|null $entry */
        if ($entry = $har->getEntries()->current()) {
            foreach ($entry->getRequest()->getCookies() as $cookie) {
                $session->getCookies()->addCookie(Cookie::createFromHar($cookie));
            }
        }

        /** @var Har\Entry $entry */
        foreach ($har->getEntries() as $entry) {
            $this->addEntryToSession($session, $entry);
        }

        return $session;
    }

    /**
     * Add entry to session.
     *
     * @param Session $session
     * @param Har\Entry $entry
     *
     * @throws HttpClientException
     */
    public function addEntryToSession(Session $session, Har\Entry $entry): void
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
     * @param Har\Request $request
     *
     * @return RequestInterface
     */
    public function getHttpRequest(Har\Request $request): RequestInterface
    {
        $httpRequest = new Request(
            method: $request->getMethod(),
            uri: $request->getUrl(),
            body: $request->getPostData()?->getText(),
            headers: $this->getHeaders($request),
        );

        // Cookies from HAR entry
        // Do not use the header defined in entry, because can be anonymized!
        $httpRequest = $httpRequest->withHeader(
            'Cookie',
            [
                implode(
                    '; ',
                    array_map(
                        fn(Har\Cookie $cookie) => Cookie::createFromHar($cookie)->getRequestHeader(),
                        $request->getCookies()
                    )
                )
            ]
        );

        return $httpRequest->withProtocolVersion($request->getHttpVersion());
    }

    /**
     * Get HTTP response.
     *
     * @param Har\Response $response
     *
     * @return ResponseInterface
     */
    public function getHttpResponse(Har\Response $response): ResponseInterface
    {
        $body = $response->getContent()->getText();
        if ('base64' === $response->getContent()->getEncoding()) {
            $body = base64_decode($body);
        }

        $httpResponse = new Response(
            body: $body,
            statusCode: $response->getStatus(),
            headers: $this->getHeaders($response),
            reasonPhrase: $response->getStatusText(),
        );

        // Cookies from HAR entry
        // Do not use the header defined in entry, because can be anonymized!
        $httpResponse = $httpResponse->withHeader(
            'Set-Cookie',
            array_map(
                fn(Har\Cookie $cookie) => Cookie::createFromHar($cookie)->getRequestHeader(),
                $response->getCookies()
            )
        );

        return $httpResponse->withProtocolVersion($response->getHttpVersion());
    }

    /**
     * Get timings.
     *
     * @param Har\Entry $entry
     *
     * @return Timings
     */
    public function getTimings(Har\Entry $entry): Timings
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

    protected function getHeaders(Har\Message $message): array
    {
        $final = [];

        /** @var Har\Header $header */
        foreach ($message->getHeaders() as $header) {
            $final[$header->getName()][] = $header->getValue();
        }

        return $final;
    }
}