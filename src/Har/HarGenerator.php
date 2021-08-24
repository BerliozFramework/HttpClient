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

use Berlioz\Http\Client\Components\CookieParserTrait;
use Berlioz\Http\Client\History\HistoryEntry;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Uri;
use DateTimeImmutable;
use ElGigi\HarParser\Entities as Har;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HarGenerator
{
    use CookieParserTrait;

    /**
     * Handle history.
     *
     * @param Session $session
     *
     * @return Har\Log
     */
    public function handle(Session $session): Har\Log
    {
        $log = new Har\Log(
            version: '1.2',
            creator: new Har\Creator(name: 'Berlioz HTTP Client', version: '2', comment: 'https://getberlioz.com'),
            browser: null,
            pages:   [],
            entries: [],
        );

        /** @var HistoryEntry $historyEntry */
        foreach ($session->getHistory() as $historyEntry) {
            if (null === $historyEntry->getResponse()) {
                continue;
            }

            $log->addEntry($this->getEntry($historyEntry));
        }

        return $log;
    }

    protected function getEntry(HistoryEntry $entry): Har\Entry
    {
        $request = $this->getRequest($entry->getRequest(), $entry->getCookies());
        $response = $this->getResponse($entry->getResponse(), $entry->getRequest()->getUri());

        return new Har\Entry(
            pageref:         null,
            startedDateTime: $entry->getTimings()?->getDateTime() ?? new DateTimeImmutable(),
            time:            $entry->getTimings()?->getTotal() ?? -1,
            request:         $request,
            response:        $response ?? null,
            cache:           [],
            timings:         new Har\Timings(
                                 blocked: $entry->getTimings()?->getBlocked() ?? null,
                                 dns:     $entry->getTimings()?->getDns() ?? null,
                                 connect: $entry->getTimings()?->getConnect() ?? null,
                                 send:    $entry->getTimings()?->getSend() ?? 0,
                                 wait:    $entry->getTimings()?->getWait() ?? 0,
                                 receive: $entry->getTimings()?->getReceive() ?? 0,
                                 ssl:     $entry->getTimings()?->getSsl() ?? null,
                             ),
            serverIPAddress: null,
        );
    }

    protected function getRequest(RequestInterface $request, array $cookies): Har\Request
    {
        if ($request->getBody()->getSize() > 0) {
            $postData = new Har\PostData(
                mimeType: $request->getHeader('Content-Type')[0] ?? 'text/plain',
                params:   [],
                text:     $request->getBody()->getContents(),
            );
        }

        return new Har\Request(
            method:      $request->getMethod(),
            url:         (string)$request->getUri(),
            httpVersion: $request->getProtocolVersion(),
            cookies:     array_map(
                             function ($cookie) {
                                 return Har\Cookie::load($cookie);
                             },
                             $cookies
                         ),
            headers:     $this->getHeaders($request),
            queryString: array_map(
                             function ($value) {
                                 $value = explode('=', $value, 2);

                                 return new Har\QueryString($value[0], urldecode($value[1] ?? ''));
                             },
                             explode(ini_get('arg_separator.output'), $request->getUri()->getQuery())
                         ),
            postData:    $postData ?? null,
            headersSize: -1,
            bodySize:    $request->getBody()->getSize(),
        );
    }

    protected function getResponse(ResponseInterface $response, Uri $uri): Har\Response
    {
        $cookies = array_map(
            function ($raw) use ($uri) {
                $cookieData = $this->parseCookie($raw);

                return new Har\Cookie(
                    name:     $cookieData['name'],
                    value:    $cookieData['value'],
                    path:     $cookieData['path'],
                    domain:   $cookieData['domain'] ?? $uri->getHost(),
                    expires:  $cookieData['expires'],
                    httpOnly: $cookieData['httponly'],
                    secure:   $cookieData['secure'],
                    sameSite: $cookieData['samesite'],
                );
            },
            $response->getHeader('Set-Cookie')
        );

        $content = new Har\Content(
            size:        $response->getBody()->getSize(),
            compression: null,
            mimeType:    $response->getHeader('Content-Type')[0] ?? 'text/plain',
            text:        $response->getBody()->getContents(),
        );

        return new Har\Response(
            status:      $response->getStatusCode(),
            statusText:  $response->getReasonPhrase(),
            httpVersion: $response->getProtocolVersion(),
            cookies:     $cookies,
            headers:     $this->getHeaders($response),
            content:     $content,
            redirectURL: $response->getHeader('Location')[0] ?? '',
            headersSize: -1,
            bodySize:    $response->getBody()->getSize(),
        );
    }

    /**
     * Get headers.
     *
     * @param MessageInterface $message
     *
     * @return array
     */
    protected function getHeaders(MessageInterface $message): array
    {
        $final = [];

        foreach ($message->getHeaders() as $name => $headers) {
            foreach ($headers as $value) {
                $final[] = new Har\Header(name: $name, value: $value);
            }
        }

        return $final;
    }
}